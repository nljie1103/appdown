<?php
/**
 * Mobileconfig 管理 API — CRUD for generated mobileconfigs and certificates
 */

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/mobileconfig.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

// ===== GET =====
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
        $rows = $pdo->query("SELECT * FROM mc_certificates ORDER BY is_global DESC, created_at DESC")->fetchAll();
        foreach ($rows as &$r) {
            $r['cert'] = $r['cert'] ? '******' : '';
            $r['key'] = $r['key'] ? '******' : '';
            $r['chain'] = $r['chain'] ? '******' : '';
        }
        json_response($rows);
    }

    if ($action === 'get_cert') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少ID'], 400);
        $stmt = $pdo->prepare('SELECT * FROM mc_certificates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => '证书不存在'], 404);
        json_response($row);
    }

    json_response([]);
}

// ===== POST =====
if ($method === 'POST') {
    csrf_validate();
    $data = get_json_input();
    $action = $data['action'] ?? '';

    if ($action === 'generate') {
        $displayName = trim($data['display_name'] ?? '');
        $targetUrl = trim($data['target_url'] ?? '');
        if (!$displayName) json_response(['error' => '请输入显示名称'], 400);
        if (!$targetUrl) json_response(['error' => '请输入目标URL'], 400);

        $params = [
            'display_name' => $displayName,
            'target_url'   => $targetUrl,
            'bundle_id'    => trim($data['bundle_id'] ?? ''),
            'version'      => trim($data['version'] ?? '1'),
            'fullscreen'   => !empty($data['fullscreen']),
            'icon_data'    => $data['icon_data'] ?? '',
            'description'  => trim($data['description'] ?? ''),
            'payload_org'  => trim($data['payload_org'] ?? ''),
        ];

        // 加载证书
        $cert = null;
        $certId = $data['cert_id'] ?? null;
        if ($certId) {
            $stmt = $pdo->prepare('SELECT * FROM mc_certificates WHERE id = ?');
            $stmt->execute([(int)$certId]);
            $cert = $stmt->fetch() ?: null;
        }
        // 回退到全局证书
        if (!$cert) {
            $gc = $pdo->query('SELECT * FROM mc_certificates WHERE is_global = 1 LIMIT 1')->fetch();
            if ($gc) {
                $cert = $gc;
                $certId = $gc['id'];
            }
        }
        // 组织名回退
        if (empty($params['payload_org']) && $cert) {
            $params['payload_org'] = $cert['payload_org'] ?? '';
        }

        $destDir = __DIR__ . '/../../uploads/mobileconfigs';
        $result = generate_and_save_mobileconfig($params, $cert, $destDir);

        if (!$result['ok']) {
            json_response(['error' => $result['error']], 500);
        }

        $stmt = $pdo->prepare("INSERT INTO generated_mobileconfigs
            (display_name, target_url, bundle_id, version, fullscreen, icon_data, description, cert_id, payload_org, file_path, file_size, template)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $displayName, $targetUrl,
            $params['bundle_id'], $params['version'], $params['fullscreen'] ? 1 : 0,
            $params['icon_data'], $params['description'],
            $certId ?: null, $params['payload_org'],
            $result['file_path'], $result['file_size'],
            trim($data['template'] ?? 'modern'),
        ]);

        json_response(['ok' => true, 'id' => $pdo->lastInsertId(), 'file_path' => $result['file_path'], 'signed' => $result['signed'] ?? false]);
    }

    if ($action === 'create_cert') {
        $name = trim($data['name'] ?? '');
        $mode = trim($data['mode'] ?? 'text');
        if (!$name) json_response(['error' => '请输入证书名称'], 400);
        if (!in_array($mode, ['text', 'path', 'upload'])) json_response(['error' => '无效模式'], 400);

        $certRaw = trim($data['cert'] ?? '');
        $keyRaw = trim($data['key'] ?? '');

        // 验证证书有效性并提取信息
        $certIssuer = '';
        $certExpires = '';
        if (!empty($certRaw)) {
            $parsed = validate_and_parse_cert($mode, $certRaw, $keyRaw);
            if (!$parsed['valid']) {
                json_response(['error' => $parsed['error']], 400);
            }
            $certIssuer = $parsed['issuer'];
            $certExpires = $parsed['expires'];
        }

        $isGlobal = !empty($data['is_global']) ? 1 : 0;
        if ($isGlobal) {
            $pdo->exec("UPDATE mc_certificates SET is_global = 0 WHERE is_global = 1");
        }

        $stmt = $pdo->prepare('INSERT INTO mc_certificates (name, mode, cert, "key", chain, payload_org, is_global, cert_issuer, cert_expires) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $name, $mode,
            $certRaw, $keyRaw, trim($data['chain'] ?? ''),
            trim($data['payload_org'] ?? ''), $isGlobal,
            $certIssuer, $certExpires,
        ]);
        json_response(['ok' => true, 'id' => $pdo->lastInsertId(), 'cert_issuer' => $certIssuer, 'cert_expires' => $certExpires]);
    }

    if ($action === 'import_global_cert') {
        // 检查是否已导入
        $existing = $pdo->query("SELECT COUNT(*) FROM mc_certificates WHERE is_global = 1")->fetchColumn();
        if ($existing > 0) json_response(['error' => '已存在全局证书，请直接编辑'], 400);

        // 读取旧设置
        $rows = $pdo->query("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('mc_sign_cert','mc_sign_key','mc_sign_chain','mc_sign_mode','mc_payload_org')")->fetchAll();
        $s = [];
        foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_val'];

        if (empty($s['mc_sign_cert']) && empty($s['mc_sign_key'])) {
            json_response(['error' => '旧设置中无证书数据可导入'], 400);
        }

        $stmt = $pdo->prepare('INSERT INTO mc_certificates (name, mode, cert, "key", chain, payload_org, is_global) VALUES (?, ?, ?, ?, ?, ?, 1)');
        $stmt->execute([
            '全局证书（从设置导入）',
            $s['mc_sign_mode'] ?? 'text',
            $s['mc_sign_cert'] ?? '', $s['mc_sign_key'] ?? '', $s['mc_sign_chain'] ?? '',
            $s['mc_payload_org'] ?? '',
        ]);
        json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }

    json_response(['error' => '未知操作'], 400);
}

