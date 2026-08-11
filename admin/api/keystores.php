<?php
/**
 * 签名密钥管理 API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

function validate_keystore_file(string $path, string $storePassword, string $alias = ''): array {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    // PKCS#12 优先用 PHP OpenSSL 真解析。
    if (in_array($ext, ['p12', 'pfx'], true) && function_exists('openssl_pkcs12_read')) {
        $raw = @file_get_contents($path);
        $certs = [];
        if ($raw === false || !@openssl_pkcs12_read($raw, $certs, $storePassword)) {
            return ['ok' => false, 'error' => 'PKCS#12 文件或密钥库密码无效'];
        }
        return ['ok' => true];
    }

    $keytoolOut = [];
    @exec('command -v keytool 2>/dev/null', $keytoolOut, $keytoolCode);
    $keytool = trim($keytoolOut[0] ?? '');
    if ($keytoolCode !== 0 || $keytool === '') {
        return ['ok' => false, 'error' => '未检测到 keytool，无法验证该 Keystore，请先安装 JDK 17'];
    }

    $passFile = secure_temp_secret($storePassword, 'appdown_kspass_');
    try {
        $cmd = escapeshellarg($keytool) . ' -list -keystore ' . escapeshellarg($path) .
            ' -storepass:file ' . escapeshellarg($passFile);
        if ($alias !== '') $cmd .= ' -alias ' . escapeshellarg($alias);
        $cmd .= ' 2>&1';
        $out = [];
        exec($cmd, $out, $code);
        return $code === 0
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Keystore 校验失败：' . redact_sensitive_text(implode("\n", array_slice($out, -8)), [$storePassword])];
    } finally {
        @unlink($passFile);
    }
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM keystores ORDER BY created_at DESC')->fetchAll();
    foreach ($rows as &$row) {
        // 旧版本数据库可能仍是明文；访问密钥管理页时无感迁移到 AES-256-GCM。
        $storeRaw = (string)($row['store_password'] ?? '');
        $keyRaw = (string)($row['key_password'] ?? '');
        if (($storeRaw !== '' && !is_encrypted_secret($storeRaw)) || ($keyRaw !== '' && !is_encrypted_secret($keyRaw))) {
            try {
                $storeRaw = encrypt_secret($storeRaw);
                $keyRaw = encrypt_secret($keyRaw);
                $pdo->prepare("UPDATE keystores SET store_password=?, key_password=?, updated_at=datetime('now') WHERE id=?")
                    ->execute([$storeRaw, $keyRaw, $row['id']]);
                $row['store_password'] = $storeRaw;
                $row['key_password'] = $keyRaw;
            } catch (Throwable $e) {
                // 不让缺少 OpenSSL 的旧站点因为迁移失败而无法查看列表；encrypted=false 会保留提示状态。
            }
        }
        $row['encrypted'] = is_encrypted_secret((string)($row['store_password'] ?? '')) && is_encrypted_secret((string)($row['key_password'] ?? ''));
        $row['store_password'] = $row['store_password'] ? '******' : '';
        $row['key_password'] = $row['key_password'] ? '******' : '';
    }
    unset($row);
    json_response($rows);
}

csrf_validate();

if ($method === 'POST') {
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    if ($action === 'upload') {
        if (empty($_FILES['file'])) json_response(['error' => '请上传keystore文件'], 400);
        $result = handle_upload('file', 'keystore');
        if (!$result['ok']) json_response($result, 400);

        $name = trim($_POST['name'] ?? '');
        $alias = trim($_POST['alias'] ?? '');
        $storePwd = $_POST['store_password'] ?? '';
        $keyPwd = $_POST['key_password'] ?? '';
        if (empty($name) || empty($alias) || empty($storePwd) || empty($keyPwd)) {
            delete_upload($result['url']);
            json_response(['error' => '名称、别名和密码为必填项'], 400);
        }
        if (strlen($storePwd) < 6 || strlen($keyPwd) < 6) {
            delete_upload($result['url']);
            json_response(['error' => '密码至少6个字符'], 400);
        }

        $fullPath = realpath(__DIR__ . '/../../' . $result['url']);
        $check = $fullPath ? validate_keystore_file($fullPath, $storePwd, $alias) : ['ok' => false, 'error' => '上传文件不存在'];
        if (!$check['ok']) {
            delete_upload($result['url']);
            json_response(['error' => $check['error']], 400);
        }

        $stmt = $pdo->prepare('INSERT INTO keystores (name, file_url, alias, store_password, key_password) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $result['url'], $alias, encrypt_secret($storePwd), encrypt_secret($keyPwd)]);
        json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }

    if ($action === 'generate') {
        $data = get_json_input();
        $name = trim($data['name'] ?? '');
        $alias = trim($data['alias'] ?? '');
        $storePwd = $data['store_password'] ?? '';
        $keyPwd = $data['key_password'] ?? '';
        $validity = min(100, max(1, (int)($data['validity_years'] ?? 25)));
        $cn = mb_substr(trim($data['common_name'] ?? ''), 0, 64);
        $ou = mb_substr(trim($data['org_unit'] ?? ''), 0, 64);
        $org = mb_substr(trim($data['org_name'] ?? ''), 0, 64);
        $loc = mb_substr(trim($data['locality'] ?? ''), 0, 64);
        $st = mb_substr(trim($data['state_name'] ?? ''), 0, 64);
        $c = strtoupper(mb_substr(trim($data['country'] ?? ''), 0, 2));

        if (empty($name) || empty($alias)) json_response(['error' => '名称和别名为必填项'], 400);
        if (strlen($storePwd) < 6 || strlen($keyPwd) < 6) json_response(['error' => '密码至少6个字符'], 400);
        if ($c !== '' && !preg_match('/^[A-Z]{2}$/', $c)) json_response(['error' => '国家代码必须是2位字母'], 400);
        if (!preg_match('/^[A-Za-z0-9._-]{1,64}$/', $alias)) json_response(['error' => '别名只能包含字母、数字、点、下划线和横线'], 400);

        $dir = __DIR__ . '/../../uploads/keystores';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) json_response(['error' => '无法创建密钥目录'], 500);
        $safeName = preg_replace('/[^\w\-]/', '_', $name);
        $filename = resolve_filename_collision($dir, $safeName . '_' . time(), 'jks');
        $filepath = $dir . '/' . $filename;

        $dnameParts = [];
        if ($cn) $dnameParts[] = 'CN=' . $cn;
        if ($ou) $dnameParts[] = 'OU=' . $ou;
        if ($org) $dnameParts[] = 'O=' . $org;
        if ($loc) $dnameParts[] = 'L=' . $loc;
        if ($st) $dnameParts[] = 'ST=' . $st;
        if ($c) $dnameParts[] = 'C=' . $c;
        $dname = implode(', ', $dnameParts) ?: 'CN=Unknown';

        $storePassFile = secure_temp_secret($storePwd, 'appdown_storepass_');
        $keyPassFile = secure_temp_secret($keyPwd, 'appdown_keypass_');
        try {
            $cmd = sprintf(
                'keytool -genkeypair -v -storetype JKS -keystore %s -alias %s -keyalg RSA -keysize 2048 -validity %d -storepass:file %s -keypass:file %s -dname %s 2>&1',
                escapeshellarg($filepath), escapeshellarg($alias), $validity * 365,
                escapeshellarg($storePassFile), escapeshellarg($keyPassFile), escapeshellarg($dname)
            );
            $output = [];
            exec($cmd, $output, $retCode);
        } finally {
            @unlink($storePassFile);
            @unlink($keyPassFile);
        }

        if ($retCode !== 0 || !file_exists($filepath)) {
            @unlink($filepath);
            json_response(['error' => '生成失败: ' . redact_sensitive_text(implode("\n", array_slice($output, -12)), [$storePwd, $keyPwd])], 500);
        }
        @chmod($filepath, 0600);

        $fileUrl = 'uploads/keystores/' . $filename;
        $stmt = $pdo->prepare('INSERT INTO keystores (name, file_url, alias, store_password, key_password, validity_years, org_name, org_unit, country, state_name, locality, common_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $fileUrl, $alias, encrypt_secret($storePwd), encrypt_secret($keyPwd), $validity, $org, $ou, $c, $st, $loc, $cn]);
        json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }

    json_response(['error' => '无效的action'], 400);
}

if ($method === 'PUT') {
    $data = get_json_input();
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_response(['error' => '缺少id'], 400);

    $fields = [];
    $params = [];
    foreach (['name', 'alias', 'org_name', 'org_unit', 'country', 'state_name', 'locality', 'common_name'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = trim($data[$f]);
        }
    }
    if (!empty($data['store_password']) && $data['store_password'] !== '******') {
        if (strlen($data['store_password']) < 6) json_response(['error' => '密钥库密码至少6个字符'], 400);
        $fields[] = 'store_password = ?';
        $params[] = encrypt_secret($data['store_password']);
    }
    if (!empty($data['key_password']) && $data['key_password'] !== '******') {
        if (strlen($data['key_password']) < 6) json_response(['error' => '密钥密码至少6个字符'], 400);
        $fields[] = 'key_password = ?';
        $params[] = encrypt_secret($data['key_password']);
    }

    if (empty($fields)) json_response(['error' => '无更新字段'], 400);
    $fields[] = "updated_at = datetime('now')";
    $params[] = $id;
    $pdo->prepare('UPDATE keystores SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $data = get_json_input();
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_response(['error' => '缺少id'], 400);

    $count = $pdo->prepare('SELECT COUNT(*) FROM generated_apks WHERE keystore_id = ?');
    $count->execute([$id]);
    if ($count->fetchColumn() > 0) json_response(['error' => '该密钥已被生成的APK使用，无法删除'], 400);

    $ks = $pdo->prepare('SELECT file_url FROM keystores WHERE id = ?');
    $ks->execute([$id]);
    $row = $ks->fetch();
    if ($row) delete_upload($row['file_url']);

    $pdo->prepare('DELETE FROM keystores WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
