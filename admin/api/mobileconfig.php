<?php
/**
 * Mobileconfig 管理 API — CRUD for generated mobileconfigs and certificates
 */

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/mobileconfig.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

function mc_get_cert(PDO $pdo, ?int $id = null): ?array {
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM mc_certificates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: null;
        if ($row && !empty($row['key'])) {
            $rawKey = (string)$row['key'];
            $row['key'] = decrypt_secret($rawKey);
            if (!is_encrypted_secret($rawKey)) {
                try {
                    $pdo->prepare("UPDATE mc_certificates SET \"key\"=?, updated_at=datetime('now') WHERE id=?")
                        ->execute([encrypt_secret($row['key']), $row['id']]);
                } catch (Throwable $e) {}
            }
        }
        return $row;
    }
    $row = $pdo->query('SELECT * FROM mc_certificates WHERE is_global = 1 LIMIT 1')->fetch() ?: null;
    if ($row && !empty($row['key'])) {
        $rawKey = (string)$row['key'];
        $row['key'] = decrypt_secret($rawKey);
        if (!is_encrypted_secret($rawKey)) {
            try {
                $pdo->prepare("UPDATE mc_certificates SET \"key\"=?, updated_at=datetime('now') WHERE id=?")
                    ->execute([encrypt_secret($row['key']), $row['id']]);
            } catch (Throwable $e) {}
        }
    }
    return $row;
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $rows = $pdo->query("
            SELECT m.*, c.name as cert_name, a.name as linked_app_name
            FROM generated_mobileconfigs m
            LEFT JOIN mc_certificates c ON m.cert_id = c.id
            LEFT JOIN apps a ON m.app_id = a.id
            ORDER BY m.id DESC
        ")->fetchAll();
        json_response($rows);
    }

    if ($action === 'list_certs') {
        $rows = $pdo->query('SELECT * FROM mc_certificates ORDER BY is_global DESC, created_at DESC')->fetchAll();
        foreach ($rows as &$r) {
            $r['has_cert'] = !empty($r['cert']);
            $r['has_key'] = !empty($r['key']);
            $r['has_chain'] = !empty($r['chain']);
            $r['cert'] = $r['cert'] ? '******' : '';
            $r['key'] = $r['key'] ? '******' : '';
            $r['chain'] = $r['chain'] ? '******' : '';
        }
        json_response($rows);
    }

    if ($action === 'get_cert') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少ID'], 400);
        $row = mc_get_cert($pdo, $id);
        if (!$row) json_response(['error' => '证书不存在'], 404);

        // 私钥永不回传给浏览器。公钥证书/链可继续回显以兼容编辑 UI。
        $row['has_key'] = !empty($row['key']);
        $row['key'] = '';
        json_response($row);
    }

    json_response([]);
}

