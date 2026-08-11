#!/usr/bin/env php
<?php
/**
 * AppDown 维护入口。
 * 核心维护任务保持在 maintenance-core.php；这里对 --delete-orphans 做第二层保守校验，
 * 防止站点设置/自定义代码中的间接文件引用被误判为孤儿文件。
 */

if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only'); }

$requestedDelete = in_array('--delete-orphans', $argv, true);
$requestedQuiet = in_array('--quiet', $argv, true);

// 让核心只做“报告”，不直接删除孤儿；最终删除由本入口在更完整的引用检查后执行。
$argv = array_values(array_filter($argv, fn($arg) => $arg !== '--delete-orphans'));
if (!$requestedQuiet && !in_array('--quiet', $argv, true)) $argv[] = '--quiet';

require __DIR__ . '/maintenance-core.php';

if ($requestedDelete && !empty($report['orphans']) && isset($pdo, $projectRoot, $dataDir)) {
    // 扫描所有可能保存站点资源引用的业务表；统计日志不作为“活跃引用”。
    $referenceHaystack = '';
    $referenceTables = [
        'site_settings', 'custom_code', 'apps', 'app_downloads', 'app_images',
        'feature_cards', 'friend_links', 'app_attachments', 'image_library', 'keystores',
        'generated_apks', 'generated_ipas', 'generated_mobileconfigs'
    ];
    foreach ($referenceTables as $table) {
        try {
            foreach ($pdo->query('SELECT * FROM "' . $table . '"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                foreach ($row as $value) {
                    if (is_scalar($value) && $value !== null) $referenceHaystack .= "\n" . (string)$value;
                }
            }
        } catch (Throwable $e) {}
    }

    $uploadsRoot = realpath($projectRoot . '/uploads');
    foreach ($report['orphans'] as $orphan) {
        $rel = (string)($orphan['path'] ?? '');
        if ($rel === '' || !$uploadsRoot) continue;
        // 签名材料只报告、永不自动删除。
        if (str_starts_with($rel, 'uploads/keystores/') || str_starts_with($rel, 'uploads/certs/')) continue;
        // 文本字段里仍出现该路径，则视为有效引用。
        if ($referenceHaystack !== '' && strpos($referenceHaystack, $rel) !== false) continue;

        $candidate = realpath($projectRoot . '/' . $rel);
        if (!$candidate || !str_starts_with($candidate, $uploadsRoot . DIRECTORY_SEPARATOR) || !is_file($candidate)) continue;
        if (filemtime($candidate) >= time() - 7 * 86400) continue;
        if (@unlink($candidate)) $report['orphan_deleted'][] = $rel;
    }

    $reportPath = $dataDir . '/maintenance-report.json';
    file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($reportPath, 0600);
}

if (!$requestedQuiet) echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
