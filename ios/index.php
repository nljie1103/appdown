<?php
/**
 * iOS安装引导页路由
 * 访问: /ios/?app=slug          (IPA模式)
 * 访问: /ios/?app=slug&type=mobileconfig  (Mobileconfig模式)
 * 根据应用的模板字段选择模板
 */

require_once __DIR__ . '/../includes/init.php';

$slug = trim($_GET['app'] ?? '');
if (empty($slug) || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
    http_response_code(404);
    echo '<!doctype html><html><body><h1>404 - 应用不存在</h1><p><a href="/">返回首页</a></p></body></html>';
    exit;
}

// 检测安装类型：ipa 或 mobileconfig
$installType = trim($_GET['type'] ?? 'ipa');
if (!in_array($installType, ['ipa', 'mobileconfig'])) {
    $installType = 'ipa';
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM apps WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$app = $stmt->fetch();

if ($installType === 'mobileconfig') {
    if (!$app || (empty($app['mc_url']) && empty($app['mc_file_id']))) {
        http_response_code(404);
        echo '<!doctype html><html><body><h1>404 - 该应用暂无Mobileconfig版本</h1><p><a href="/">返回首页</a></p></body></html>';
        exit;
    }
} else {
    if (!$app || empty($app['ios_plist_url'])) {
        http_response_code(404);
        echo '<!doctype html><html><body><h1>404 - 该应用暂无iOS版本</h1><p><a href="/">返回首页</a></p></body></html>';
        exit;
    }
}

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

// 公共变量
$siteName = htmlspecialchars($settings['site_title'] ?? 'APP下载');
$appName = htmlspecialchars($app['name']);
$themeColor = htmlspecialchars($app['theme_color'] ?? '#017afe');
$iconUrl = htmlspecialchars($app['icon_url'] ?: ($settings['logo_url'] ?? ''));

if ($installType === 'mobileconfig') {
    // Mobileconfig模式
    $plistUrl = $baseUrl . '/api/mobileconfig.php?app=' . $app['slug'];
    $certName = '';
    $description = htmlspecialchars($app['mc_description']);
    $version = htmlspecialchars($app['mc_version']);
    $size = '';
    $template = $app['mc_template'] ?? 'modern';

    // 如有预生成文件，从其记录加载详细信息
    if (!empty($app['mc_file_id'])) {
        $mcStmt = $pdo->prepare('SELECT * FROM generated_mobileconfigs WHERE id = ?');
        $mcStmt->execute([$app['mc_file_id']]);
        $mcFile = $mcStmt->fetch();
        if ($mcFile) {
            if (!empty($mcFile['description'])) $description = htmlspecialchars($mcFile['description']);
            if (!empty($mcFile['version'])) $version = htmlspecialchars($mcFile['version']);
            if (!empty($mcFile['template'])) $template = $mcFile['template'];
        }
    }
} else {
    // IPA模式（保持原有逻辑）
    $plistUrl = htmlspecialchars($app['ios_plist_url']);
    $certName = htmlspecialchars($app['ios_cert_name']);
    $description = htmlspecialchars($app['ios_description']);
    $version = htmlspecialchars($app['ios_version']);
    $size = htmlspecialchars($app['ios_size']);
    $template = $app['ios_template'] ?? 'modern';
}

// 根据模板选择
if ($template === 'classic') {
    include __DIR__ . '/template-classic.php';
} else {
    include __DIR__ . '/template-modern.php';
}
