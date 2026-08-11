<?php
/**
 * AppDown Admin 2.0 static/runtime boundary smoke test.
 * Usage: php tests/smoke_admin2.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/version.php';

function admin2_fail(string $message): void { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function admin2_assert(bool $ok, string $message): void { if (!$ok) admin2_fail($message); }

$required = [
    'admin/app.php',
    'admin/api/bootstrap.php',
    'admin/vue/admin2.js',
    'admin/vue/admin2.css',
    'admin-ui/package.json',
    'admin-ui/package-lock.json',
    'admin-ui/src/App.vue',
    'admin-ui/src/router.ts',
    'admin-ui/src/api.ts',
];
foreach ($required as $path) {
    admin2_assert(is_file($root . '/' . $path), "missing Admin 2.0 file: {$path}");
    admin2_assert(filesize($root . '/' . $path) > 0, "empty Admin 2.0 file: {$path}");
}

$appShell = (string)file_get_contents($root . '/admin/app.php');
admin2_assert(str_contains($appShell, '/admin/vue/admin2.js'), 'Vue shell does not load production JS');
admin2_assert(str_contains($appShell, '/admin/vue/admin2.css'), 'Vue shell does not load production CSS');

$router = (string)file_get_contents($root . '/admin-ui/src/router.ts');
foreach (['/dashboard','/apps','/attachments','/builder','/signing','/mobileconfig','/templates','/content','/fonts','/settings','/custom-code','/backup','/system','/account'] as $route) {
    admin2_assert(str_contains($router, "path: '{$route}'"), "missing Vue route: {$route}");
}

$srcFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/admin-ui/src', FilesystemIterator::SKIP_DOTS));
foreach ($srcFiles as $file) {
    if (!$file->isFile() || !preg_match('/\.(vue|ts)$/', $file->getFilename())) continue;
    $src = (string)file_get_contents($file->getPathname());
    admin2_assert(!str_contains($src, 'lucide-vue-next'), 'deprecated lucide-vue-next import remains: ' . $file->getFilename());
}
$pkg = json_decode((string)file_get_contents($root . '/admin-ui/package.json'), true);
admin2_assert(is_array($pkg), 'package.json invalid');
admin2_assert(isset($pkg['dependencies']['@lucide/vue']), '@lucide/vue dependency missing');
admin2_assert(!isset($pkg['dependencies']['lucide-vue-next']), 'deprecated lucide-vue-next dependency remains');

$login = (string)file_get_contents($root . '/admin/login.php');
$index = (string)file_get_contents($root . '/admin/index.php');
admin2_assert(str_contains($login, '/admin/app.php#/dashboard'), 'login does not enter Admin 2.0');
admin2_assert(str_contains($index, '/admin/app.php#/dashboard'), '/admin/ does not enter Admin 2.0');

$bootstrap = (string)file_get_contents($root . '/admin/api/bootstrap.php');
admin2_assert(str_contains($bootstrap, "'keystores' => true"), 'keystore capability missing');
admin2_assert(str_contains($bootstrap, "'mobileconfig' => true"), 'mobileconfig capability missing');
admin2_assert(str_contains($bootstrap, "'fonts' => true"), 'font capability missing');

if (APPDOWN_EDITION === 'main') {
    admin2_assert(is_file($root . '/admin/api/update.php'), 'main updater API missing');
    $updaterApi = (string)file_get_contents($root . '/admin/api/update.php');
    admin2_assert(!str_contains($updaterApi, 'get_request_method('), 'updater API references undefined get_request_method helper');
    admin2_assert(str_contains($bootstrap, "'platform_update' => true"), 'main update capability should be enabled');
    admin2_assert(str_contains($router, "path: '/update'"), 'main Vue update route missing');
} else {
    admin2_assert(str_contains($bootstrap, "'platform_update' => false"), 'SaaS tenant must not have platform update capability');
}

fwrite(STDOUT, 'Admin 2.0 smoke test passed for edition ' . APPDOWN_EDITION . ".\n");
