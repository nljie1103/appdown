<?php
/**
 * Android安装引导页路由
 * 访问: /android/?app=slug
 * 根据应用的 android_ 系列字段提供安装信息
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

if (!$app) {
    http_response_code(404);
    echo '<!doctype html><html><body><h1>404 - 应用不存在</h1><p><a href="/">返回首页</a></p></body></html>';
    exit;
}

// 从应用配置读取APK地址
$downloadUrl = $app['android_apk_url'] ?? '';

// 获取站点设置
$settings = [];
$rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
foreach ($rows as $r) {
    $settings[$r['setting_key']] = $r['setting_val'];
}

// 构建基础URL
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $scheme . '://' . $host;

// 如果是相对路径，补全为绝对URL
if ($downloadUrl && !preg_match('#^https?://#', $downloadUrl)) {
    $downloadUrl = $baseUrl . '/' . ltrim($downloadUrl, '/');
}

// 模板变量
$siteName = htmlspecialchars($settings['site_title'] ?? 'APP下载');
$appName = htmlspecialchars($app['name']);
$themeColor = htmlspecialchars($app['theme_color'] ?? '#4CAF50');
$iconUrl = htmlspecialchars($app['icon_url'] ?: ($settings['logo_url'] ?? ''));
$downloadHref = htmlspecialchars($downloadUrl);
$version = htmlspecialchars($app['android_version'] ?? '');
$size = htmlspecialchars($app['android_size'] ?? '');
$description = htmlspecialchars($app['android_description'] ?? '');

// 根据模板选择
$template = $app['android_template'] ?? 'modern';
if ($template === 'classic') {
    include __DIR__ . '/template-classic.php';
} else {
    include __DIR__ . '/template-modern.php';
}
