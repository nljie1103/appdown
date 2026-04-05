<?php
/**
 * iOS安装引导页路由
 * 访问: /ios/?app=slug
 * 根据应用的 ios_template 字段选择模板
 */

require_once __DIR__ . '/../includes/init.php';

$slug = trim($_GET['app'] ?? '');
if (empty($slug) || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
    http_response_code(404);
    echo '<!doctype html><html><body><h1>404 - 应用不存在</h1><p><a href="/">返回首页</a></p></body></html>';
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM apps WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$app = $stmt->fetch();

if (!$app || empty($app['ios_plist_url'])) {
    http_response_code(404);
    echo '<!doctype html><html><body><h1>404 - 该应用暂无iOS版本</h1><p><a href="/">返回首页</a></p></body></html>';
    exit;
}

// 获取站点设置
$settings = [];
$rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
foreach ($rows as $r) {
    $settings[$r['setting_key']] = $r['setting_val'];
}

// 公共变量
$siteName = htmlspecialchars($settings['site_title'] ?? 'APP下载');
$appName = htmlspecialchars($app['name']);
$plistUrl = htmlspecialchars($app['ios_plist_url']);
$certName = htmlspecialchars($app['ios_cert_name']);
$description = htmlspecialchars($app['ios_description']);
$version = htmlspecialchars($app['ios_version']);
$size = htmlspecialchars($app['ios_size']);
$themeColor = htmlspecialchars($app['theme_color'] ?? '#017afe');
// 优先使用应用自己的图标，没有则用全局logo
$iconUrl = htmlspecialchars($app['icon_url'] ?: ($settings['logo_url'] ?? ''));

// 根据模板选择
$template = $app['ios_template'] ?? 'modern';
if ($template === 'classic') {
    include __DIR__ . '/template-classic.php';
} else {
    include __DIR__ . '/template-modern.php';
}