// ===== PUT =====
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

        // 合并参数
        $params = [
            'display_name' => trim($data['display_name'] ?? $old['display_name']),
            'target_url'   => trim($data['target_url'] ?? $old['target_url']),
            'bundle_id'    => trim($data['bundle_id'] ?? $old['bundle_id']),
            'version'      => trim($data['version'] ?? $old['version']),
            'fullscreen'   => isset($data['fullscreen']) ? !empty($data['fullscreen']) : (bool)$old['fullscreen'],
            'icon_data'    => $data['icon_data'] ?? $old['icon_data'],
            'description'  => trim($data['description'] ?? $old['description']),
            'payload_org'  => trim($data['payload_org'] ?? $old['payload_org']),
        ];

        $certId = isset($data['cert_id']) ? ($data['cert_id'] ?: null) : $old['cert_id'];
        $cert = null;
        if ($certId) {
            $cs = $pdo->prepare('SELECT * FROM mc_certificates WHERE id = ?');
            $cs->execute([(int)$certId]);
            $cert = $cs->fetch() ?: null;
        }
        if (!$cert) {
            $gc = $pdo->query('SELECT * FROM mc_certificates WHERE is_global = 1 LIMIT 1')->fetch();
            if ($gc) { $cert = $gc; }
        }
        if (empty($params['payload_org']) && $cert) {
            $params['payload_org'] = $cert['payload_org'] ?? '';
        }

        // 删旧文件
        if (!empty($old['file_path'])) {
            $oldPath = __DIR__ . '/../../' . $old['file_path'];
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        // 重新生成
        $destDir = __DIR__ . '/../../uploads/mobileconfigs';
        $result = generate_and_save_mobileconfig($params, $cert, $destDir);
        if (!$result['ok']) json_response(['error' => $result['error']], 500);

        $template = trim($data['template'] ?? $old['template']);

        $stmt = $pdo->prepare("UPDATE generated_mobileconfigs SET
            display_name=?, target_url=?, bundle_id=?, version=?, fullscreen=?,
            icon_data=?, description=?, cert_id=?, payload_org=?,
            file_path=?, file_size=?, template=?, updated_at=datetime('now')
            WHERE id=?");
        $stmt->execute([
            $params['display_name'], $params['target_url'], $params['bundle_id'],
            $params['version'], $params['fullscreen'] ? 1 : 0,
            $params['icon_data'], $params['description'],
            $certId, $params['payload_org'],
            $result['file_path'], $result['file_size'], $template,
            $id,
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

        $oldFullPath = __DIR__ . '/../../' . $old['file_path'];
        if (!file_exists($oldFullPath)) json_response(['error' => '原文件不存在'], 404);

        $safeName = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}_.-]/u', '_', $newName);
        $safeName = trim(preg_replace('/_+/', '_', $safeName), '_') ?: 'app';
        if (substr(strtolower($safeName), -13) !== '.mobileconfig') {
            $safeName .= '.mobileconfig';
        }

        $dir = dirname($oldFullPath);
        $newFullPath = $dir . '/' . $safeName;
        // 处理冲突
        if (file_exists($newFullPath) && $newFullPath !== $oldFullPath) {
            $base = pathinfo($safeName, PATHINFO_FILENAME);
            $safeName = $base . '_' . time() . '.mobileconfig';
            $newFullPath = $dir . '/' . $safeName;
        }

        if (!rename($oldFullPath, $newFullPath)) {
            json_response(['error' => '重命名失败'], 500);
        }

        $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/../..')) . '/';
        $newRelative = str_replace($projectRoot, '', str_replace('\\', '/', realpath($newFullPath)));

        $stmt = $pdo->prepare("UPDATE generated_mobileconfigs SET file_path = ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$newRelative, $id]);
        json_response(['ok' => true, 'new_path' => $newRelative]);
    }

    if ($action === 'associate') {
        $mcId = (int)($data['mc_id'] ?? 0);
        $appId = (int)($data['app_id'] ?? 0);
        $platformId = (int)($data['platform_id'] ?? 0);
        $version = trim($data['version'] ?? '1.0');
        if (!$mcId || !$appId || !$platformId) json_response(['error' => '请选择应用和附件分类'], 400);

        // 获取 mobileconfig 文件信息
        $mc = $pdo->prepare('SELECT * FROM generated_mobileconfigs WHERE id = ?');
        $mc->execute([$mcId]);
        $mcRow = $mc->fetch();
        if (!$mcRow) json_response(['error' => 'Mobileconfig 不存在'], 404);

        // 检测目标平台是否属于该应用
        $platCheck = $pdo->prepare('SELECT id FROM app_platforms WHERE id = ? AND app_id = ?');
        $platCheck->execute([$platformId, $appId]);
        if (!$platCheck->fetch()) json_response(['error' => '附件分类不属于该应用'], 400);

        // 计算文件大小
        $filePath = __DIR__ . '/../../' . $mcRow['file_path'];
        $bytes = file_exists($filePath) ? filesize($filePath) : 0;
        if ($bytes >= 1048576) { $fileSize = round($bytes / 1048576, 1) . ' MB'; }
        elseif ($bytes >= 1024) { $fileSize = round($bytes / 1024, 1) . ' KB'; }
        else { $fileSize = $bytes . ' B'; }

        // 添加到附件库
        $stmt = $pdo->prepare('INSERT INTO app_attachments (app_id, platform_id, version, file_url, file_size, changelog) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$appId, $platformId, $version, $mcRow['file_path'], $fileSize, $mcRow['display_name']]);

        // 更新 mobileconfig 关联记录
        $pdo->prepare("UPDATE generated_mobileconfigs SET app_id = ?, updated_at = datetime('now') WHERE id = ?")->execute([$appId, $mcId]);

        json_response(['ok' => true]);
    }

    if ($action === 'update_cert') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少ID'], 400);

        $stmt = $pdo->prepare('SELECT * FROM mc_certificates WHERE id = ?');
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if (!$old) json_response(['error' => '证书不存在'], 404);

        $name = trim($data['name'] ?? $old['name']);
        $mode = trim($data['mode'] ?? $old['mode']);
        $isGlobal = isset($data['is_global']) ? (!empty($data['is_global']) ? 1 : 0) : (int)$old['is_global'];

        $certVal = isset($data['cert']) ? trim($data['cert']) : $old['cert'];
        $keyVal = isset($data['key']) ? trim($data['key']) : $old['key'];
        $chainVal = isset($data['chain']) ? trim($data['chain']) : $old['chain'];

        // 如果证书内容有变更，重新验证并提取信息
        $certIssuer = $old['cert_issuer'] ?? '';
        $certExpires = $old['cert_expires'] ?? '';
        if (isset($data['cert']) && !empty($certVal)) {
            $parsed = validate_and_parse_cert($mode, $certVal, $keyVal);
            if (!$parsed['valid']) {
                json_response(['error' => $parsed['error']], 400);
            }
            $certIssuer = $parsed['issuer'];
            $certExpires = $parsed['expires'];
        }

        if ($isGlobal && !(int)$old['is_global']) {
            $pdo->exec("UPDATE mc_certificates SET is_global = 0 WHERE is_global = 1");
        }

        $stmt = $pdo->prepare('UPDATE mc_certificates SET name=?, mode=?, cert=?, "key"=?, chain=?, payload_org=?, is_global=?, cert_issuer=?, cert_expires=?, updated_at=datetime(\'now\') WHERE id=?');
        $stmt->execute([
            $name, $mode,
            $certVal, $keyVal, $chainVal,
            trim($data['payload_org'] ?? $old['payload_org']),
            $isGlobal, $certIssuer, $certExpires, $id,
        ]);
        json_response(['ok' => true, 'cert_issuer' => $certIssuer, 'cert_expires' => $certExpires]);
    }

    json_response(['error' => '未知操作'], 400);
}

// ===== DELETE =====
if ($method === 'DELETE') {
    csrf_validate();
    $data = get_json_input();
    $action = $data['action'] ?? '';

    if ($action === 'delete_cert') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少ID'], 400);

        $refs = $pdo->prepare('SELECT COUNT(*) FROM generated_mobileconfigs WHERE cert_id = ?');
        $refs->execute([$id]);
        if ($refs->fetchColumn() > 0) {
            json_response(['error' => '该证书被已生成的Mobileconfig引用，无法删除。请先修改引用后再删除。'], 400);
        }

        $pdo->prepare('DELETE FROM mc_certificates WHERE id = ?')->execute([$id]);
        json_response(['ok' => true]);
    }

    // 默认：删除 mobileconfig 文件
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_response(['error' => '缺少ID'], 400);

    $stmt = $pdo->prepare('SELECT * FROM generated_mobileconfigs WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => '记录不存在'], 404);

    // 删除磁盘文件
    if (!empty($row['file_path'])) {
        $fullPath = __DIR__ . '/../../' . $row['file_path'];
        if (file_exists($fullPath)) @unlink($fullPath);
    }

    // 清除 apps 关联
    $pdo->prepare("UPDATE apps SET mc_file_id = NULL WHERE mc_file_id = ?")->execute([$id]);

    // 删除记录
    $pdo->prepare('DELETE FROM generated_mobileconfigs WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
