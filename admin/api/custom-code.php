<?php
/**
 * 自定义代码API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    $rows = $pdo->query('SELECT position, code FROM custom_code')->fetchAll();
    $result = [];
    foreach ($rows as $r) {
        $result[$r['position']] = $r['code'];
    }
    json_response($result);
}

if ($method === 'POST') {
    csrf_validate();
    $data = get_json_input();
    $position = $data['position'] ?? '';
    $code = $data['code'] ?? '';

    $allowed = ['head_css', 'head_js', 'footer_css', 'footer_js'];
    if (!in_array($position, $allowed, true)) {
        json_response(['error' => '无效位置'], 400);
    }

    $stmt = $pdo->prepare("UPDATE custom_code SET code = ?, updated_at = datetime('now') WHERE position = ?");
    $stmt->execute([$code, $position]);

    clear_config_cache();
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
