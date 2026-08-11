<?php
/** AppDown Admin 2.0 read-only system overview. */
require_once __DIR__ . '/../../includes/init.php';
require_auth();
require_method('GET');

$pdo = get_db();
$root = dirname(__DIR__, 2);
$dbInfo = $pdo->query('PRAGMA database_list')->fetchAll();
$dbPath = '';
foreach ($dbInfo as $row) {
    if (($row['name'] ?? '') === 'main') { $dbPath = (string)($row['file'] ?? ''); break; }
}
$dbDir = $dbPath !== '' ? dirname($dbPath) : ($root . '/data');
$uploadsDir = $root . '/uploads';

$checks = [
    ['label' => 'PHP 版本', 'required' => '>= 8.0', 'current' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.0.0', '>=')],
    ['label' => 'PDO SQLite', 'required' => '已启用', 'current' => extension_loaded('pdo_sqlite') ? '已启用' : '未启用', 'ok' => extension_loaded('pdo_sqlite')],
    ['label' => 'Fileinfo', 'required' => '已启用', 'current' => extension_loaded('fileinfo') ? '已启用' : '未启用', 'ok' => extension_loaded('fileinfo')],
    ['label' => 'ZipArchive', 'required' => '推荐', 'current' => class_exists('ZipArchive') ? '已启用' : '未启用', 'ok' => class_exists('ZipArchive')],
    ['label' => 'OpenSSL', 'required' => '推荐', 'current' => extension_loaded('openssl') ? '已启用' : '未启用', 'ok' => extension_loaded('openssl')],
    ['label' => 'Sodium', 'required' => '推荐', 'current' => extension_loaded('sodium') ? '已启用' : '未启用', 'ok' => extension_loaded('sodium')],
    ['label' => 'cURL', 'required' => '推荐', 'current' => extension_loaded('curl') ? '已启用' : '未启用', 'ok' => extension_loaded('curl')],
    ['label' => 'GD', 'required' => '推荐', 'current' => extension_loaded('gd') ? '已启用' : '未启用', 'ok' => extension_loaded('gd')],
    ['label' => 'mbstring', 'required' => '推荐', 'current' => extension_loaded('mbstring') ? '已启用' : '未启用', 'ok' => extension_loaded('mbstring')],
    ['label' => '当前数据目录', 'required' => '可写', 'current' => is_writable($dbDir) ? '可写' : '不可写', 'ok' => is_writable($dbDir)],
    ['label' => 'uploads 目录', 'required' => '可写', 'current' => is_writable($uploadsDir) ? '可写' : '不可写', 'ok' => is_writable($uploadsDir)],
    ['label' => '安装锁', 'required' => '已锁定', 'current' => is_file($root . '/install/install.lock') ? '已锁定' : '未锁定', 'ok' => is_file($root . '/install/install.lock')],
];

$info = [
    ['label' => 'Edition', 'value' => defined('APPDOWN_EDITION') ? APPDOWN_EDITION : 'unknown'],
    ['label' => 'AppDown 版本', 'value' => defined('APPDOWN_VERSION') ? APPDOWN_VERSION : 'unknown'],
    ['label' => 'PHP SAPI', 'value' => php_sapi_name()],
    ['label' => '服务器软件', 'value' => (string)($_SERVER['SERVER_SOFTWARE'] ?? '未知')],
    ['label' => '操作系统', 'value' => PHP_OS . ' ' . php_uname('r')],
    ['label' => '服务器时间', 'value' => date('Y-m-d H:i:s')],
    ['label' => '时区', 'value' => date_default_timezone_get()],
    ['label' => '最大上传', 'value' => (string)ini_get('upload_max_filesize')],
    ['label' => 'POST 限制', 'value' => (string)ini_get('post_max_size')],
    ['label' => '内存限制', 'value' => (string)ini_get('memory_limit')],
    ['label' => '执行时限', 'value' => (string)ini_get('max_execution_time') . ' 秒'],
    ['label' => 'SQLite 版本', 'value' => (string)$pdo->query('SELECT sqlite_version()')->fetchColumn()],
    ['label' => '数据库大小', 'value' => ($dbPath && is_file($dbPath)) ? number_format(filesize($dbPath) / 1024, 1) . ' KB' : '未知'],
    ['label' => '数据库路径', 'value' => $dbPath ?: '未知'],
    ['label' => '项目根目录', 'value' => realpath($root) ?: $root],
];

$counts = [];
foreach ([
    'apps' => '应用',
    'app_downloads' => '下载按钮',
    'app_images' => '轮播截图',
    'feature_cards' => '特色卡片',
    'friend_links' => '友情链接',
    'app_attachments' => '附件',
    'image_library' => '媒体库图片',
] as $table => $label) {
    try { $count = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(); }
    catch (Throwable $e) { $count = 0; }
    $counts[] = ['label' => $label, 'value' => $count];
}

json_response(['checks' => $checks, 'info' => $info, 'counts' => $counts]);