if ($method === 'POST') {
    csrf_validate();
    $data = get_json_input();
    $action = $data['action'] ?? '';

    if ($action === 'generate') {
        $displayName = trim($data['display_name'] ?? '');
        $targetUrl = trim($data['target_url'] ?? '');
        if (!$displayName) json_response(['error' => '请输入显示名称'], 400);
        if (!$targetUrl || !filter_var($targetUrl, FILTER_VALIDATE_URL)) json_response(['error' => '请输入有效的目标URL'], 400);

        $params = [
            'display_name' => $displayName,
            'target_url' => $targetUrl,
            'bundle_id' => trim($data['bundle_id'] ?? ''),
            'version' => trim($data['version'] ?? '1'),
            'fullscreen' => !empty($data['fullscreen']),
            'icon_data' => $data['icon_data'] ?? '',
            'description' => trim($data['description'] ?? ''),
            'payload_org' => trim($data['payload_org'] ?? ''),
        ];

        $certId = !empty($data['cert_id']) ? (int)$data['cert_id'] : null;
        $cert = $certId ? mc_get_cert($pdo, $certId) : null;
        if (!$cert) {
            $cert = mc_get_cert($pdo, null);
            if ($cert) $certId = (int)$cert['id'];
        }
        if (empty($params['payload_org']) && $cert) $params['payload_org'] = $cert['payload_org'] ?? '';

        $destDir = __DIR__ . '/../../uploads/mobileconfigs';
        $result = generate_and_save_mobileconfig($params, $cert, $destDir);
        if (!$result['ok']) json_response(['error' => $result['error']], 500);

        $stmt = $pdo->prepare("INSERT INTO generated_mobileconfigs
            (display_name, target_url, bundle_id, version, fullscreen, icon_data, description, cert_id, payload_org, file_path, file_size, template)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $displayName, $targetUrl, $params['bundle_id'], $params['version'], $params['fullscreen'] ? 1 : 0,
            $params['icon_data'], $params['description'], $certId ?: null, $params['payload_org'],
            $result['file_path'], $result['file_size'], trim($data['template'] ?? 'modern'),
        ]);
        json_response(['ok' => true, 'id' => $pdo->lastInsertId(), 'file_path' => $result['file_path'], 'signed' => $result['signed'] ?? false]);
    }

    if ($action === 'create_cert') {
        $name = trim($data['name'] ?? '');
        $mode = trim($data['mode'] ?? 'text');
        if (!$name) json_response(['error' => '请输入证书名称'], 400);
        if (!in_array($mode, ['text', 'path', 'upload'], true)) json_response(['error' => '无效模式'], 400);

        $certRaw = trim($data['cert'] ?? '');
        $keyRaw = trim($data['key'] ?? '');
        $chainRaw = trim($data['chain'] ?? '');
        $certIssuer = '';
        $certExpires = '';
        if ($certRaw !== '') {
            $parsed = validate_and_parse_cert($mode, $certRaw, $keyRaw);
            if (!$parsed['valid']) json_response(['error' => $parsed['error']], 400);
            $certIssuer = $parsed['issuer'];
            $certExpires = $parsed['expires'];
        }

        $isGlobal = !empty($data['is_global']) ? 1 : 0;
        if ($isGlobal) $pdo->exec('UPDATE mc_certificates SET is_global = 0 WHERE is_global = 1');

        $stmt = $pdo->prepare('INSERT INTO mc_certificates (name, mode, cert, "key", chain, payload_org, is_global, cert_issuer, cert_expires) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $mode, $certRaw, encrypt_secret($keyRaw), $chainRaw, trim($data['payload_org'] ?? ''), $isGlobal, $certIssuer, $certExpires]);
        json_response(['ok' => true, 'id' => $pdo->lastInsertId(), 'cert_issuer' => $certIssuer, 'cert_expires' => $certExpires]);
    }

    if ($action === 'import_global_cert') {
        $existing = $pdo->query('SELECT COUNT(*) FROM mc_certificates WHERE is_global = 1')->fetchColumn();
        if ($existing > 0) json_response(['error' => '已存在全局证书，请直接编辑'], 400);
        $rows = $pdo->query("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('mc_sign_cert','mc_sign_key','mc_sign_chain','mc_sign_mode','mc_payload_org')")->fetchAll();
        $s = [];
        foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_val'];
        if (empty($s['mc_sign_cert']) && empty($s['mc_sign_key'])) json_response(['error' => '旧设置中无证书数据可导入'], 400);
        $stmt = $pdo->prepare('INSERT INTO mc_certificates (name, mode, cert, "key", chain, payload_org, is_global) VALUES (?, ?, ?, ?, ?, ?, 1)');
        $stmt->execute(['全局证书（从设置导入）', $s['mc_sign_mode'] ?? 'text', $s['mc_sign_cert'] ?? '', encrypt_secret((string)($s['mc_sign_key'] ?? '')), $s['mc_sign_chain'] ?? '', $s['mc_payload_org'] ?? '']);
        json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }

    json_response(['error' => '未知操作'], 400);
}

