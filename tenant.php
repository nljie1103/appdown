<?php
/**
 * SaaS 公开租户分发页。
 * Web Server 将 /<slug>/ 重写到这里；页面核心继续复用 index.html。
 */
require_once __DIR__ . '/includes/init.php';

$tenant = require_tenant_context();
$slug = $tenant['slug'];
$templateFile = __DIR__ . '/index.html';
if (!is_file($templateFile)) {
    http_response_code(500);
    die('Landing page template missing');
}

$html = (string)file_get_contents($templateFile);
$prefix = '/' . rawurlencode($slug);

// 静态公共资源必须从根目录加载；租户 URL 下不能使用相对 static/。
$html = str_replace('href="static/', 'href="/static/', $html);
$html = str_replace("fetch('/api/config.php')", "fetch('{$prefix}/api/config.php')", $html);
$html = str_replace("'/api/track.php'", "'{$prefix}/api/track.php'", $html);
$html = str_replace('href="privacy.php"', 'href="' . $prefix . '/privacy.php"', $html);
$html = str_replace('href="terms.php"', 'href="' . $prefix . '/terms.php"', $html);

// 右键菜单的“返回首页”在租户页中返回当前租户，而不是平台欢迎页。
$html = str_replace("action:()=>location.href='/'", "action:()=>location.href='{$prefix}/'", $html);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
echo $html;
