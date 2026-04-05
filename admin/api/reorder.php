<?php
/**
 * 通用排序API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();
csrf_validate();
require_method('POST');

$data = get_json_input();
$table = $data['table'] ?? '';
$order = $data['order'] ?? [];

$allowed = ['apps', 'app_downloads', 'app_images', 'feature_cards', 'friend_links'];
if (!in_array($table, $allowed, true)) {
    json_response(['error' => '不允许排序此表'], 400);
}

if (!is_array($order) || empty($order)) {
    json_response(['error' => '排序数据为空'], 400);
}

$pdo = get_db();
$stmt = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");

foreach ($order as $index => $id) {
    $stmt->execute([$index, (int)$id]);
}

clear_config_cache();
json_response(['ok' => true]);
