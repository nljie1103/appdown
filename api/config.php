<?php
/**
 * SaaS 公共 API：返回当前租户完整站点配置 JSON。
 * Web Server 将 /<slug>/api/config.php 重写到本文件并附带 tenant=<slug>。
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/landing_templates.php';
require_method('GET');

$tenant = require_tenant_context();
$slug = $tenant['slug'];
$lock = __DIR__ . '/../install/install.lock';
$dbFile = tenant_db_path($slug);
if (!file_exists($lock) || !file_exists($dbFile)) {
    json_response(['error' => 'tenant_not_initialized'], 503);
}

$cachePath = tenant_config_cache_path($slug);
if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 300) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60');
    readfile($cachePath);
    exit;
}

$pdo = get_db();
$settings = [];
foreach ($pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_val'];
}

function tenant_public_asset(?string $value, string $slug): string {
    return tenant_absolute_asset_url((string)$value, $slug);
}

function tenant_public_href(?string $value, string $slug): string {
    $value = trim((string)$value);
    if ($value === '' || $value === '#') return $value;
    if (preg_match('#^/?api/(config|track|plist|mobileconfig)\.php(.*)$#i', $value, $m)) {
        return '/' . rawurlencode($slug) . '/api/' . strtolower($m[1]) . '.php' . $m[2];
    }
    return tenant_absolute_asset_url($value, $slug);
}

// 批量读取当前租户的应用关联数据，避免每个应用重复执行下载/截图/特色查询。
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
        $row['href'] = tenant_public_href($row['href'] ?? '', $slug);
        $downloadsByApp[$appId][] = $row;
    }

    $stmt = $pdo->prepare("SELECT app_id, image_url, alt_text FROM app_images WHERE app_id IN ($marks) ORDER BY app_id, sort_order ASC");
    $stmt->execute($appIds);
    foreach ($stmt->fetchAll() as $row) {
        $appId = (int)$row['app_id'];
        unset($row['app_id']);
        $row['image_url'] = tenant_public_asset($row['image_url'] ?? '', $slug);
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
        $row['icon_url'] = tenant_public_asset($row['icon_url'] ?? '', $slug);
        $featuresByCategory[$categoryId][] = $row;
    }
}

foreach ($apps as &$app) {
    $appId = (int)$app['id'];
    $categoryId = (int)($app['feature_category_id'] ?? 0);
    $app['icon_url'] = tenant_public_asset($app['icon_url'] ?? '', $slug);
    $app['downloads'] = $downloadsByApp[$appId] ?? [];
    $app['images'] = $imagesByApp[$appId] ?? [];
    $app['features'] = $categoryId > 0 ? ($featuresByCategory[$categoryId] ?? []) : [];
    $app['id'] = $app['slug'];
    unset($app['slug'], $app['feature_category_id']);
}
unset($app);

$features = $pdo->query('SELECT title, description, icon, icon_url FROM feature_cards WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
foreach ($features as &$feature) {
    $feature['icon_url'] = tenant_public_asset($feature['icon_url'] ?? '', $slug);
}
unset($feature);

$links = $pdo->query('SELECT name, url, icon, icon_url, show_icon FROM friend_links WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();
foreach ($links as &$link) {
    $link['url'] = tenant_public_href($link['url'] ?? '#', $slug);
    // 现有首页会自行补前导斜线，继续保持兼容。
    $link['icon_url'] = ltrim(tenant_public_asset($link['icon_url'] ?? '', $slug), '/');
}
unset($link);

$custom = [];
foreach ($pdo->query('SELECT position, code FROM custom_code')->fetchAll() as $row) {
    $custom[$row['position']] = $row['code'];
}

$landingTemplate = normalize_landing_template($settings['landing_template'] ?? 'classic');
$templateCss = landing_template_css($landingTemplate);
if ($templateCss !== '') {
    $custom['head_css'] = $templateCss . "\n" . ($custom['head_css'] ?? '');
}

$config = [
    'tenant' => [
        'slug' => $slug,
        'display_name' => $tenant['display_name'],
        'public_path' => tenant_public_path($slug),
    ],
    'site' => [
        'title'             => $settings['site_title'] ?? $tenant['display_name'],
        'heading'           => $settings['site_heading'] ?? $tenant['display_name'],
        'logo_url'          => tenant_public_asset($settings['logo_url'] ?? '', $slug),
        'favicon_url'       => tenant_public_asset($settings['favicon_url'] ?? '', $slug),
        'notice_text'       => $settings['notice_text'] ?? '',
        'notice_enabled'    => ($settings['notice_enabled'] ?? '0') === '1',
        'copyright'         => $settings['copyright'] ?? '',
        'carousel_interval' => (int)($settings['carousel_interval'] ?? 4000),
        'landing_template'  => $landingTemplate,
        'landing_layout'    => landing_template_layout($landingTemplate),
        'stats' => [
            'downloads'    => (int)($settings['stats_downloads'] ?? 0),
            'rating'       => (float)($settings['stats_rating'] ?? 0),
            'daily_active' => (int)($settings['stats_daily_active'] ?? 0),
        ],
        'font_url'          => tenant_public_asset($settings['font_url'] ?? '', $slug),
        'font_family'       => $settings['font_family'] ?? 'CustomFont',
        'bg_type'           => $settings['bg_type'] ?? 'default',
        'bg_color'          => $settings['bg_color'] ?? '',
        'bg_gradient'       => $settings['bg_gradient'] ?? '',
        'bg_image'          => tenant_public_asset($settings['bg_image'] ?? '', $slug),
        'effects_config'    => $settings['effects_config'] ?? '{}',
        'inapp_redirect'    => ($settings['inapp_redirect'] ?? '0') === '1',
    ],
    'apps'         => $apps,
    'features'     => $features,
    'friend_links' => $links,
    'custom_code'  => $custom,
];

$json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) json_response(['error' => 'config_encode_failed'], 500);
if (!is_dir(dirname($cachePath))) @mkdir(dirname($cachePath), 0750, true);
file_put_contents($cachePath, $json, LOCK_EX);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
echo $json;