if ($method === 'PUT') {
    csrf_validate();
    $data = get_json_input();
    $action = $data['action'] ?? 'update';

    if ($action === 'update') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少ID'], 400);
        $stmt = $pdo->prepare('SELECT * FROM generated_mobileconfigs WHERE id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) json_response(['error' => '记录不存在'], 404);

        $params = [
            'display_name' => trim($data['display_name'] ?? $old['display_name']),
            'target_url' => trim($data['target_url'] ?? $old['target_url']),
            'bundle_id' => trim($data['bundle_id'] ?? $old['bundle_id']),
            'version' => trim($data['version'] ?? $old['version']),
            'fullscreen' => isset($data['fullscreen']) ? !empty($data['fullscreen']) : (bool)$old['fullscreen'],
            'icon_data' => $data['icon_data'] ?? $old['icon_data'],
            'description' => trim($data['description'] ?? $old['description']),
            'payload_org' => trim($data['payload_org'] ?? $old['payload_org']),
        ];
        if (!filter_var($params['target_url'], FILTER_VALIDATE_URL)) json_response(['error' => '目标URL无效'], 400);

        $certId = isset($data['cert_id']) ? ($data['cert_id'] ? (int)$data['cert_id'] : null) : $old['cert_id'];
        $cert = $certId ? mc_get_cert($pdo, (int)$certId) : null;
        if (!$cert) $cert = mc_get_cert($pdo, null);
        if (empty($params['payload_org']) && $cert) $params['payload_org'] = $cert['payload_org'] ?? '';

        $destDir = __DIR__ . '/../../uploads/mobileconfigs';
        $result = generate_and_save_mobileconfig($params, $cert, $destDir);
        if (!$result['ok']) json_response(['error' => $result['error']], 500);

        // 先成功生成新文件，再删除旧文件，避免失败后丢失可用版本。
        if (!empty($old['file_path']) && $old['file_path'] !== $result['file_path']) {
            $oldPath = __DIR__ . '/../../' . $old['file_path'];
            if (is_file($oldPath)) @unlink($oldPath);
        }

        $stmt = $pdo->prepare("UPDATE generated_mobileconfigs SET display_name=?, target_url=?, bundle_id=?, version=?, fullscreen=?, icon_data=?, description=?, cert_id=?, payload_org=?, file_path=?, file_size=?, template=?, updated_at=datetime('now') WHERE id=?");
        $stmt->execute([
            $params['display_name'], $params['target_url'], $params['bundle_id'], $params['version'], $params['fullscreen'] ? 1 : 0,
            $params['icon_data'], $params['description'], $certId, $params['payload_org'], $result['file_path'], $result['file_size'],
            trim($data['template'] ?? $old['template']), $id,
        ]);
        json_response(['ok' => true, 'file_path' => $result['file_path']]);
    }

    if ($action === 'rename') {
        $id = (int)($data['id'] ?? 0);
        $newName = trim($data['new_name'] ?? '');
        if (!$id || !$newName) json_response(['error' => '缺少参数'], 400);
        $stmt = $pdo->prepare('SELECT file_path FROM generated_mobileconfigs WHERE id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) json_response(['error' => '记录不存在'], 404);
        $oldFullPath = realpath(__DIR__ . '/../../' . $old['file_path']);
        $mcRoot = realpath(__DIR__ . '/../../uploads/mobileconfigs');
        if (!$oldFullPath || !$mcRoot || !str_starts_with($oldFullPath, $mcRoot . DIRECTORY_SEPARATOR)) json_response(['error' => '原文件路径不合法'], 400);

        $safeName = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}_.-]/u', '_', $newName);
        $safeName = trim(preg_replace('/_+/', '_', $safeName), '_') ?: 'app';
        if (!str_ends_with(strtolower($safeName), '.mobileconfig')) $safeName .= '.mobileconfig';
        $newFullPath = dirname($oldFullPath) . '/' . $safeName;
        if (file_exists($newFullPath) && $newFullPath !== $oldFullPath) $newFullPath = dirname($oldFullPath) . '/' . pathinfo($safeName, PATHINFO_FILENAME) . '_' . time() . '.mobileconfig';
        if (!rename($oldFullPath, $newFullPath)) json_response(['error' => '重命名失败'], 500);
        $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/../..')) . '/';
        $newRelative = str_replace($projectRoot, '', str_replace('\\', '/', realpath($newFullPath)));
        $pdo->prepare("UPDATE generated_mobileconfigs SET file_path=?, updated_at=datetime('now') WHERE id=?")->execute([$newRelative, $id]);
        json_response(['ok' => true, 'new_path' => $newRelative]);
    }

    if ($action === 'associate') {
        $mcId = (int)($data['mc_id'] ?? 0);
        $appId = (int)($data['app_id'] ?? 0);
        $platformId = (int)($data['platform_id'] ?? 0);
        $version = trim($data['version'] ?? '1.0');
        if (!$mcId || !$appId || !$platformId) json_response(['error' => '请选择应用和附件分类'], 400);
        $mc = $pdo->prepare('SELECT * FROM generated_mobileconfigs WHERE id = ?');
        $mc->execute([$mcId]);
        $mcRow = $mc->fetch();
        if (!$mcRow) json_response(['error' => 'Mobileconfig 不存在'], 404);
        $platCheck = $pdo->prepare('SELECT id FROM app_platforms WHERE id = ? AND app_id = ?');
        $platCheck->execute([$platformId, $appId]);
        if (!$platCheck->fetch()) json_response(['error' => '附件分类不属于该应用'], 400);
        $filePath = __DIR__ . '/../../' . $mcRow['file_path'];
        $bytes = is_file($filePath) ? filesize($filePath) : 0;
        $fileSize = $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : ($bytes >= 1024 ? round($bytes / 1024, 1) . ' KB' : $bytes . ' B');
        $existStmt = $pdo->prepare('SELECT id FROM app_attachments WHERE file_url = ? AND platform_id = ?');
        $existStmt->execute([$mcRow['file_path'], $platformId]);
        $existRow = $existStmt->fetch();
        if ($existRow) {
            $pdo->prepare('UPDATE app_attachments SET version=?, file_size=?, changelog=? WHERE id=?')->execute([$version, $fileSize, $mcRow['display_name'], $existRow['id']]);
        } else {
            $pdo->prepare('INSERT INTO app_attachments (app_id, platform_id, version, file_url, file_size, changelog) VALUES (?, ?, ?, ?, ?, ?)')->execute([$appId, $platformId, $version, $mcRow['file_path'], $fileSize, $mcRow['display_name']]);
        }
        $pdo->prepare("UPDATE generated_mobileconfigs SET app_id=?, updated_at=datetime('now') WHERE id=?")->execute([$appId, $mcId]);
        json_response(['ok' => true]);
    }

    if ($action === 'update_cert') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少ID'], 400);
        $old = mc_get_cert($pdo, $id);
        if (!$old) json_response(['error' => '证书不存在'], 404);

        $name = trim($data['name'] ?? $old['name']);
        $mode = trim($data['mode'] ?? $old['mode']);
        if (!in_array($mode, ['text', 'path', 'upload'], true)) json_response(['error' => '无效模式'], 400);
        $isGlobal = isset($data['is_global']) ? (!empty($data['is_global']) ? 1 : 0) : (int)$old['is_global'];

        // 浏览器永远拿不到旧私钥，因此空值代表“保留原值”，而不是清空。
        $certInput = isset($data['cert']) ? trim((string)$data['cert']) : '';
        $keyInput = isset($data['key']) ? trim((string)$data['key']) : '';
        $chainInput = isset($data['chain']) ? trim((string)$data['chain']) : '';
        $certVal = $certInput !== '' ? $certInput : $old['cert'];
        $keyVal = $keyInput !== '' ? $keyInput : $old['key'];
        $chainVal = $chainInput !== '' ? $chainInput : $old['chain'];

        $certIssuer = $old['cert_issuer'] ?? '';
        $certExpires = $old['cert_expires'] ?? '';
        if ($certVal !== '' && ($certInput !== '' || $keyInput !== '' || $mode !== $old['mode'])) {
            $parsed = validate_and_parse_cert($mode, $certVal, $keyVal);
            if (!$parsed['valid']) json_response(['error' => $parsed['error']], 400);
            $certIssuer = $parsed['issuer'];
            $certExpires = $parsed['expires'];
        }

        if ($isGlobal && !(int)$old['is_global']) $pdo->exec('UPDATE mc_certificates SET is_global = 0 WHERE is_global = 1');
        $stmt = $pdo->prepare('UPDATE mc_certificates SET name=?, mode=?, cert=?, "key"=?, chain=?, payload_org=?, is_global=?, cert_issuer=?, cert_expires=?, updated_at=datetime(\'now\') WHERE id=?');
        $stmt->execute([$name, $mode, $certVal, encrypt_secret($keyVal), $chainVal, trim($data['payload_org'] ?? $old['payload_org']), $isGlobal, $certIssuer, $certExpires, $id]);
        json_response(['ok' => true, 'cert_issuer' => $certIssuer, 'cert_expires' => $certExpires]);
    }

    json_response(['error' => '未知操作'], 400);
}

