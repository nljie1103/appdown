#!/usr/bin/env php
<?php
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only'); }
set_time_limit(0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/version.php';
if (defined('APPDOWN_EDITION') && APPDOWN_EDITION === 'saas') require_once __DIR__ . '/../includes/saas.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$action = $argv[1] ?? '';
$logFile = $argv[2] ?? '';
if (!in_array($action, ['install', 'uninstall'], true) || $logFile === '') {
    fwrite(STDERR, "usage: template-builder-env-worker.php <install|uninstall> <log>\n");
    exit(64);
}
$pdo = get_db();
set_setting($pdo, 'template_builder_install_status', 'running');
@file_put_contents($logFile, '');

$runner = '/usr/local/libexec/appdown-template-builder-runner';
$cmd = 'sudo -n ' . escapeshellarg($runner) . ' ' . escapeshellarg($action) . ' > ' . escapeshellarg($logFile) . ' 2>&1';
exec($cmd, $out, $code);
if ($code === 0) {
    file_put_contents($logFile, "\n[完成] Template Builder {$action} 成功\n", FILE_APPEND);
    set_setting($pdo, 'template_builder_install_status', $action === 'install' ? 'done' : 'idle');
    exit(0);
}
file_put_contents($logFile, "\n[失败] runner 退出码 {$code}\n", FILE_APPEND);
set_setting($pdo, 'template_builder_install_status', 'failed');
exit($code ?: 1);
