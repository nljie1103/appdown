<?php
/**
 * 分发首页模板 API
 */

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/landing_templates.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    $current = normalize_landing_template(get_setting($pdo, 'landing_template', 'classic'));
    json_response([
        'current' => $current,
        'templates' => landing_template_catalog(),
    ]);
}

if ($method === 'POST') {
    csrf_validate();
    $data = get_json_input();
    $template = (string)($data['template'] ?? '');
    $catalog = landing_template_catalog();
    if (!isset($catalog[$template])) {
        json_response(['error' => '未知的页面模板'], 400);
    }
    set_setting($pdo, 'landing_template', $template);
    clear_config_cache();
    json_response(['ok' => true, 'template' => $template]);
}

json_response(['error' => 'method not allowed'], 405);
