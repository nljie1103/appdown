<?php
/**
 * AppDown SaaS integration smoke test.
 * Usage: php tests/smoke_saas.php
 *
 * Runs against disposable files inside the checkout. CI must provide pdo_sqlite,
 * zip, openssl and mbstring.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/saas.php';
require_once $root . '/includes/db.php';
require_once $root . '/includes/helpers.php';
require_once $root . '/includes/security.php';
require_once $root . '/includes/landing_templates.php';

function saas_fail(string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function saas_assert(bool $condition, string $message): void {
    if (!$condition) saas_fail($message);
}

foreach (['pdo_sqlite', 'openssl', 'mbstring'] as $ext) {
    saas_assert(extension_loaded($ext), "required extension missing: {$ext}");
}
saas_assert(class_exists('ZipArchive'), 'ZipArchive extension missing');

$suffix = strtolower(substr(bin2hex(random_bytes(4)), 0, 8));
$alpha = 'alpha_' . $suffix;
$beta = 'beta_' . $suffix;
$lockPath = $root . '/install/install.lock';
$createdLock = false;

try {
    // Slug rules and system routes.
    saas_assert(validate_tenant_slug('admin')['ok'] === false, 'reserved slug admin must be rejected');
    saas_assert(validate_tenant_slug('super')['ok'] === false, 'reserved slug super must be rejected');
    saas_assert(validate_tenant_slug($alpha)['ok'] === true, 'valid tenant slug rejected');

    // Create two truly independent tenants.
    $a = create_tenant($alpha, 'Alpha Test', 'alpha-password-123');
    $b = create_tenant($beta, 'Beta Test', 'beta-password-123');
    saas_assert(($a['ok'] ?? false) === true, 'failed to create alpha tenant');
    saas_assert(($b['ok'] ?? false) === true, 'failed to create beta tenant');
    saas_assert(tenant_db_path($alpha) !== tenant_db_path($beta), 'tenant database paths are identical');
    saas_assert(tenant_upload_dir($alpha) !== tenant_upload_dir($beta), 'tenant upload paths are identical');
    saas_assert(is_file(tenant_db_path($alpha)), 'alpha SQLite database was not created');
    saas_assert(is_file(tenant_db_path($beta)), 'beta SQLite database was not created');

    // Store intentionally different data in each SQLite database.
    $alphaTenant = find_tenant($alpha, true);
    $betaTenant = find_tenant($beta, true);
    saas_assert(is_array($alphaTenant) && is_array($betaTenant), 'tenant registry lookup failed');

    set_current_tenant($alphaTenant);
    $alphaDb = get_db();
    set_setting($alphaDb, 'site_heading', 'Only Alpha');
    $alphaDb->prepare("INSERT INTO apps (slug,name,theme_color) VALUES (?,?,?)")
        ->execute(['alpha-app', 'Alpha App', '#123456']);
    $alphaCipher = encrypt_secret('alpha-secret');
    $alphaKeyPath = tenant_secret_key_path($alpha);
    saas_assert(is_file($alphaKeyPath), 'alpha master key not created');

    set_current_tenant($betaTenant);
    $betaDb = get_db();
    set_setting($betaDb, 'site_heading', 'Only Beta');
    $betaDb->prepare("INSERT INTO apps (slug,name,theme_color) VALUES (?,?,?)")
        ->execute(['beta-app', 'Beta App', '#654321']);
    $betaCipher = encrypt_secret('beta-secret');
    $betaKeyPath = tenant_secret_key_path($beta);
    saas_assert(is_file($betaKeyPath), 'beta master key not created');
    saas_assert($alphaKeyPath !== $betaKeyPath, 'tenant master key paths are identical');

    saas_assert($alphaDb->query("SELECT name FROM apps WHERE slug='alpha-app'")->fetchColumn() === 'Alpha App', 'alpha app missing from alpha DB');
    saas_assert($alphaDb->query("SELECT COUNT(*) FROM apps WHERE slug='beta-app'")->fetchColumn() == 0, 'beta app leaked into alpha DB');
    saas_assert($betaDb->query("SELECT name FROM apps WHERE slug='beta-app'")->fetchColumn() === 'Beta App', 'beta app missing from beta DB');
    saas_assert($betaDb->query("SELECT COUNT(*) FROM apps WHERE slug='alpha-app'")->fetchColumn() == 0, 'alpha app leaked into beta DB');

    // Secrets must be bound to the tenant master key.
    saas_assert(decrypt_secret($betaCipher) === 'beta-secret', 'beta secret decrypt failed');
    $crossRejected = false;
    try {
        decrypt_secret($alphaCipher);
    } catch (RuntimeException $e) {
        $crossRejected = true;
    }
    saas_assert($crossRejected, 'beta tenant unexpectedly decrypted alpha ciphertext');

    set_current_tenant($alphaTenant);
    saas_assert(decrypt_secret($alphaCipher) === 'alpha-secret', 'alpha secret decrypt failed');
    saas_assert(
        tenant_absolute_asset_url('uploads/apps/demo.apk', $alpha) === '/uploads/tenants/' . $alpha . '/apps/demo.apk',
        'legacy upload path was not mapped into alpha tenant'
    );
    saas_assert(
        tenant_absolute_asset_url('uploads/tenants/' . $beta . '/apps/demo.apk', $alpha) === '/uploads/tenants/' . $beta . '/apps/demo.apk',
        'already scoped path should remain stable for stored URLs'
    );

    // Real ZipArchive APK/IPA structure checks.
    $tmpDir = sys_get_temp_dir() . '/appdown-saas-zip-' . $suffix;
    mkdir($tmpDir, 0700, true);

    $apk = $tmpDir . '/valid.apk';
    $zip = new ZipArchive();
    saas_assert($zip->open($apk, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'failed to create APK fixture');
    $zip->addFromString('AndroidManifest.xml', 'manifest');
    $zip->addFromString('classes.dex', 'dex');
    $zip->close();
    saas_assert(validate_app_archive($apk, 'apk')['ok'] === true, 'valid APK fixture rejected');

    $badApk = $tmpDir . '/invalid.apk';
    $zip = new ZipArchive();
    $zip->open($badApk, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('classes.dex', 'dex');
    $zip->close();
    saas_assert(validate_app_archive($badApk, 'apk')['ok'] === false, 'invalid APK fixture accepted');

    $ipa = $tmpDir . '/valid.ipa';
    $zip = new ZipArchive();
    $zip->open($ipa, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('Payload/Test.app/Info.plist', '<plist/>');
    $zip->addFromString('Payload/Test.app/Test', 'binary');
    $zip->close();
    saas_assert(validate_app_archive($ipa, 'ipa')['ok'] === true, 'valid IPA fixture rejected');

    $evilZip = $tmpDir . '/evil.zip';
    $zip = new ZipArchive();
    $zip->open($evilZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('../outside.txt', 'nope');
    $zip->close();
    $zip = new ZipArchive();
    $zip->open($evilZip);
    $safe = validate_zip_safety($zip);
    $zip->close();
    saas_assert($safe['ok'] === false, 'ZIP path traversal fixture accepted');

    // Public config and tenant renderer integration.
    if (!file_exists($lockPath)) {
        file_put_contents($lockPath, "saas-smoke\n");
        $createdLock = true;
    }
    set_current_tenant($alphaTenant);
    set_setting($alphaDb, 'landing_template', 'midnight');
    $alphaDb->prepare("UPDATE custom_code SET code=? WHERE position='head_css'")
        ->execute(['.alpha-user-css{display:block;}']);
    clear_config_cache();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'appdown.test';
    $_GET['tenant'] = $alpha;

    ob_start();
    include $root . '/api/config.php';
    $configJson = ob_get_clean();
    $config = json_decode((string)$configJson, true);
    saas_assert(is_array($config), 'tenant config API returned invalid JSON');
    saas_assert(($config['tenant']['slug'] ?? '') === $alpha, 'config API resolved wrong tenant');
    saas_assert(count($config['apps'] ?? []) === 1 && ($config['apps'][0]['name'] ?? '') === 'Alpha App', 'config API leaked/wrong app data');
    $headCss = (string)($config['custom_code']['head_css'] ?? '');
    saas_assert(strpos($headCss, 'body{background:#070a12') !== false, 'tenant landing template CSS missing');
    saas_assert(strpos($headCss, '.alpha-user-css') > strpos($headCss, 'body{background:#070a12'), 'tenant custom CSS no longer overrides template');

    ob_start();
    include $root . '/tenant.php';
    $tenantHtml = ob_get_clean();
    saas_assert(strpos($tenantHtml, "fetch('/{$alpha}/api/config.php')") !== false, 'tenant renderer did not rewrite config API');
    saas_assert(strpos($tenantHtml, "'/{$alpha}/api/track.php'") !== false, 'tenant renderer did not rewrite tracking API');
    saas_assert(strpos($tenantHtml, 'href="/static/') !== false, 'tenant renderer did not root static assets');

    // Static guardrails for paths most likely to cause cross-tenant leaks.
    $checks = [
        'tools/build-worker.php' => ["appdown_upload_dir() . '/apks'", 'APPDOWN_TENANT'],
        'tools/ios-build-worker.php' => ["appdown_upload_dir() . '/ipas'", "'/data/ios-build/' . \$tenantSlug"],
        'admin/api/package-info.php' => ['appdown_upload_url_prefix()', 'realpath(appdown_upload_dir())'],
        'admin/api/backup.php' => ['tenant_slug', 'appdown_upload_dir()'],
        'admin/api/mobileconfig.php' => ["appdown_upload_dir() . '/mobileconfigs'"],
    ];
    foreach ($checks as $file => $needles) {
        $source = file_get_contents($root . '/' . $file);
        foreach ($needles as $needle) saas_assert(strpos($source, $needle) !== false, "isolation marker missing in {$file}: {$needle}");
    }

    fwrite(STDOUT, "SaaS tenant isolation + ZipArchive integration smoke test passed.\n");
} finally {
    set_current_tenant(null);
    if (isset($alpha) && find_tenant($alpha, true)) delete_tenant_permanently($alpha);
    if (isset($beta) && find_tenant($beta, true)) delete_tenant_permanently($beta);
    if (isset($tmpDir) && is_dir($tmpDir)) remove_tree($tmpDir);
    if ($createdLock && file_exists($lockPath)) @unlink($lockPath);
    // Remove central test DB when no tenants/super users remain.
    $controlPath = saas_control_db_path();
    if (file_exists($controlPath)) {
        try {
            $c = get_saas_db();
            $tenantCount = (int)$c->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
            $superCount = (int)$c->query('SELECT COUNT(*) FROM super_users')->fetchColumn();
            if ($tenantCount === 0 && $superCount === 0) {
                unset($c);
                @unlink($controlPath);
                @unlink($controlPath . '-wal');
                @unlink($controlPath . '-shm');
            }
        } catch (Throwable $ignored) {}
    }
}
