<?php
/**
 * SaaS Mobileconfig 动态生成。
 * 访问: /<tenant>/api/mobileconfig.php?app=slug
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/mobileconfig.php';

$tenant = require_tenant_context();
$tenantSlug = $tenant['slug'];
$appSlug = trim((string)($_GET['app'] ?? ''));
if ($appSlug === '' || !preg_match('/^[a-z0-9_-]+$/', $appSlug)) {
    http_response_code(404);
    exit('invalid app');
}

function tenant_stored_file_path(string $stored, string $tenantSlug): ?string {
    if ($stored === '' || preg_match('#^https?://#i', $stored)) return null;
    $webPath = tenant_absolute_asset_url($stored, $tenantSlug);
    $candidate = realpath(__DIR__ . '/..' . $webPath);
    $tenantRoot = realpath(tenant_upload_dir($tenantSlug));
    if (!$candidate || !$tenantRoot || !str_starts_with($candidate, $tenantRoot . DIRECTORY_SEPARATOR) || !is_file($candidate)) return null;
    return $candidate;
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM apps WHERE slug = ? AND is_active = 1');
$stmt->execute([$appSlug]);
$app = $stmt->fetch();
if (!$app) {
    http_response_code(404);
    exit('app not found');
}

// 优先服务租户自己的预生成 mobileconfig。
if (!empty($app['mc_file_id'])) {
    $mcStmt = $pdo->prepare('SELECT file_path FROM generated_mobileconfigs WHERE id = ?');
    $mcStmt->execute([(int)$app['mc_file_id']]);
    $mcFile = $mcStmt->fetch();
    if ($mcFile && !empty($mcFile['file_path'])) {
        $fullPath = tenant_stored_file_path((string)$mcFile['file_path'], $tenantSlug);
        if ($fullPath) {
            header('Content-Type: application/x-apple-aspen-config');
            header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
            header('Content-Length: ' . filesize($fullPath));
            readfile($fullPath);
            exit;
        }
    }
}

if (!empty($app['mc_file_url'])) {
    $fullPath = tenant_stored_file_path((string)$app['mc_file_url'], $tenantSlug);
    if ($fullPath) {
        header('Content-Type: application/x-apple-aspen-config');
        header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}

if (empty($app['mc_url'])) {
    http_response_code(404);
    exit('no mobileconfig configured');
}

$iconData = (string)($app['mc_icon_data'] ?? '');
if ($iconData === '' && !empty($app['icon_url'])) {
    $iconPath = tenant_stored_file_path((string)$app['icon_url'], $tenantSlug);
    if ($iconPath) $iconData = base64_encode((string)file_get_contents($iconPath));
}

$globalSettings = [];
foreach ($pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll() as $row) {
    $globalSettings[$row['setting_key']] = $row['setting_val'];
}

if ($iconData === '' && !empty($globalSettings['logo_url'])) {
    $logoPath = tenant_stored_file_path((string)$globalSettings['logo_url'], $tenantSlug);
    if ($logoPath) $iconData = base64_encode((string)file_get_contents($logoPath));
}

$organization = (string)($app['mc_payload_org'] ?? '');
if ($organization === '') $organization = (string)($globalSettings['mc_payload_org'] ?? '');
if ($organization === '') $organization = (string)($globalSettings['site_title'] ?? $tenant['display_name']);

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

$certPem = '';
$keyPem = '';
$chainPem = '';
$appSignMode = (string)($app['mc_sign_mode'] ?? '');
if ($appSignMode !== '') {
    $certPem = resolve_cert_content($appSignMode, (string)($app['mc_sign_cert'] ?? ''));
    $appKey = (string)($app['mc_sign_key'] ?? '');
    if (is_encrypted_secret($appKey)) $appKey = decrypt_secret($appKey);
    $keyPem = resolve_cert_content($appSignMode, $appKey);
    $chainPem = resolve_cert_content($appSignMode, (string)($app['mc_sign_chain'] ?? ''));
}

if ($certPem === '' || $keyPem === '') {
    $globalMode = (string)($globalSettings['mc_sign_mode'] ?? '');
    if ($globalMode !== '') {
        $certPem = resolve_cert_content($globalMode, (string)($globalSettings['mc_sign_cert'] ?? ''));
        $globalKey = (string)($globalSettings['mc_sign_key'] ?? '');
        if (is_encrypted_secret($globalKey)) $globalKey = decrypt_secret($globalKey);
        $keyPem = resolve_cert_content($globalMode, $globalKey);
        $chainPem = resolve_cert_content($globalMode, (string)($globalSettings['mc_sign_chain'] ?? ''));
    }
}

if ($certPem === '' || $keyPem === '') {
    $gc = $pdo->query('SELECT * FROM mc_certificates WHERE is_global = 1 LIMIT 1')->fetch();
    if ($gc) {
        $certPem = resolve_cert_content((string)$gc['mode'], (string)$gc['cert']);
        $keyPem = resolve_cert_content((string)$gc['mode'], decrypt_secret((string)($gc['key'] ?? '')));
        $chainPem = resolve_cert_content((string)$gc['mode'], (string)$gc['chain']);
    }
}

$output = $unsignedXml;
if ($certPem !== '' && $keyPem !== '' && function_exists('openssl_pkcs7_sign')) {
    $signed = sign_mobileconfig($unsignedXml, $certPem, $keyPem, $chainPem);
    if (is_string($signed) && $signed !== '') {
        $output = $signed;
    } elseif (is_array($signed) && !empty($signed['error'])) {
        error_log('[AppDown SaaS mobileconfig] ' . $signed['error']);
    }
}

header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename="' . $appSlug . '.mobileconfig"');
header('Content-Length: ' . strlen($output));
echo $output;
exit;
