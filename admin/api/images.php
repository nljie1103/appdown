<?php
/**
 * 轮播图CRUD API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    $app_id = $_GET['app_id'] ?? 0;
    $stmt = $pdo->prepare('SELECT * FROM app_images WHERE app_id = ? ORDER BY sort_order');
    $stmt->execute([$app_id]);
    json_response($stmt->fetchAll());
}

csrf_validate();

if ($method === 'POST') {
    $data = get_json_input();
    $app_id = $data['app_id'] ?? 0;
    $max = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) as m FROM app_images WHERE app_id = ?');
    $max->execute([$app_id]);
    $order = (int)$max->fetch()['m'] + 1;

    $stmt = $pdo->prepare('INSERT INTO app_images (app_id, image_url, alt_text, sort_order) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $app_id,
        trim($data['image_url'] ?? ''),
        trim($data['alt_text'] ?? ''),
        $order,
    ]);

    clear_config_cache();
    json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

if ($method === 'PUT') {
    $data = get_json_input();
    $stmt = $pdo->prepare('UPDATE app_images SET image_url=?, alt_text=? WHERE id=?');
    $stmt->execute([
        trim($data['image_url'] ?? ''),
        trim($data['alt_text'] ?? ''),
        $data['id'] ?? 0,
    ]);
    clear_config_cache();
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $data = get_json_input();
    $id = $data['id'] ?? 0;
    // 获取图片URL，如果是上传的则删除文件
    $stmt = $pdo->prepare('SELECT image_url FROM app_images WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        delete_upload($row['image_url']);
    }
    $pdo->prepare('DELETE FROM app_images WHERE id = ?')->execute([$id]);
    clear_config_cache();
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
