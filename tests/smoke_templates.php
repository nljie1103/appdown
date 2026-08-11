<?php
/**
 * Landing template 2.0 smoke test.
 * Usage: php tests/smoke_templates.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);

function fail_test(string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assert_test(bool $condition, string $message): void {
    if (!$condition) fail_test($message);
}

$tmpRoot = sys_get_temp_dir() . '/appdown-template-smoke-' . bin2hex(random_bytes(4));
mkdir($tmpRoot, 0700, true);

$copyItems = ['api', 'includes', 'install'];
foreach ($copyItems as $item) {
    $src = $root . '/' . $item;
    $dst = $tmpRoot . '/' . $item;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $file) {
        $target = $dst . substr($file->getPathname(), strlen($src));
        if ($file->isDir()) {
            if (!is_dir($target)) mkdir($target, 0700, true);
        } else {
            $parent = dirname($target);
            if (!is_dir($parent)) mkdir($parent, 0700, true);
            copy($file->getPathname(), $target);
        }
    }
}
mkdir($tmpRoot . '/data', 0700, true);
file_put_contents($tmpRoot . '/install/install.lock', "template-smoke\n");

require_once $tmpRoot . '/includes/landing_templates.php';
require_once $tmpRoot . '/includes/db.php';
require_once $tmpRoot . '/includes/helpers.php';

$catalog = landing_template_catalog();
$expected = ['classic', 'glass', 'minimal', 'midnight', 'aurora', 'store', 'bento', 'split', 'mobile'];
assert_test(array_keys($catalog) === $expected, 'template 2.0 catalog keys changed unexpectedly');
assert_test(normalize_landing_template('unknown') === 'classic', 'unknown template must fall back to classic');
assert_test(landing_template_layout('unknown') === 'classic', 'unknown template layout must fall back to classic');
assert_test(landing_template_css('classic') === '', 'classic template should preserve original page CSS');

$expectedLayouts = [
    'classic' => 'classic',
    'glass' => 'spotlight',
    'minimal' => 'editorial',
    'midnight' => 'console',
    'aurora' => 'showcase',
    'store' => 'store',
    'bento' => 'bento',
    'split' => 'split',
    'mobile' => 'mobile',
];
foreach ($expectedLayouts as $name => $layout) {
    assert_test(landing_template_layout($name) === $layout, "{$name} layout mismatch");
    assert_test(($catalog[$name]['layout'] ?? '') === $layout, "{$name} catalog layout missing");
    assert_test(trim((string)($catalog[$name]['description'] ?? '')) !== '', "{$name} description missing");
}
foreach (array_diff($expected, ['classic']) as $name) {
    $css = landing_template_css($name);
    assert_test(strlen($css) > 20, "{$name} theme CSS is unexpectedly empty");
    assert_test(str_contains($css, 'body'), "{$name} theme CSS should define body tokens/background");
}

$layoutJs = $root . '/static/landing-layouts.js';
$layoutCss = $root . '/static/landing-layouts.css';
$index = $root . '/index.html';
assert_test(is_file($layoutJs), 'structural layout JS is missing');
assert_test(is_file($layoutCss), 'structural layout CSS is missing');
assert_test(is_file($index), 'landing index is missing');
$js = (string)file_get_contents($layoutJs);
$css = (string)file_get_contents($layoutCss);
$indexHtml = (string)file_get_contents($index);
assert_test(str_contains($js, 'AppDownLandingLayouts'), 'layout JS does not expose AppDownLandingLayouts');
foreach (['adl-console-grid', 'adl-store-grid', 'adl-bento-top', 'adl-split', 'adl-mobile-shell'] as $marker) {
    assert_test(str_contains($js . $css, $marker), "layout marker {$marker} missing");
}
assert_test(str_contains($indexHtml, 'static/landing-layouts.css'), 'index does not load structural layout CSS');
assert_test(str_contains($indexHtml, 'static/landing-layouts.js'), 'index does not load structural layout JS');
assert_test(str_contains($indexHtml, 'AppDownLandingLayouts?.apply'), 'index does not apply selected structural template');
assert_test(str_contains($indexHtml, 'AppDownLandingLayouts?.syncActive'), 'active app summary is not synced after tab changes');

$pdo = get_db();
set_setting($pdo, 'landing_template', 'midnight');
set_setting($pdo, 'site_title', 'Template Smoke');
$pdo->prepare("UPDATE custom_code SET code = ? WHERE position = 'head_css'")
    ->execute(['.user-override{color:red!important;}']);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = 'on';
chdir($tmpRoot);

ob_start();
include $tmpRoot . '/api/config.php';
$json = ob_get_clean();
$data = json_decode($json, true);
assert_test(is_array($data), 'config API did not return JSON');
assert_test(($data['site']['landing_template'] ?? '') === 'midnight', 'config API did not expose selected template');
assert_test(($data['site']['landing_layout'] ?? '') === 'console', 'config API did not expose selected layout');
$headCss = (string)($data['custom_code']['head_css'] ?? '');
$templatePos = strpos($headCss, 'body{--adl-accent:#60a5fa');
$userPos = strpos($headCss, '.user-override{color:red!important;}');
assert_test($templatePos !== false, 'selected template CSS was not injected');
assert_test($userPos !== false, 'user CSS disappeared');
assert_test($templatePos < $userPos, 'user CSS must come after template CSS so it can override the template');

fwrite(STDOUT, "Landing template 2.0 smoke test passed.\n");
