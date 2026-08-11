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

// 优先：预生成文件（通过 mc_file_id 关联）
if (!empty($app['mc_file_id'])) {
    $mcStmt = $pdo->prepare('SELECT file_path FROM generated_mobileconfigs WHERE id = ?');
    $mcStmt->execute([$app['mc_file_id']]);
    $mcFile = $mcStmt->fetch();
    if ($mcFile && !empty($mcFile['file_path'])) {
        $fullPath = realpath(__DIR__ . '/../' . ltrim($mcFile['file_path'], '/'));
        $projectRoot = realpath(__DIR__ . '/..');
        if ($fullPath && $projectRoot && str_starts_with($fullPath, $projectRoot . DIRECTORY_SEPARATOR) && is_file($fullPath)) {
            header('Content-Type: application/x-apple-aspen-config');
            header('Content-Disposition: attachment; filename="' . basename($mcFile['file_path']) . '"');
            header('Content-Length: ' . filesize($fullPath));
            readfile($fullPath);
            exit;
        }
    }
}

// 其次：通过 mc_file_url 直接指定文件路径
if (!empty($app['mc_file_url'])) {
    $fileUrl = $app['mc_file_url'];
    if (!preg_match('#^https?://#', $fileUrl)) {
        $fullPath = realpath(__DIR__ . '/../' . ltrim($fileUrl, '/'));
        $projectRoot = realpath(__DIR__ . '/..');
        if ($fullPath && $projectRoot && str_starts_with($fullPath, $projectRoot . DIRECTORY_SEPARATOR) && is_file($fullPath)) {
            header('Content-Type: application/x-apple-aspen-config');
            header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
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

$iconData = $app['mc_icon_data'] ?? '';
if (empty($iconData) && !empty($app['icon_url'])) {
    $iconPath = realpath(__DIR__ . '/../' . ltrim($app['icon_url'], '/'));
    $projectRoot = realpath(__DIR__ . '/..');
    if ($iconPath && $projectRoot && str_starts_with($iconPath, $projectRoot . DIRECTORY_SEPARATOR) && is_file($iconPath)) {
        $iconData = base64_encode(file_get_contents($iconPath));
    }
}

$rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
$globalSettings = [];
foreach ($rows as $r) $globalSettings[$r['setting_key']] = $r['setting_val'];

if (empty($iconData)) {
    $logoUrl = $globalSettings['logo_url'] ?? '';
    if ($logoUrl) {
        $logoPath = realpath(__DIR__ . '/../' . ltrim($logoUrl, '/'));
        $projectRoot = realpath(__DIR__ . '/..');
        if ($logoPath && $projectRoot && str_starts_with($logoPath, $projectRoot . DIRECTORY_SEPARATOR) && is_file($logoPath)) {
            $iconData = base64_encode(file_get_contents($logoPath));
        }
    }
}

$organization = $app['mc_payload_org'] ?? '';
if (empty($organization)) $organization = $globalSettings['mc_payload_org'] ?? '';
if (empty($organization)) $organization = $globalSettings['site_title'] ?? '';

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

// 签名逻辑：应用级证书 > 旧全局设置 > 新 mc_certificates 全局证书
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

if (empty($certPem) || empty($keyPem)) {
    $gcStmt = $pdo->query('SELECT * FROM mc_certificates WHERE is_global = 1 LIMIT 1');
    $gc = $gcStmt->fetch();
    if ($gc) {
        $certPem = resolve_cert_content($gc['mode'], $gc['cert']);
        $keyValue = decrypt_secret((string)($gc['key'] ?? ''));
        $keyPem = resolve_cert_content($gc['mode'], $keyValue);
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

header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename="' . $slug . '.mobileconfig"');
echo $unsignedXml;
exit;
