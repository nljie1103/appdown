<?php
/**
 * Apple signing material management for Template Builder 2.0.
 * P12/private data stays under data/, never in public uploads.
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/template_builder.php';
require_auth();

$pdo = get_db();
ensure_template_builder_schema($pdo);
$method = get_request_method();

function tb_identity_cert_sha256(string $pem): string {
    $clean = preg_replace('#-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+#', '', $pem);
    $der = base64_decode((string)$clean, true);
    return $der === false ? '' : strtoupper(hash('sha256', $der));
}

function tb_secure_upload(string $field, array $extensions, string $prefix): array {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => '上传失败'];
    $f = $_FILES[$field];
    if ((int)$f['size'] <= 0 || (int)$f['size'] > 10 * 1024 * 1024) return ['ok' => false, 'error' => '文件为空或超过 10MB'];
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions, true)) return ['ok' => false, 'error' => '文件类型不支持'];
    $dir = template_builder_data_dir() . '/signing';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return ['ok' => false, 'error' => '无法创建私有签名目录'];
    $filename = $prefix . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $path = $dir . '/' . $filename;
    if (!move_uploaded_file($f['tmp_name'], $path)) return ['ok' => false, 'error' => '文件保存失败'];
    @chmod($path, 0600);
    return ['ok' => true, 'path' => $path, 'name' => basename((string)$f['name'])];
}

if ($method === 'GET') {
    $identities = $pdo->query('SELECT id,name,cert_subject,cert_serial,cert_expires,cert_sha256,created_at FROM ios_signing_identities ORDER BY id DESC')->fetchAll();
    $profiles = $pdo->query('SELECT id,name,uuid,team_id,app_identifier,bundle_pattern,profile_type,expires_at,device_count,cert_sha256_json,created_at FROM ios_provisioning_profiles ORDER BY id DESC')->fetchAll();
    foreach ($profiles as &$p) {
        $p['cert_sha256'] = json_decode($p['cert_sha256_json'] ?? '[]', true) ?: [];
        unset($p['cert_sha256_json']);
    }
    unset($p);
    json_response(['identities' => $identities, 'profiles' => $profiles]);
}

csrf_validate();

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload_identity') {
        $name = trim($_POST['name'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($name === '' || $password === '') json_response(['error' => '名称和 P12 密码必填'], 400);
        $up = tb_secure_upload('file', ['p12', 'pfx'], 'identity');
        if (!$up['ok']) json_response(['error' => $up['error']], 400);
        try {
            $raw = file_get_contents($up['path']);
            $parts = [];
            if ($raw === false || !openssl_pkcs12_read($raw, $parts, $password) || empty($parts['cert']) || empty($parts['pkey'])) throw new RuntimeException('P12/PFX 或密码无效');
            $certInfo = openssl_x509_parse($parts['cert']) ?: [];
            $subjectParts = $certInfo['subject'] ?? [];
            $subject = is_array($subjectParts) ? implode(', ', array_map(fn($k,$v)=>$k.'='.$v, array_keys($subjectParts), array_values($subjectParts))) : '';
            $expires = !empty($certInfo['validTo_time_t']) ? gmdate('c', (int)$certInfo['validTo_time_t']) : '';
            $serial = (string)($certInfo['serialNumberHex'] ?? $certInfo['serialNumber'] ?? '');
            $sha = tb_identity_cert_sha256($parts['cert']);
            if ($sha === '') throw new RuntimeException('无法计算证书指纹');
            $stmt = $pdo->prepare('INSERT INTO ios_signing_identities (name,p12_path,p12_password,cert_subject,cert_serial,cert_expires,cert_sha256) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$name, $up['path'], encrypt_secret($password), $subject, $serial, $expires, $sha]);
            json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (Throwable $e) {
            @unlink($up['path']);
            json_response(['error' => $e->getMessage()], 400);
        }
    }

    if ($action === 'upload_profile') {
        $name = trim($_POST['name'] ?? '');
        $up = tb_secure_upload('file', ['mobileprovision'], 'profile');
        if (!$up['ok']) json_response(['error' => $up['error']], 400);
        try {
            $meta = template_builder_parse_profile($up['path']);
            if ($meta['uuid'] === '' || $meta['bundle_pattern'] === '') throw new RuntimeException('Provisioning Profile 缺少 UUID 或 application-identifier');
            if ($meta['expires_at'] !== '' && strtotime($meta['expires_at']) !== false && strtotime($meta['expires_at']) <= time()) throw new RuntimeException('Provisioning Profile 已过期');
            $display = $name !== '' ? $name : ($meta['name'] ?: $up['name']);
            $stmt = $pdo->prepare('INSERT INTO ios_provisioning_profiles (name,profile_path,uuid,team_id,app_identifier,bundle_pattern,profile_type,expires_at,device_count,cert_sha256_json) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$display,$up['path'],$meta['uuid'],$meta['team_id'],$meta['app_identifier'],$meta['bundle_pattern'],$meta['profile_type'],$meta['expires_at'],$meta['device_count'],json_encode($meta['cert_sha256'],JSON_UNESCAPED_SLASHES)]);
            json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'profile' => $meta]);
        } catch (Throwable $e) {
            @unlink($up['path']);
            json_response(['error' => $e->getMessage()], 400);
        }
    }
    json_response(['error' => '无效 action'], 400);
}

if ($method === 'DELETE') {
    $data = get_json_input();
    $type = $data['type'] ?? '';
    $id = (int)($data['id'] ?? 0);
    if (!$id || !in_array($type, ['identity','profile'], true)) json_response(['error' => '参数错误'], 400);
    if ($type === 'identity') {
        $stmt = $pdo->prepare('SELECT p12_path FROM ios_signing_identities WHERE id=?');
        $stmt->execute([$id]); $row = $stmt->fetch();
        if ($row && is_file($row['p12_path'])) @unlink($row['p12_path']);
        $pdo->prepare('DELETE FROM ios_signing_identities WHERE id=?')->execute([$id]);
    } else {
        $stmt = $pdo->prepare('SELECT profile_path FROM ios_provisioning_profiles WHERE id=?');
        $stmt->execute([$id]); $row = $stmt->fetch();
        if ($row && is_file($row['profile_path'])) @unlink($row['profile_path']);
        $pdo->prepare('DELETE FROM ios_provisioning_profiles WHERE id=?')->execute([$id]);
    }
    json_response(['ok' => true]);
}
json_response(['error' => 'method not allowed'], 405);
