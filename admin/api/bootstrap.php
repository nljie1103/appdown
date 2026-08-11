<?php
/**
 * AppDown Admin 2.0 bootstrap API.
 * Returns authenticated tenant UI context; tenant identity is resolved from Session server-side.
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/version.php';
require_auth();
require_method('GET');

$pdo = get_db();
$tenant = current_tenant(true);
$siteTitle = get_setting($pdo, 'site_title', $tenant['display_name'] ?? 'AppDown');

json_response([
    'ok' => true,
    'csrf' => csrf_token(),
    'edition' => defined('APPDOWN_EDITION') ? APPDOWN_EDITION : 'saas',
    'version' => defined('APPDOWN_VERSION') ? APPDOWN_VERSION : '',
    'user' => [
        'id' => (int)($_SESSION['admin_id'] ?? 0),
        'name' => (string)($_SESSION['admin_user'] ?? ($tenant['slug'] ?? 'Tenant')),
    ],
    'site' => [
        'title' => $siteTitle,
    ],
    'tenant' => $tenant ? [
        'slug' => (string)$tenant['slug'],
        'display_name' => (string)$tenant['display_name'],
        'public_path' => tenant_public_path((string)$tenant['slug']),
    ] : null,
    'capabilities' => [
        'apk_builder' => true,
        'ipa_builder' => true,
        'templates' => true,
        'backup' => true,
        'system_tools' => true,
        'keystores' => true,
        'mobileconfig' => true,
        'fonts' => true,
        // 租户永远不能通过 /admin 升级整个 SaaS 平台；平台升级仍只在 /super。
        'platform_update' => false,
    ],
]);
