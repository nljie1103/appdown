<?php
/**
 * 公共API: 返回完整站点配置JSON
 * GET /api/config.php
 */

require_once __DIR__ . '/../includes/init.php';
require_method('GET');

// 文件缓存 (5分钟)
$cache_path = __DIR__ . '/../data/config_cache.json';
if (file_exists($cache_path) && (time() - filemtime($cache_path)) < 300) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60');
    readfile($cache_path);
    exit;
}

$pdo = get_db();

// 站点设置
$settings = [];
$rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
foreach ($rows as $r) {
    $settings[$r['setting_key']] = $r['setting_val'];
}

// 应用列表
$apps = $pdo->query('SELECT id, slug, name, icon, theme_color FROM apps WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

foreach ($apps as &$app) {
    // 下载按钮
    $dlStmt = $pdo->prepare('SELECT btn_type, btn_text, btn_subtext, href FROM app_downloads WHERE app_id = ? AND is_active = 1 ORDER BY sort_order ASC');
    $dlStmt->execute([$app['id']]);
    $app['downloads'] = $dlStmt->fetchAll();

    // 轮播图
    $imgStmt = $pdo->prepare('SELECT image_url, alt_text FROM app_images WHERE app_id = ? ORDER BY sort_order ASC');
    $imgStmt->execute([$app['id']]);
    $app['images'] = array_column($imgStmt->fetchAll(), null);

    // 输出时用slug做id，移除数据库id
    $app['id'] = $app['slug'];
    unset($app['slug']);
}
unset($app);

// 特色卡片
$features = $pdo->query('SELECT title, description, icon FROM feature_cards WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

// 友情链接
$links = $pdo->query('SELECT name, url FROM friend_links WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

// 自定义代码
$custom = [];
$cRows = $pdo->query('SELECT position, code FROM custom_code')->fetchAll();
foreach ($cRows as $r) {
    $custom[$r['position']] = $r['code'];
}

$config = [
    'site' => [
        'title'             => $settings['site_title'] ?? '',
        'heading'           => $settings['site_heading'] ?? '',
        'logo_url'          => $settings['logo_url'] ?? 'img/logo.png',
        'favicon_url'       => $settings['favicon_url'] ?? 'img/favicon.ico',
        'notice_text'       => $settings['notice_text'] ?? '',
        'notice_enabled'    => (bool)($settings['notice_enabled'] ?? true),
        'copyright'         => $settings['copyright'] ?? '',
        'carousel_interval' => (int)($settings['carousel_interval'] ?? 4000),
        'stats' => [
            'downloads'    => (int)($settings['stats_downloads'] ?? 0),
            'rating'       => (float)($settings['stats_rating'] ?? 0),
            'daily_active' => (int)($settings['stats_daily_active'] ?? 0),
        ],
        'font_url'    => $settings['font_url'] ?? '',
        'font_family' => $settings['font_family'] ?? 'CustomFont',
    ],
    'apps'         => $apps,
    'features'     => $features,
    'friend_links' => $links,
    'custom_code'  => $custom,
];

$json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// 写入缓存
file_put_contents($cache_path, $json, LOCK_EX);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
echo $json;
