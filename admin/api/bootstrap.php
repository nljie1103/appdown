<?php
/**
 * AppDown Admin 2.0 bootstrap API.
 * Returns only authenticated UI context; business data stays in dedicated APIs.
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/version.php';
require_auth();
require_method('GET');

$pdo = get_db();
$tenant = function_exists('current_tenant') ? current_tenant(true) : null;
$siteTitle = get_setting($pdo, 'site_title', 'AppDown');

json_response([
    'ok' => true,
    'csrf' => csrf_token(),
    'edition' => defined('APPDOWN_EDITION') ? APPDOWN_EDITION : 'main',
    'version' => defined('APPDOWN_VERSION') ? APPDOWN_VERSION : '',
    'user' => [
        'id' => (int)($_SESSION['admin_id'] ?? 0),
        'name' => (string)($_SESSION['admin_user'] ?? 'Admin'),
    ],
    'site' => [
        'title' => $siteTitle,
    ],
    'tenant' => $tenant ? [
        'slug' => (string)$tenant['slug'],
        'display_name' => (string)$tenant['display_name'],
        'public_path' => function_exists('tenant_public_path') ? tenant_public_path((string)$tenant['slug']) : '/',
    ] : null,
    'capabilities' => [
        'apk_builder' => true,
        'ipa_builder' => true,
        'templates' => true,
        'backup' => true,
        'system_tools' => true,
    ],
]);
