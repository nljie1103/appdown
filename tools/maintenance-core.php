#!/usr/bin/env php
<?php
/**
 * AppDown 日常维护
 * - SQLite 自动备份 + 保留策略
 * - 统计/登录/构建日志清理
 * - 上传孤儿文件检测（默认只报告）
 * - Mobileconfig 证书到期提醒
 * - 版本历史摘要
 *
 * 用法:
 *   php tools/maintenance.php
 *   php tools/maintenance.php --delete-orphans
 *   php tools/maintenance.php --quiet
 */

if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only'); }

date_default_timezone_set('Asia/Shanghai');
require_once __DIR__ . '/../includes/saas.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/security.php';

$pdo = get_db();
$quiet = in_array('--quiet', $argv, true);
$deleteOrphans = in_array('--delete-orphans', $argv, true);
$projectRoot = realpath(__DIR__ . '/..');
$tenant = require_tenant_context();
$dataDir = appdown_data_dir();
$uploadsRoot = realpath(appdown_upload_dir()) ?: appdown_upload_dir();

$report = [
    'generated_at' => date('c'),
    'backup' => null,
    'cleanup' => [],
    'orphans' => [],
    'orphan_deleted' => [],
    'certificate_warnings' => [],
    'versions' => [],
];

// 1) SQLite 备份：先 checkpoint，再复制主库。个人站数据库较小，这种方式简单可靠。
try {
    $backupDir = $dataDir . '/backups';
    if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);
    $dbPath = appdown_db_path();
    if (is_file($dbPath)) {
        $backupPath = $backupDir . '/app-' . date('Ymd-His') . '.db';
        $backedUp = false;
        // SQLite VACUUM INTO 会生成一致性快照；老版本 SQLite 不支持时再回退到 checkpoint + copy。
        try {
            $pdo->exec('VACUUM INTO ' . $pdo->quote($backupPath));
            $backedUp = is_file($backupPath);
        } catch (Throwable $e) {
            $pdo->exec('PRAGMA wal_checkpoint(FULL)');
            $backedUp = copy($dbPath, $backupPath);
        }
        if ($backedUp) {
            @chmod($backupPath, 0600);
            $report['backup'] = basename($backupPath);
        }
    }
    // 保留最近 30 份自动备份。
    $backups = glob($backupDir . '/app-*.db') ?: [];
    usort($backups, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($backups, 30) as $old) @unlink($old);
} catch (Throwable $e) {
    $report['backup_error'] = $e->getMessage();
}

// 2) 数据生命周期。
$cleanupSql = [
    'page_visits' => "DELETE FROM page_visits WHERE created_at < datetime('now', '-90 days')",
    'download_clicks' => "DELETE FROM download_clicks WHERE created_at < datetime('now', '-180 days')",
    'login_attempts' => "DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-7 days')",
    'build_tasks' => "DELETE FROM build_tasks WHERE status IN ('done','failed') AND updated_at < datetime('now', '-30 days')",
];
foreach ($cleanupSql as $name => $sql) {
    try { $report['cleanup'][$name] = (int)$pdo->exec($sql); }
    catch (Throwable $e) { $report['cleanup'][$name] = 'error: ' . $e->getMessage(); }
}

// 清理旧 worker 日志。
$logsDeleted = 0;
foreach (['build_worker_*.log', 'ipa_build_worker_*.log', 'android_install.log', 'ios_install.log', 'ios_xcode_install.log'] as $pattern) {
    foreach (glob($dataDir . '/' . $pattern) ?: [] as $file) {
        if (is_file($file) && filemtime($file) < time() - 30 * 86400) { if (@unlink($file)) $logsDeleted++; }
    }
}
$report['cleanup']['logs'] = $logsDeleted;

