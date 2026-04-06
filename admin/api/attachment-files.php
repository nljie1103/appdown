<?php
/**
 * 附件文件API - 上传/删除
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

csrf_validate();

if ($method === 'POST') {
    // 文件上传 (multipart/form-data)
    $appId = $_POST['app_id'] ?? 0;
    $platformId = $_POST['platform_id'] ?? 0;
    $version = trim($_POST['version'] ?? '');
    $changelog = trim($_POST['changelog'] ?? '');

    if (!$appId || !$platformId || !$version) {
        json_response(['error' => '应用ID、平台ID、版本号不能为空'], 400);
    }

    // 获取应用名称用于文件命名
    $appStmt = $pdo->prepare('SELECT name FROM apps WHERE id = ?');
    $appStmt->execute([$appId]);
    $appName = $appStmt->fetchColumn() ?: 'app';
    $customName = $appName . '-' . $version;

    $upload = handle_upload('file', 'app', $customName);
    if (!$upload['ok']) {
        json_response(['error' => $upload['error']], 400);
    }

    // 计算文件大小
    $filePath = __DIR__ . '/../../' . $upload['url'];
    $bytes = file_exists($filePath) ? filesize($filePath) : 0;
    if ($bytes >= 1073741824) {
        $fileSize = round($bytes / 1073741824, 1) . ' GB';
    } elseif ($bytes >= 1048576) {
        $fileSize = round($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        $fileSize = round($bytes / 1024, 1) . ' KB';
    } else {
        $fileSize = $bytes . ' B';
    }

    $stmt = $pdo->prepare('INSERT INTO app_attachments (app_id, platform_id, version, file_url, file_size, changelog) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$appId, $platformId, $version, $upload['url'], $fileSize, $changelog]);

    json_response(['ok' => true, 'id' => $pdo->lastInsertId(), 'url' => $upload['url'], 'file_size' => $fileSize]);
}

if ($method === 'PUT') {
    $data = get_json_input();
    $id = (int)($data['id'] ?? 0);
    $version = trim($data['version'] ?? '');
    $changelog = trim($data['changelog'] ?? '');

    if (!$id || $version === '') {
        json_response(['error' => 'ID和版本号不能为空'], 400);
    }

    $stmt = $pdo->prepare('UPDATE app_attachments SET version = ?, changelog = ? WHERE id = ?');
    $stmt->execute([$version, $changelog, $id]);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $data = get_json_input();
    $id = $data['id'] ?? 0;
    if (!$id) json_response(['error' => '缺少ID'], 400);

    $row = $pdo->prepare('SELECT file_url FROM app_attachments WHERE id = ?');
    $row->execute([$id]);
    $att = $row->fetch();
    if ($att) {
        delete_upload($att['file_url']);
    }

    $pdo->prepare('DELETE FROM app_attachments WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
