<?php
/**
 * 公共API: 返回完整站点配置JSON
 * GET /api/config.php
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/landing_templates.php';
require_method('GET');

$lock = __DIR__ . '/../install/install.lock';
$db_file = __DIR__ . '/../data/app.db';
if (!file_exists($lock) || !file_exists($db_file)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'not_installed']);
    exit;
}

$cache_path = __DIR__ . '/../data/config_cache.json';
if (file_exists($cache_path) && (time() - filemtime($cache_path)) < 300) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60');
    readfile($cache_path);
    exit;
}

$pdo = get_db();
$settings = [];
foreach ($pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_val'];
}

// 一次取出应用，再批量读取下载、截图和应用特色，避免每个应用 2~3 次查询的 N+1。
$apps = $pdo->query('SELECT id, slug, name, icon, icon_url, theme_color, feature_category_id FROM apps WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
$appIds = array_map(static fn(array $app): int => (int)$app['id'], $apps);
$downloadsByApp = [];
$imagesByApp = [];
$featuresByCategory = [];

if ($appIds) {
    $marks = implode(',', array_fill(0, count($appIds), '?'));

    $stmt = $pdo->prepare("SELECT app_id, btn_type, btn_icon, btn_text, btn_subtext, href FROM app_downloads WHERE is_active = 1 AND app_id IN ($marks) ORDER BY app_id, sort_order ASC");
    $stmt->execute($appIds);
    foreach ($stmt->fetchAll() as $row) {
        $appId = (int)$row['app_id'];
        unset($row['app_id']);
        $downloadsByApp[$appId][] = $row;
    }

    $stmt = $pdo->prepare("SELECT app_id, image_url, alt_text FROM app_images WHERE app_id IN ($marks) ORDER BY app_id, sort_order ASC");
    $stmt->execute($appIds);
    foreach ($stmt->fetchAll() as $row) {
        $appId = (int)$row['app_id'];
        unset($row['app_id']);
        $imagesByApp[$appId][] = $row;
    }
}

$categoryIds = [];
foreach ($apps as $app) {
    $categoryId = (int)($app['feature_category_id'] ?? 0);
    if ($categoryId > 0) $categoryIds[$categoryId] = $categoryId;
}
if ($categoryIds) {
    $ids = array_values($categoryIds);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT category_id, title, description, icon, icon_url FROM feature_cards WHERE is_active = 1 AND category_id IN ($marks) ORDER BY category_id, sort_order ASC");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $categoryId = (int)$row['category_id'];
        unset($row['category_id']);
        $featuresByCategory[$categoryId][] = $row;
    }
}

foreach ($apps as &$app) {
    $appId = (int)$app['id'];
    $categoryId = (int)($app['feature_category_id'] ?? 0);
    $app['downloads'] = $downloadsByApp[$appId] ?? [];
    $app['images'] = $imagesByApp[$appId] ?? [];
    $app['features'] = $categoryId > 0 ? ($featuresByCategory[$categoryId] ?? []) : [];
    $app['id'] = $app['slug'];
    unset($app['slug'], $app['feature_category_id']);
}
unset($app);

$features = $pdo->query('SELECT title, description, icon, icon_url FROM feature_cards WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
$links = $pdo->query('SELECT name, url, icon, icon_url, show_icon FROM friend_links WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

$custom = [];
foreach ($pdo->query('SELECT position, code FROM custom_code')->fetchAll() as $row) {
    $custom[$row['position']] = $row['code'];
}

$landingTemplate = normalize_landing_template($settings['landing_template'] ?? 'classic');
$templateCss = landing_template_css($landingTemplate);
if ($templateCss !== '') {
    // 内置模板 token 先注入，用户自己的 CSS 保持后置，仍拥有最终覆盖权。
    $custom['head_css'] = $templateCss . "\n" . ($custom['head_css'] ?? '');
}

$config = [
    'site' => [
        'title'             => $settings['site_title'] ?? '',
        'heading'           => $settings['site_heading'] ?? '',
        'logo_url'          => $settings['logo_url'] ?? '',
        'favicon_url'       => $settings['favicon_url'] ?? '',
        'notice_text'       => $settings['notice_text'] ?? '',
        'notice_enabled'    => (bool)($settings['notice_enabled'] ?? true),
        'copyright'         => $settings['copyright'] ?? '',
        'carousel_interval' => (int)($settings['carousel_interval'] ?? 4000),
        'landing_template'  => $landingTemplate,
        'landing_layout'    => landing_template_layout($landingTemplate),
        'stats' => [
            'downloads'    => (int)($settings['stats_downloads'] ?? 0),
            'rating'       => (float)($settings['stats_rating'] ?? 0),
            'daily_active' => (int)($settings['stats_daily_active'] ?? 0),
        ],
        'font_url'          => $settings['font_url'] ?? '',
        'font_family'       => $settings['font_family'] ?? 'CustomFont',
        'bg_type'           => $settings['bg_type'] ?? 'default',
        'bg_color'          => $settings['bg_color'] ?? '',
        'bg_gradient'       => $settings['bg_gradient'] ?? '',
        'bg_image'          => $settings['bg_image'] ?? '',
        'effects_config'    => $settings['effects_config'] ?? '{}',
        'inapp_redirect'    => (bool)($settings['inapp_redirect'] ?? false),
    ],
    'apps'         => $apps,
    'features'     => $features,
    'friend_links' => $links,
    'custom_code'  => $custom,
];

$json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    json_response(['error' => 'config_encode_failed'], 500);
}
file_put_contents($cache_path, $json, LOCK_EX);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
echo $json;