// 3) 收集数据库引用的 uploads 路径。
$referenced = [];
$addRef = function ($value) use (&$referenced): void {
    $value = trim((string)$value);
    if ($value === '' || preg_match('#^https?://#i', $value)) return;
    $value = ltrim(str_replace('\\', '/', $value), '/');
    if (str_starts_with($value, 'uploads/')) $referenced[$value] = true;
};

$queries = [
    "SELECT icon_url FROM apps UNION ALL SELECT ios_ipa_url FROM apps UNION ALL SELECT android_apk_url FROM apps UNION ALL SELECT mc_file_url FROM apps",
    'SELECT image_url FROM app_images',
    'SELECT file_url FROM app_attachments',
    'SELECT file_url FROM image_library',
    'SELECT file_url FROM keystores',
    'SELECT apk_url AS file_url FROM generated_apks',
    'SELECT ipa_url AS file_url FROM generated_ipas',
    'SELECT file_path AS file_url FROM generated_mobileconfigs',
];
foreach ($queries as $sql) {
    try {
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_NUM) as $row) foreach ($row as $value) $addRef($value);
    } catch (Throwable $e) {}
}

if (is_dir($uploadsRoot)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsRoot, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $real = $file->getRealPath();
        $rel = appdown_upload_url_prefix() . '/' . ltrim(str_replace('\\', '/', substr($real, strlen($uploadsRoot))), '/');
        if (!isset($referenced[$rel])) {
            $report['orphans'][] = ['path' => $rel, 'size' => $file->getSize(), 'mtime' => date('c', $file->getMTime())];
            // 自动删除必须显式传参，且至少 7 天未修改，防止误删刚上传尚未关联的文件。
            if ($deleteOrphans && $file->getMTime() < time() - 7 * 86400) {
                if (@unlink($real)) $report['orphan_deleted'][] = $rel;
            }
        }
    }
}

// 4) 证书到期提醒（30 天内或已过期）。
try {
    $rows = $pdo->query("SELECT id, name, cert_issuer, cert_expires, is_global FROM mc_certificates WHERE cert_expires <> '' ORDER BY cert_expires ASC")->fetchAll();
    foreach ($rows as $row) {
        $ts = strtotime($row['cert_expires']);
        if (!$ts) continue;
        $days = (int)floor(($ts - time()) / 86400);
        if ($days <= 30) {
            $report['certificate_warnings'][] = [
                'id' => (int)$row['id'], 'name' => $row['name'], 'issuer' => $row['cert_issuer'],
                'expires' => $row['cert_expires'], 'days_left' => $days, 'is_global' => (bool)$row['is_global'],
            ];
        }
    }
} catch (Throwable $e) {}

// 5) 版本历史摘要：保留生成记录，不因清理 build_tasks 丢失历史。
try {
    $report['versions']['apk'] = $pdo->query("SELECT id, app_id, app_name, package_name AS bundle, version_name AS version, version_code AS build, apk_url AS file, created_at FROM generated_apks ORDER BY created_at DESC, id DESC LIMIT 100")->fetchAll();
    $report['versions']['ipa'] = $pdo->query("SELECT id, app_id, app_name, bundle_id AS bundle, version_name AS version, version_code AS build, ipa_url AS file, created_at FROM generated_ipas ORDER BY created_at DESC, id DESC LIMIT 100")->fetchAll();
    $report['versions']['attachments'] = $pdo->query("SELECT f.id, f.app_id, a.name AS app_name, p.name AS platform, f.version, f.file_url AS file, f.created_at FROM app_attachments f LEFT JOIN apps a ON a.id=f.app_id LEFT JOIN app_platforms p ON p.id=f.platform_id ORDER BY f.created_at DESC, f.id DESC LIMIT 200")->fetchAll();
} catch (Throwable $e) {
    $report['versions_error'] = $e->getMessage();
}

$reportPath = $dataDir . '/maintenance-report.json';
file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
@chmod($reportPath, 0600);
set_setting($pdo, 'maintenance_last_success', date('Y-m-d H:i:s'));

if (!$quiet) echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
