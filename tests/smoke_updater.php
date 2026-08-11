<?php
/** Online updater offline smoke test. */
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/includes/version.php';
require_once $root . '/includes/updater.php';
function up_fail(string $m): void { fwrite(STDERR, "FAIL: $m\n"); exit(1); }
function up_assert(bool $c, string $m): void { if (!$c) up_fail($m); }
up_assert(class_exists('ZipArchive'), 'ZipArchive missing');

// Release channel filtering must never mix main and SaaS.
if (APPDOWN_EDITION === 'main') {
    $fake = [
        ['tag_name'=>'saas-v99.0.0','draft'=>false,'prerelease'=>false],
        ['tag_name'=>'v1.2.1','draft'=>false,'prerelease'=>false],
        ['tag_name'=>'v1.3.0','draft'=>false,'prerelease'=>true],
    ];
    $selected = updater_select_latest_release($fake);
    up_assert(($selected['tag'] ?? '') === 'v1.2.1', 'main selected wrong release channel');
} else {
    $fake = [
        ['tag_name'=>'v99.0.0','draft'=>false,'prerelease'=>false],
        ['tag_name'=>'saas-v1.1.1','draft'=>false,'prerelease'=>false],
        ['tag_name'=>'saas-v1.2.0','draft'=>true,'prerelease'=>false],
    ];
    $selected = updater_select_latest_release($fake);
    up_assert(($selected['tag'] ?? '') === 'saas-v1.1.1', 'saas selected wrong release channel');
}

$tmp = sys_get_temp_dir() . '/appdown-updater-smoke-' . bin2hex(random_bytes(4));
mkdir($tmp . '/data', 0700, true); mkdir($tmp . '/uploads', 0700, true); mkdir($tmp . '/includes', 0700, true);
putenv('APPDOWN_UPDATE_TEST_ROOT=' . $tmp);
file_put_contents($tmp . '/README.md', 'old');
file_put_contents($tmp . '/old.php', '<?php echo "old";');
file_put_contents($tmp . '/uploads/user.bin', 'KEEP');
file_put_contents($tmp . '/data/app.db', 'KEEPDB');
file_put_contents($tmp . '/data/update-installed-manifest.json', json_encode(['files'=>['README.md','old.php']]));

$targetVersion = APPDOWN_EDITION === 'main' ? '9.9.9' : '8.8.8';
$tag = updater_release_prefix() . $targetVersion;
$zipPath = $tmp . '/release.zip';
$z = new ZipArchive();
up_assert($z->open($zipPath, ZipArchive::CREATE|ZipArchive::OVERWRITE) === true, 'cannot create fixture');
$prefix = 'nljie1103-appdown-deadbeef/';
$z->addFromString($prefix . 'README.md', 'new');
$z->addFromString($prefix . 'new.php', '<?php echo "new";');
$z->addFromString($prefix . 'includes/version.php', "<?php\ndefine('APPDOWN_EDITION', '" . APPDOWN_EDITION . "');\ndefine('APPDOWN_VERSION', '" . $targetVersion . "');\n");
$z->addFromString($prefix . 'uploads/user.bin', 'OVERWRITE-NOT-ALLOWED');
$z->addFromString($prefix . 'data/app.db', 'OVERWRITE-NOT-ALLOWED');
$z->close();

$r = updater_apply_release_archive($zipPath, ['tag'=>$tag,'version'=>$targetVersion]);
up_assert(($r['ok'] ?? false) === true, 'apply failed');
up_assert(file_get_contents($tmp . '/README.md') === 'new', 'program file not updated');
up_assert(is_file($tmp . '/new.php'), 'new program file not created');
up_assert(!file_exists($tmp . '/old.php'), 'old manifest program file not removed');
up_assert(file_get_contents($tmp . '/uploads/user.bin') === 'KEEP', 'uploads were overwritten');
up_assert(file_get_contents($tmp . '/data/app.db') === 'KEEPDB', 'database was overwritten');
up_assert(is_file($r['backup']), 'code backup missing');

// Path traversal must be rejected before any write.
$evil = $tmp . '/evil.zip';
$z = new ZipArchive(); $z->open($evil, ZipArchive::CREATE|ZipArchive::OVERWRITE);
$z->addFromString($prefix . 'README.md', 'x');
$z->addFromString($prefix . 'includes/version.php', "<?php define('APPDOWN_EDITION', '".APPDOWN_EDITION."'); define('APPDOWN_VERSION', '".$targetVersion."');");
$z->addFromString($prefix . '../escape.php', 'bad'); $z->close();
$rejected = false; try { updater_archive_map($evil, APPDOWN_EDITION, $targetVersion); } catch (RuntimeException $e) { $rejected = true; }
up_assert($rejected, 'path traversal ZIP accepted');

function up_rm(string $p): void { if (!is_dir($p)) return; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());} @rmdir($p); }
up_rm($tmp);
fwrite(STDOUT, "Online updater smoke test passed.\n");
