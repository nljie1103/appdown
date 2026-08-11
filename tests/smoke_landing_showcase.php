<?php
/** Verify Aurora is a real structural template, not a color alias. */
declare(strict_types=1);
$root = dirname(__DIR__);
function showcase_assert(bool $ok, string $message): void { if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
$jsPath = $root . '/static/landing-layouts.js';
$cssPath = $root . '/static/landing-showcase.css';
showcase_assert(is_file($jsPath), 'landing layout engine missing');
showcase_assert(is_file($cssPath), 'Aurora showcase stylesheet missing');
$js = (string)file_get_contents($jsPath);
$css = (string)file_get_contents($cssPath);
foreach (['adl-showcase', 'adl-showcase-hero', 'adl-showcase-brand', 'adl-showcase-controls', 'adl-showcase-stage'] as $marker) {
    showcase_assert(str_contains($js, $marker), "Aurora structural marker missing: {$marker}");
    showcase_assert(str_contains($css, '.' . $marker), "Aurora style marker missing: {$marker}");
}
showcase_assert(str_contains($js, "ensureStyleSheet('appdown-landing-showcase-css', '/static/landing-showcase.css')"), 'Aurora stylesheet is not loaded by the layout engine');
showcase_assert(str_contains($css, 'grid-template-columns'), 'Aurora does not define an independent structural grid');
showcase_assert(str_contains($css, '.adl-showcase-controls .app-tabs'), 'Aurora app selector is not structurally restyled');
fwrite(STDOUT, "Aurora showcase template smoke test passed.\n");
