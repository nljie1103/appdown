<?php
/**
 * 附件管理API - 平台分类 CRUD
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    $appId = $_GET['app_id'] ?? 0;
    if (!$appId) json_response(['error' => '缺少 app_id'], 400);

    $platforms = $pdo->prepare('SELECT * FROM app_platforms WHERE app_id = ? ORDER BY sort_order ASC');
    $platforms->execute([$appId]);
    $result = $platforms->fetchAll();

    // 每个平台带上附件列表
    foreach ($result as &$p) {
        $att = $pdo->prepare('SELECT * FROM app_attachments WHERE platform_id = ? ORDER BY created_at DESC');
        $att->execute([$p['id']]);
        $p['files'] = $att->fetchAll();
    }
    unset($p);

    json_response($result);
}

csrf_validate();

if ($method === 'POST') {
    $data = get_json_input();
    $appId = $data['app_id'] ?? 0;
    $name = trim($data['name'] ?? '');
    if (!$appId || !$name) json_response(['error' => '应用ID和名称不能为空'], 400);

    $max = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) as m FROM app_platforms WHERE app_id = ?');
    $max->execute([$appId]);
    $order = $max->fetch()['m'] + 1;

    $stmt = $pdo->prepare('INSERT INTO app_platforms (app_id, name, sort_order) VALUES (?, ?, ?)');
    $stmt->execute([$appId, $name, $order]);
    json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

if ($method === 'PUT') {
    $data = get_json_input();
    $id = $data['id'] ?? 0;
    $name = trim($data['name'] ?? '');
    if (!$id || !$name) json_response(['error' => 'ID和名称不能为空'], 400);

    $pdo->prepare('UPDATE app_platforms SET name = ? WHERE id = ?')->execute([$name, $id]);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $data = get_json_input();
    $id = $data['id'] ?? 0;
    if (!$id) json_response(['error' => '缺少ID'], 400);

    // 删除该平台下所有附件文件
    $files = $pdo->prepare('SELECT file_url FROM app_attachments WHERE platform_id = ?');
    $files->execute([$id]);
    foreach ($files->fetchAll() as $f) {
        delete_upload($f['file_url']);
    }

    $pdo->prepare('DELETE FROM app_platforms WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
