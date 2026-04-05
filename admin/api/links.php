<?php
/**
 * 友情链接CRUD API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    json_response($pdo->query('SELECT * FROM friend_links ORDER BY sort_order ASC')->fetchAll());
}

csrf_validate();

if ($method === 'POST') {
    $data = get_json_input();
    $max = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM friend_links')->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO friend_links (name, url, icon, icon_url, show_icon, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        trim($data['name'] ?? ''),
        trim($data['url'] ?? '#'),
        trim($data['icon'] ?? ''),
        trim($data['icon_url'] ?? ''),
        ($data['show_icon'] ?? 0) ? 1 : 0,
        $max + 1
    ]);
    clear_config_cache();
    json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

if ($method === 'PUT') {
    $data = get_json_input();
    $stmt = $pdo->prepare('UPDATE friend_links SET name=?, url=?, icon=?, icon_url=?, show_icon=?, is_active=? WHERE id=?');
    $stmt->execute([
        trim($data['name'] ?? ''),
        trim($data['url'] ?? '#'),
        trim($data['icon'] ?? ''),
        trim($data['icon_url'] ?? ''),
        ($data['show_icon'] ?? 0) ? 1 : 0,
        ($data['is_active'] ?? 1) ? 1 : 0,
        $data['id'] ?? 0
    ]);
    clear_config_cache();
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $pdo->prepare('DELETE FROM friend_links WHERE id = ?')->execute([get_json_input()['id'] ?? 0]);
    clear_config_cache();
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
