<?php
/**
 * 特色卡片CRUD API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    json_response($pdo->query('SELECT * FROM feature_cards ORDER BY sort_order ASC')->fetchAll());
}

csrf_validate();

if ($method === 'POST') {
    $data = get_json_input();
    $max = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM feature_cards')->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO feature_cards (title, description, icon, sort_order) VALUES (?, ?, ?, ?)');
    $stmt->execute([trim($data['title'] ?? ''), trim($data['description'] ?? ''), trim($data['icon'] ?? ''), $max + 1]);
    clear_config_cache();
    json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

if ($method === 'PUT') {
    $data = get_json_input();
    $stmt = $pdo->prepare('UPDATE feature_cards SET title=?, description=?, icon=?, is_active=? WHERE id=?');
    $stmt->execute([trim($data['title'] ?? ''), trim($data['description'] ?? ''), trim($data['icon'] ?? ''), ($data['is_active'] ?? 1) ? 1 : 0, $data['id'] ?? 0]);
    clear_config_cache();
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $pdo->prepare('DELETE FROM feature_cards WHERE id = ?')->execute([get_json_input()['id'] ?? 0]);
    clear_config_cache();
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
