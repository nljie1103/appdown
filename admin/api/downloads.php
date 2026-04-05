<?php
/**
 * 下载按钮CRUD API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    $app_id = $_GET['app_id'] ?? 0;
    $stmt = $pdo->prepare('SELECT * FROM app_downloads WHERE app_id = ? ORDER BY sort_order');
    $stmt->execute([$app_id]);
    json_response($stmt->fetchAll());
}

csrf_validate();

if ($method === 'POST') {
    $data = get_json_input();
    $app_id = $data['app_id'] ?? 0;
    $max = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) as m FROM app_downloads WHERE app_id = ?');
    $max->execute([$app_id]);
    $order = (int)$max->fetch()['m'] + 1;

    $stmt = $pdo->prepare('INSERT INTO app_downloads (app_id, btn_type, btn_text, btn_subtext, href, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $app_id,
        $data['btn_type'] ?? 'android',
        trim($data['btn_text'] ?? ''),
        trim($data['btn_subtext'] ?? ''),
        trim($data['href'] ?? '#'),
        $order,
    ]);

    clear_config_cache();
    json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

if ($method === 'PUT') {
    $data = get_json_input();
    $id = $data['id'] ?? 0;
    $stmt = $pdo->prepare('UPDATE app_downloads SET btn_type=?, btn_text=?, btn_subtext=?, href=?, is_active=? WHERE id=?');
    $stmt->execute([
        $data['btn_type'] ?? 'android',
        trim($data['btn_text'] ?? ''),
        trim($data['btn_subtext'] ?? ''),
        trim($data['href'] ?? '#'),
        ($data['is_active'] ?? 1) ? 1 : 0,
        $id,
    ]);

    clear_config_cache();
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $data = get_json_input();
    $pdo->prepare('DELETE FROM app_downloads WHERE id = ?')->execute([$data['id'] ?? 0]);
    clear_config_cache();
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