if ($method === 'DELETE') {
    csrf_validate();
    $data = get_json_input();
    $action = $data['action'] ?? '';

    if ($action === 'delete_cert') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少ID'], 400);
        $refs = $pdo->prepare('SELECT COUNT(*) FROM generated_mobileconfigs WHERE cert_id = ?');
        $refs->execute([$id]);
        if ($refs->fetchColumn() > 0) json_response(['error' => '该证书被已生成的Mobileconfig引用，无法删除。请先修改引用后再删除。'], 400);
        $pdo->prepare('DELETE FROM mc_certificates WHERE id = ?')->execute([$id]);
        json_response(['ok' => true]);
    }

    $id = (int)($data['id'] ?? 0);
    if (!$id) json_response(['error' => '缺少ID'], 400);
    $stmt = $pdo->prepare('SELECT * FROM generated_mobileconfigs WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => '记录不存在'], 404);
    if (!empty($row['file_path'])) {
        $fullPath = realpath(__DIR__ . '/../../' . $row['file_path']);
        $mcRoot = realpath(__DIR__ . '/../../uploads/mobileconfigs');
        if ($fullPath && $mcRoot && str_starts_with($fullPath, $mcRoot . DIRECTORY_SEPARATOR) && is_file($fullPath)) @unlink($fullPath);
    }
    $pdo->prepare('UPDATE apps SET mc_file_id = NULL WHERE mc_file_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM generated_mobileconfigs WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
