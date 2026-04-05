<?php
/**
 * 站点设置API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    $rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_val'];
    }
    json_response($settings);
}

if ($method === 'POST') {
    csrf_validate();
    $data = get_json_input();
    $settings = $data['settings'] ?? [];

    if (empty($settings) || !is_array($settings)) {
        json_response(['error' => '无效数据'], 400);
    }

    $allowed = ['site_title','site_heading','logo_url','favicon_url','notice_text','notice_enabled',
                'copyright','carousel_interval','stats_downloads','stats_rating','stats_daily_active',
                'font_url','font_family','captcha_enabled',
                'bg_type','bg_color','bg_gradient','bg_image',
                'effects_config'];

    foreach ($settings as $key => $val) {
        if (in_array($key, $allowed, true)) {
            set_setting($pdo, $key, (string)$val);
        }
    }

    clear_config_cache();
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
