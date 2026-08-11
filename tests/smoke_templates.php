<?php
/**
 * Landing template smoke test.
 * Usage: php tests/smoke_templates.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/landing_templates.php';

function fail_test(string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assert_test(bool $condition, string $message): void {
    if (!$condition) fail_test($message);
}

$catalog = landing_template_catalog();
$expected = ['classic', 'glass', 'minimal', 'midnight', 'aurora'];
assert_test(array_keys($catalog) === $expected, 'template catalog keys changed unexpectedly');
assert_test(normalize_landing_template('unknown') === 'classic', 'unknown template must fall back to classic');
assert_test(landing_template_css('classic') === '', 'classic template should not override the original page CSS');

foreach (['glass', 'minimal', 'midnight', 'aurora'] as $name) {
    $css = landing_template_css($name);
    assert_test(strlen($css) > 100, "{$name} CSS is unexpectedly empty");
    assert_test(str_contains($css, 'body'), "{$name} CSS should style the page body");
}

// Minimal integration coverage for api/config.php.
$tmpRoot = sys_get_temp_dir() . '/appdown-template-smoke-' . bin2hex(random_bytes(4));
mkdir($tmpRoot, 0700, true);

// We do not mutate the repository database. Instead copy the relevant PHP tree
// to a temporary test root so api/config.php can use its normal relative paths.
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

// db.php's relative data path naturally points at the copied temporary root.
require_once $tmpRoot . '/includes/db.php';
require_once $tmpRoot . '/includes/helpers.php';
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
$headCss = (string)($data['custom_code']['head_css'] ?? '');
$templatePos = strpos($headCss, 'body{background:#070a12');
$userPos = strpos($headCss, '.user-override{color:red!important;}');
assert_test($templatePos !== false, 'selected template CSS was not injected');
assert_test($userPos !== false, 'user CSS disappeared');
assert_test($templatePos < $userPos, 'user CSS must come after template CSS so it can override the template');

fwrite(STDOUT, "Landing template smoke test passed.\n");
