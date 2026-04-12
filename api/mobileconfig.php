<?php
/**
 * Mobileconfig动态生成 - 根据应用配置生成iOS WebClip描述文件
 * 支持预生成文件服务 + 即时生成回退
 * 访问: /api/mobileconfig.php?app=slug
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/mobileconfig.php';

$slug = trim($_GET['app'] ?? '');
if (empty($slug) || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
    http_response_code(404);
    exit('invalid app');
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM apps WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$app = $stmt->fetch();

if (!$app) {
    http_response_code(404);
    exit('app not found');
}

// 优先：预生成文件
if (!empty($app['mc_file_id'])) {
    $mcStmt = $pdo->prepare('SELECT file_path FROM generated_mobileconfigs WHERE id = ?');
    $mcStmt->execute([$app['mc_file_id']]);
    $mcFile = $mcStmt->fetch();
    if ($mcFile && !empty($mcFile['file_path'])) {
        $fullPath = __DIR__ . '/../' . $mcFile['file_path'];
        if (file_exists($fullPath)) {
            header('Content-Type: application/x-apple-aspen-config');
            header('Content-Disposition: attachment; filename="' . basename($mcFile['file_path']) . '"');
            header('Content-Length: ' . filesize($fullPath));
            readfile($fullPath);
            exit;
        }
    }
}

// 回退：即时生成（需要 mc_url）
if (empty($app['mc_url'])) {
    http_response_code(404);
    exit('no mobileconfig configured');
}

// 图标base64数据
$iconData = $app['mc_icon_data'] ?? '';
if (empty($iconData) && !empty($app['icon_url'])) {
    $iconPath = __DIR__ . '/../' . ltrim($app['icon_url'], '/');
    if (file_exists($iconPath)) {
        $iconData = base64_encode(file_get_contents($iconPath));
    }
}

// 加载全局设置
$rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
$globalSettings = [];
foreach ($rows as $r) {
    $globalSettings[$r['setting_key']] = $r['setting_val'];
}

// 再fallback到全局logo
if (empty($iconData)) {
    $logoUrl = $globalSettings['logo_url'] ?? '';
    if ($logoUrl) {
        $logoPath = __DIR__ . '/../' . ltrim($logoUrl, '/');
        if (file_exists($logoPath)) {
            $iconData = base64_encode(file_get_contents($logoPath));
        }
    }
}

// 组织名称: 应用级 > 全局 > 站点标题
$organization = $app['mc_payload_org'] ?? '';
if (empty($organization)) $organization = $globalSettings['mc_payload_org'] ?? '';
if (empty($organization)) $organization = $globalSettings['site_title'] ?? '';

// 生成 XML
$unsignedXml = build_mobileconfig_xml([
    'display_name' => $app['name'],
    'target_url'   => $app['mc_url'],
    'bundle_id'    => $app['mc_bundle_id'] ?: 'com.webclip.' . $app['slug'],
    'version'      => $app['mc_version'] ?? '1',
    'fullscreen'   => !empty($app['mc_fullscreen']),
    'icon_data'    => $iconData,
    'description'  => $app['mc_description'] ?: $app['name'],
    'payload_org'  => $organization,
]);

// 签名逻辑：应用级证书 > 全局证书
$certPem = '';
$keyPem = '';
$chainPem = '';

$appSignMode = $app['mc_sign_mode'] ?? '';
if (!empty($appSignMode)) {
    $certPem = resolve_cert_content($appSignMode, $app['mc_sign_cert'] ?? '');
    $keyPem = resolve_cert_content($appSignMode, $app['mc_sign_key'] ?? '');
    $chainPem = resolve_cert_content($appSignMode, $app['mc_sign_chain'] ?? '');
}

if (empty($certPem) || empty($keyPem)) {
    $globalMode = $globalSettings['mc_sign_mode'] ?? '';
    if (!empty($globalMode)) {
        $certPem = resolve_cert_content($globalMode, $globalSettings['mc_sign_cert'] ?? '');
        $keyPem = resolve_cert_content($globalMode, $globalSettings['mc_sign_key'] ?? '');
        $chainPem = resolve_cert_content($globalMode, $globalSettings['mc_sign_chain'] ?? '');
    }
}

// 也检查新的 mc_certificates 表全局证书
if (empty($certPem) || empty($keyPem)) {
    $gcStmt = $pdo->query('SELECT * FROM mc_certificates WHERE is_global = 1 LIMIT 1');
    $gc = $gcStmt->fetch();
    if ($gc) {
        $certPem = resolve_cert_content($gc['mode'], $gc['cert']);
        $keyPem = resolve_cert_content($gc['mode'], $gc['key']);
        $chainPem = resolve_cert_content($gc['mode'], $gc['chain']);
    }
}

if (!empty($certPem) && !empty($keyPem) && function_exists('openssl_pkcs7_sign')) {
    $signed = sign_mobileconfig($unsignedXml, $certPem, $keyPem, $chainPem);
    if ($signed !== false) {
        header('Content-Type: application/x-apple-aspen-config');
        header('Content-Disposition: attachment; filename="' . $slug . '.mobileconfig"');
        header('Content-Length: ' . strlen($signed));
        echo $signed;
        exit;
    }
}

// 未签名输出
header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename="' . $slug . '.mobileconfig"');
echo $unsignedXml;
exit;
