<?php
/**
 * AppDown Admin 2.0 static/runtime boundary + feature-parity smoke test.
 * Usage: php tests/smoke_admin2.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/version.php';

function admin2_fail(string $message): void { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function admin2_assert(bool $ok, string $message): void { if (!$ok) admin2_fail($message); }
function admin2_source(string $root, string $path): string {
    admin2_assert(is_file($root . '/' . $path), "missing Admin 2.0 file: {$path}");
    $src = (string)file_get_contents($root . '/' . $path);
    admin2_assert($src !== '', "empty Admin 2.0 file: {$path}");
    return $src;
}
function admin2_markers(string $src, array $markers, string $label): void {
    foreach ($markers as $marker) admin2_assert(str_contains($src, $marker), "{$label} missing parity marker: {$marker}");
}

$required = [
    'admin/app.php',
    'admin/api/bootstrap.php',
    'admin/api/system-overview.php',
    'admin/vue/admin2.js',
    'admin/vue/admin2.css',
    'admin-ui/package.json',
    'admin-ui/package-lock.json',
    'admin-ui/src/App.vue',
    'admin-ui/src/router.ts',
    'admin-ui/src/api.ts',
    'admin-ui/src/views/AppsView.vue',
    'admin-ui/src/views/AttachmentsView.vue',
    'admin-ui/src/views/BackupView.vue',
    'admin-ui/src/views/ContentView.vue',
    'admin-ui/src/views/FontsView.vue',
    'admin-ui/src/views/MediaView.vue',
    'admin-ui/src/views/MobileconfigView.vue',
    'admin-ui/src/views/SystemView.vue',
    'admin-ui/src/views/AccountView.vue',
];
foreach ($required as $path) {
    admin2_assert(is_file($root . '/' . $path), "missing Admin 2.0 file: {$path}");
    admin2_assert(filesize($root . '/' . $path) > 0, "empty Admin 2.0 file: {$path}");
}

$appShell = admin2_source($root, 'admin/app.php');
admin2_assert(str_contains($appShell, '/admin/vue/admin2.js'), 'Vue shell does not load production JS');
admin2_assert(str_contains($appShell, '/admin/vue/admin2.css'), 'Vue shell does not load production CSS');

$router = admin2_source($root, 'admin-ui/src/router.ts');
foreach (['/dashboard','/apps','/attachments','/builder','/signing','/mobileconfig','/templates','/content','/media','/fonts','/settings','/custom-code','/backup','/system','/account'] as $route) {
    admin2_assert(str_contains($router, "path: '{$route}'"), "missing Vue route: {$route}");
}

$apps = admin2_source($root, 'admin-ui/src/views/AppsView.vue');
admin2_markers($apps, [
    'feature_category_id', 'ios_cert_name', 'mc_file_id', 'mc_file_url',
    'android-install', 'ios-ipa-install', 'ios-mobileconfig-install',
    '/admin/api/reorder.php', "table==='apps'", "table==='app_downloads'", "'app_images'",
], 'AppsView');

$attachments = admin2_source($root, 'admin-ui/src/views/AttachmentsView.vue');
admin2_markers($attachments, [
    '/admin/api/package-info.php?file=', 'inspectPackage', 'dropFile', '.apk,.ipa,.exe,.dmg,.zip',
], 'AttachmentsView');

$backup = admin2_source($root, 'admin-ui/src/views/BackupView.vue');
admin2_markers($backup, [
    'passwordConfirm', '加密密码至少 8 位', "filter(t=>t!=='admin_users')", 'signing_materials_included',
], 'BackupView');

$content = admin2_source($root, 'admin-ui/src/views/ContentView.vue');
admin2_markers($content, [
    '/admin/api/features.php?action=categories', 'feature_categories', 'feature_cards',
    'friend_links', 'show_icon', 'icon_url', '/admin/api/reorder.php',
], 'ContentView');

$fonts = admin2_source($root, 'admin-ui/src/views/FontsView.vue');
admin2_markers($fonts, ['readFontName', '0x4f54544f', '用户上传字体'], 'FontsView');

$media = admin2_source($root, 'admin-ui/src/views/MediaView.vue');
admin2_markers($media, [
    '/admin/api/image-library.php?action=categories', '/admin/api/image-library.php?action=images',
    'image_categories', 'image_library', 'quality', "format: 'webp'", '<option value="gif">GIF</option>', 'dropImage',
], 'MediaView');

$mobileconfig = admin2_source($root, 'admin-ui/src/views/MobileconfigView.vue');
admin2_markers($mobileconfig, [
    "action:'update'", "action:'rename'", "action:'associate'", "action:'update_cert'",
    "action:'import_global_cert'", 'profilePlatforms', 'icon_data',
], 'MobileconfigView');

$system = admin2_source($root, 'admin-ui/src/views/SystemView.vue');
admin2_markers($system, [
    '/admin/api/system-overview.php', 'save_env_paths', 'install_ios_xcode', 'submit_ios_2fa',
    'custom_android_home', 'custom_docker_data_root', 'custom_docker_osx_image', 'custom_xcode_version',
], 'SystemView');

$customCode = admin2_source($root, 'admin-ui/src/views/CustomCodeView.vue');
admin2_markers($customCode, [
    '全屏樱花', '全屏雪花', '节日灯笼', '粒子背景', '鼠标跟随', '彩带背景',
    '全站灰色', '右键美化', '禁止查看源码', '背景音乐', '节日欢迎弹窗',
    'FESTIVALS', 'effects_config', '/admin/api/settings.php',
], 'CustomCodeView');

$androidDefaultIcon = admin2_source($root, 'android-template/app/src/main/res/mipmap/ic_launcher.xml');
admin2_markers($androidDefaultIcon, ['<vector', '#2563EB'], 'Android default launcher icon');

$iosWorker = admin2_source($root, 'tools/ios-build-worker.php');
admin2_markers($iosWorker, ['scp_to_remote', 'scp_from_remote', '/tmp/appdown-build-', 'ios_builder_ed25519'], 'iOS build worker');

$xcodeWorker = admin2_source($root, 'tools/xcode-install-worker.php');
admin2_markers($xcodeWorker, ['custom_ios_ssh_port', 'custom_xcode_version', 'ios_builder_ed25519', 'StrictHostKeyChecking=accept-new'], 'Xcode install worker');

$iosSetup = admin2_source($root, 'tools/setup-ios-env.sh');
admin2_markers($iosSetup, ['x86_64', '127.0.0.1:${SSH_PORT}:10022', 'Phase 1 判定失败', 'sickcodes/docker-osx:auto'], 'iOS environment setup');

$interactiveXcode = admin2_source($root, 'tools/setup-ios-xcode.sh');
admin2_markers($interactiveXcode, ['SSH_PORT="${SSH_PORT:-50922}"', '/usr/local/bin:/opt/homebrew/bin', 'IdentitiesOnly=yes', 'xcodebuild -version'], 'interactive Xcode setup');

$pbx = admin2_source($root, 'ios-template/WebViewApp.xcodeproj/project.pbxproj');
admin2_markers($pbx, ['objectVersion = 54;', 'compatibilityVersion = "Xcode 12.0";', 'IPHONEOS_DEPLOYMENT_TARGET = 14.0;'], 'iOS Xcode 12 compatible project');
foreach (['icon-20@2x.png','icon-20@3x.png','icon-29@2x.png','icon-29@3x.png','icon-40@2x.png','icon-40@3x.png','icon-60@2x.png','icon-60@3x.png','icon-76@1x.png','icon-76@2x.png','icon-83.5@2x.png','icon-1024.png'] as $icon) {
    $iconPath = $root . '/ios-template/WebViewApp/Assets.xcassets/AppIcon.appiconset/' . $icon;
    admin2_assert(is_file($iconPath) && filesize($iconPath) > 100, 'missing/empty default iOS icon: ' . $icon);
}

$account = admin2_source($root, 'admin-ui/src/views/AccountView.vue');
admin2_markers($account, ['新密码长度不能少于 8 位', '两次输入的新密码不一致', 'current_password'], 'AccountView');

$srcFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/admin-ui/src', FilesystemIterator::SKIP_DOTS));
foreach ($srcFiles as $file) {
    if (!$file->isFile() || !preg_match('/\.(vue|ts)$/', $file->getFilename())) continue;
    $src = (string)file_get_contents($file->getPathname());
    admin2_assert(!str_contains($src, 'lucide-vue-next'), 'deprecated lucide-vue-next import remains: ' . $file->getFilename());
}
$pkg = json_decode(admin2_source($root, 'admin-ui/package.json'), true);
admin2_assert(is_array($pkg), 'package.json invalid');
admin2_assert(isset($pkg['dependencies']['@lucide/vue']), '@lucide/vue dependency missing');
admin2_assert(!isset($pkg['dependencies']['lucide-vue-next']), 'deprecated lucide-vue-next dependency remains');

$login = admin2_source($root, 'admin/login.php');
$index = admin2_source($root, 'admin/index.php');
admin2_assert(str_contains($login, '/admin/app.php#/dashboard'), 'login does not enter Admin 2.0');
admin2_assert(str_contains($index, '/admin/app.php#/dashboard'), '/admin/ does not enter Admin 2.0');

$bootstrap = admin2_source($root, 'admin/api/bootstrap.php');
admin2_assert(str_contains($bootstrap, "'keystores' => true"), 'keystore capability missing');
admin2_assert(str_contains($bootstrap, "'mobileconfig' => true"), 'mobileconfig capability missing');
admin2_assert(str_contains($bootstrap, "'fonts' => true"), 'font capability missing');

if (APPDOWN_EDITION === 'main') {
    admin2_assert(is_file($root . '/admin/api/update.php'), 'main updater API missing');
    $updaterApi = admin2_source($root, 'admin/api/update.php');
    admin2_assert(!str_contains($updaterApi, 'get_request_method('), 'updater API references undefined get_request_method helper');
    admin2_assert(str_contains($bootstrap, "'platform_update' => true"), 'main update capability should be enabled');
    admin2_assert(str_contains($router, "path: '/update'"), 'main Vue update route missing');
} else {
    admin2_assert(str_contains($bootstrap, "'platform_update' => false"), 'SaaS tenant must not have platform update capability');
}

fwrite(STDOUT, 'Admin 2.0 feature-parity smoke test passed for edition ' . APPDOWN_EDITION . ".\n");
