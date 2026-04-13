<?php
/**
 * 生成任务 API（APK + IPA）
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

// GET — 查询任务/列表
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    // 单个任务状态（轮询用）
    if ($action === 'task_status') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少id'], 400);
        $stmt = $pdo->prepare('SELECT id, build_type, status, progress, progress_msg, result_url, result_size, error_msg, created_at, updated_at FROM build_tasks WHERE id = ?');
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task) json_response(['error' => '任务不存在'], 404);
        json_response($task);
    }

    // APK 任务列表
    if ($action === 'list_tasks') {
        $rows = $pdo->query("SELECT id, build_type, status, progress, progress_msg, result_url, result_size, error_msg, created_at, updated_at, params FROM build_tasks WHERE build_type = 'apk' ORDER BY id DESC")->fetchAll();
        foreach ($rows as &$row) {
            $p = json_decode($row['params'], true);
            $row['app_name'] = $p['app_name'] ?? '';
            $row['package_name'] = $p['package_name'] ?? '';
            unset($row['params']);
        }
        json_response($rows);
    }

    // IPA 任务列表
    if ($action === 'list_ipa_tasks') {
        $rows = $pdo->query("SELECT id, build_type, status, progress, progress_msg, result_url, result_size, error_msg, created_at, updated_at, params FROM build_tasks WHERE build_type = 'ipa' ORDER BY id DESC")->fetchAll();
        foreach ($rows as &$row) {
            $p = json_decode($row['params'], true);
            $row['app_name'] = $p['app_name'] ?? '';
            $row['bundle_id'] = $p['bundle_id'] ?? '';
            unset($row['params']);
        }
        json_response($rows);
    }

    // APK列表
    if ($action === 'list_apks' || $action === '') {
        $rows = $pdo->query(
            'SELECT g.*, k.name as keystore_name, a.name as linked_app_name ' .
            'FROM generated_apks g ' .
            'LEFT JOIN keystores k ON g.keystore_id = k.id ' .
            'LEFT JOIN apps a ON g.app_id = a.id ' .
            'ORDER BY g.id DESC'
        )->fetchAll();
        json_response($rows);
    }

    // IPA列表
    if ($action === 'list_ipas') {
        $rows = $pdo->query(
            'SELECT g.*, a.name as linked_app_name ' .
            'FROM generated_ipas g ' .
            'LEFT JOIN apps a ON g.app_id = a.id ' .
            'ORDER BY g.id DESC'
        )->fetchAll();
        json_response($rows);
    }

    json_response(['error' => '无效的action'], 400);
}

csrf_validate();

// POST — 创建构建任务
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!$action) {
        $data = get_json_input();
        $action = $data['action'] ?? '';
    } else {
        $data = $_POST;
    }

    // 取消构建任务（通用，APK/IPA共用）
    if ($action === 'cancel_task') {
        $taskId = (int)($data['task_id'] ?? 0);
        if (!$taskId) json_response(['error' => '缺少task_id'], 400);

        $stmt = $pdo->prepare('SELECT id, status, pid FROM build_tasks WHERE id = ?');
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if (!$task) json_response(['error' => '任务不存在'], 404);
        if (!in_array($task['status'], ['pending', 'building'])) {
            json_response(['error' => '任务已结束，无需取消'], 400);
        }

        $stmt = $pdo->prepare("UPDATE build_tasks SET status = 'failed', error_msg = '用户手动取消', progress_msg = '已取消', updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$taskId]);

        if ($task['status'] === 'building' && $task['pid'] > 0) {
            $pid = (int)$task['pid'];
            @exec('kill -9 -' . $pid . ' 2>/dev/null');
            @exec('kill -9 ' . $pid . ' 2>/dev/null');
        }

        json_response(['ok' => true]);
    }

    // ========== APK 构建 ==========
    if ($action === 'build') {
        $pdo->beginTransaction();
        try {
            // 自动清理僵尸任务
            $pdo->exec("UPDATE build_tasks SET status = 'failed', error_msg = '构建超时，已自动标记为失败', progress_msg = '超时失败', updated_at = datetime('now') WHERE status = 'building' AND build_type = 'apk' AND updated_at < datetime('now', '-15 minutes')");
            $pdo->exec("UPDATE build_tasks SET status = 'failed', error_msg = 'Worker未启动，已自动标记为失败', progress_msg = '启动失败', updated_at = datetime('now') WHERE status = 'pending' AND build_type = 'apk' AND created_at < datetime('now', '-15 minutes')");

            // 检查是否有正在构建的 APK 任务
            $running = $pdo->query("SELECT COUNT(*) FROM build_tasks WHERE status IN ('pending','building') AND build_type = 'apk'")->fetchColumn();
            if ($running > 0) {
                $pdo->rollBack();
                json_response(['error' => '当前有 APK 任务正在构建中，请等待完成后再试'], 400);
            }

            $url = trim($data['url'] ?? '');
            $appName = trim($data['app_name'] ?? '');
            $packageName = trim($data['package_name'] ?? '');
            $versionName = trim($data['version_name'] ?? '1.0.0');
            $versionCode = (int)($data['version_code'] ?? 1);
            $keystoreId = (int)($data['keystore_id'] ?? 0);
            $iconUrl = trim($data['icon_url'] ?? '');
            $splashUrl = trim($data['splash_url'] ?? '');
            $splashColor = trim($data['splash_color'] ?? '#FFFFFF');
            $statusBarColor = trim($data['status_bar_color'] ?? '#000000');

            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) json_response(['error' => '请输入有效的URL'], 400);
            if (empty($appName)) json_response(['error' => '请输入应用名称'], 400);
            if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){1,}$/', $packageName)) json_response(['error' => '包名格式不正确，例: com.example.app'], 400);
            if ($versionCode < 1) json_response(['error' => '版本代码必须 >= 1'], 400);
            if (!$keystoreId) json_response(['error' => '请选择签名密钥'], 400);

            $ks = $pdo->prepare('SELECT id FROM keystores WHERE id = ?');
            $ks->execute([$keystoreId]);
            if (!$ks->fetch()) json_response(['error' => '签名密钥不存在'], 400);

            foreach ([$iconUrl, $splashUrl] as $path) {
                if ($path && strpos($path, '..') !== false) json_response(['error' => '文件路径不合法'], 400);
            }

            $params = json_encode([
                'url' => $url, 'app_name' => $appName, 'package_name' => $packageName,
                'version_name' => $versionName, 'version_code' => $versionCode,
                'icon_url' => $iconUrl, 'splash_url' => $splashUrl,
                'splash_color' => $splashColor, 'status_bar_color' => $statusBarColor,
            ], JSON_UNESCAPED_UNICODE);

            $stmt = $pdo->prepare("INSERT INTO build_tasks (build_type, status, params, keystore_id) VALUES ('apk', 'pending', ?, ?)");
            $stmt->execute([$params, $keystoreId]);
            $taskId = $pdo->lastInsertId();
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            json_response(['error' => '创建任务失败: ' . $e->getMessage()], 500);
        }

        // 后台启动 worker
        $phpBin = PHP_BINDIR . '/php';
        if (!@file_exists($phpBin)) $phpBin = 'php';
        $workerScript = realpath(__DIR__ . '/../../tools/build-worker.php');
        if (!$workerScript) json_response(['error' => 'build-worker.php 不存在'], 500);
        $dataDir = realpath(__DIR__ . '/../../data') ?: (__DIR__ . '/../../data');
        $debugLog = $dataDir . '/build_worker_' . $taskId . '.log';
        $cmd = sprintf('nohup %s %s %d > %s 2>&1 & echo $!', escapeshellarg($phpBin), escapeshellarg($workerScript), $taskId, escapeshellarg($debugLog));
        $pidOutput = [];
        exec($cmd, $pidOutput);
        $workerPid = (int)($pidOutput[0] ?? 0);
        if ($workerPid > 0) {
            $pdo->prepare('UPDATE build_tasks SET pid = ? WHERE id = ?')->execute([$workerPid, $taskId]);
        }

        json_response(['ok' => true, 'task_id' => (int)$taskId]);
    }

    // ========== IPA 构建 ==========
    if ($action === 'build_ipa') {
        $pdo->beginTransaction();
        try {
            // 自动清理僵尸 IPA 任务
            $pdo->exec("UPDATE build_tasks SET status = 'failed', error_msg = '构建超时', progress_msg = '超时失败', updated_at = datetime('now') WHERE status = 'building' AND build_type = 'ipa' AND updated_at < datetime('now', '-20 minutes')");
            $pdo->exec("UPDATE build_tasks SET status = 'failed', error_msg = 'Worker未启动', progress_msg = '启动失败', updated_at = datetime('now') WHERE status = 'pending' AND build_type = 'ipa' AND created_at < datetime('now', '-15 minutes')");

            // 检查是否有正在构建的 IPA 任务（IPA和APK各允许一个）
            $running = $pdo->query("SELECT COUNT(*) FROM build_tasks WHERE status IN ('pending','building') AND build_type = 'ipa'")->fetchColumn();
            if ($running > 0) {
                $pdo->rollBack();
                json_response(['error' => '当前有 IPA 任务正在构建中，请等待完成后再试'], 400);
            }

            $url = trim($data['url'] ?? '');
            $appName = trim($data['app_name'] ?? '');
            $bundleId = trim($data['bundle_id'] ?? '');
            $versionName = trim($data['version_name'] ?? '1.0.0');
            $versionCode = (int)($data['version_code'] ?? 1);
            $iconUrl = trim($data['icon_url'] ?? '');
            $statusBarColor = trim($data['status_bar_color'] ?? '#000000');

            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) json_response(['error' => '请输入有效的URL'], 400);
            if (empty($appName)) json_response(['error' => '请输入应用名称'], 400);
            if (!preg_match('/^[a-z][a-z0-9_-]*(\.[a-z][a-z0-9_-]*){1,}$/', $bundleId)) json_response(['error' => 'Bundle ID 格式不正确，例: com.example.app'], 400);
            if ($versionCode < 1) json_response(['error' => '版本代码必须 >= 1'], 400);
            if ($iconUrl && strpos($iconUrl, '..') !== false) json_response(['error' => '文件路径不合法'], 400);

            $params = json_encode([
                'url' => $url, 'app_name' => $appName, 'bundle_id' => $bundleId,
                'version_name' => $versionName, 'version_code' => $versionCode,
                'icon_url' => $iconUrl, 'status_bar_color' => $statusBarColor,
            ], JSON_UNESCAPED_UNICODE);

            $stmt = $pdo->prepare("INSERT INTO build_tasks (build_type, status, params, keystore_id) VALUES ('ipa', 'pending', ?, 0)");
            $stmt->execute([$params]);
            $taskId = $pdo->lastInsertId();
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            json_response(['error' => '创建任务失败: ' . $e->getMessage()], 500);
        }

        $phpBin = PHP_BINDIR . '/php';
        if (!@file_exists($phpBin)) $phpBin = 'php';
        $workerScript = realpath(__DIR__ . '/../../tools/ios-build-worker.php');
        if (!$workerScript) json_response(['error' => 'ios-build-worker.php 不存在'], 500);
        $dataDir = realpath(__DIR__ . '/../../data') ?: (__DIR__ . '/../../data');
        $debugLog = $dataDir . '/ipa_build_worker_' . $taskId . '.log';
        $cmd = sprintf('nohup %s %s %d > %s 2>&1 & echo $!', escapeshellarg($phpBin), escapeshellarg($workerScript), $taskId, escapeshellarg($debugLog));
        $pidOutput = [];
        exec($cmd, $pidOutput);
        $workerPid = (int)($pidOutput[0] ?? 0);
        if ($workerPid > 0) {
            $pdo->prepare('UPDATE build_tasks SET pid = ? WHERE id = ?')->execute([$workerPid, $taskId]);
        }

        json_response(['ok' => true, 'task_id' => (int)$taskId]);
    }

    json_response(['error' => '无效的action'], 400);
}

// PUT — 关联/重命名
if ($method === 'PUT') {
    $data = get_json_input();
    $action = $data['action'] ?? '';
    $type = $data['type'] ?? 'apk';

    if ($action === 'associate') {
        $itemId = (int)($data['apk_id'] ?? $data['ipa_id'] ?? 0);
        $appId = isset($data['app_id']) && $data['app_id'] !== null && $data['app_id'] !== '' ? (int)$data['app_id'] : null;
        $platformId = (int)($data['platform_id'] ?? 0);
        $version = trim($data['version'] ?? '1.0');
        if (!$itemId) json_response(['error' => '缺少id'], 400);
        if (!$appId || !$platformId) json_response(['error' => '请选择应用和附件分类'], 400);

        // 验证应用存在
        $app = $pdo->prepare('SELECT id FROM apps WHERE id = ?');
        $app->execute([$appId]);
        if (!$app->fetch()) json_response(['error' => '应用不存在'], 400);

        // 验证附件分类属于该应用
        $platCheck = $pdo->prepare('SELECT id FROM app_platforms WHERE id = ? AND app_id = ?');
        $platCheck->execute([$platformId, $appId]);
        if (!$platCheck->fetch()) json_response(['error' => '附件分类不属于该应用'], 400);

        $table = ($type === 'ipa') ? 'generated_ipas' : 'generated_apks';
        $urlCol = ($type === 'ipa') ? 'ipa_url' : 'apk_url';
        $sizeCol = ($type === 'ipa') ? 'ipa_size' : 'apk_size';

        // 获取文件信息
        $fileStmt = $pdo->prepare("SELECT app_name, $urlCol, $sizeCol FROM $table WHERE id = ?");
        $fileStmt->execute([$itemId]);
        $fileRow = $fileStmt->fetch();
        if (!$fileRow) json_response(['error' => '记录不存在'], 404);

        $fileUrl = $fileRow[$urlCol];
        $fileSize = $fileRow[$sizeCol];

        // 添加到附件库（去重：同文件+同分类则更新）
        $existStmt = $pdo->prepare('SELECT id FROM app_attachments WHERE file_url = ? AND platform_id = ?');
        $existStmt->execute([$fileUrl, $platformId]);
        $existRow = $existStmt->fetch();
        if ($existRow) {
            $pdo->prepare("UPDATE app_attachments SET version = ?, file_size = ?, changelog = ?, updated_at = datetime('now') WHERE id = ?")->execute([$version, $fileSize, $fileRow['app_name'], $existRow['id']]);
        } else {
            $pdo->prepare('INSERT INTO app_attachments (app_id, platform_id, version, file_url, file_size, changelog) VALUES (?, ?, ?, ?, ?, ?)')->execute([$appId, $platformId, $version, $fileUrl, $fileSize, $fileRow['app_name']]);
        }

        // 更新关联记录
        $stmt = $pdo->prepare("UPDATE $table SET app_id = ? WHERE id = ?");
        $stmt->execute([$appId, $itemId]);
        json_response(['ok' => true]);
    }

    if ($action === 'rename') {
        $itemId = (int)($data['apk_id'] ?? $data['ipa_id'] ?? 0);
        $newName = trim($data['new_name'] ?? '');
        if (!$itemId || !$newName) json_response(['error' => '缺少参数'], 400);

        $table = ($type === 'ipa') ? 'generated_ipas' : 'generated_apks';
        $urlCol = ($type === 'ipa') ? 'ipa_url' : 'apk_url';
        $ext = ($type === 'ipa') ? '.ipa' : '.apk';

        $stmt = $pdo->prepare("SELECT $urlCol FROM $table WHERE id = ?");
        $stmt->execute([$itemId]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => '记录不存在'], 404);
        if (empty($row[$urlCol])) json_response(['error' => '文件不存在'], 404);

        $oldFullPath = __DIR__ . '/../../' . $row[$urlCol];
        if (!file_exists($oldFullPath)) json_response(['error' => '原文件不存在'], 404);

        $safeName = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}_.-]/u', '_', $newName);
        $safeName = trim(preg_replace('/_+/', '_', $safeName), '_') ?: 'app';
        if (substr(strtolower($safeName), -strlen($ext)) !== $ext) $safeName .= $ext;

        $dir = dirname($oldFullPath);
        $newFullPath = $dir . '/' . $safeName;
        if (file_exists($newFullPath) && $newFullPath !== $oldFullPath) {
            $base = pathinfo($safeName, PATHINFO_FILENAME);
            $safeName = $base . '_' . time() . $ext;
            $newFullPath = $dir . '/' . $safeName;
        }

        if (!rename($oldFullPath, $newFullPath)) json_response(['error' => '重命名失败'], 500);

        $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/../..')) . '/';
        $newRelative = str_replace($projectRoot, '', str_replace('\\', '/', realpath($newFullPath)));

        $pdo->prepare("UPDATE $table SET $urlCol = ? WHERE id = ?")->execute([$newRelative, $itemId]);
        json_response(['ok' => true, 'new_url' => $newRelative]);
    }

    json_response(['error' => '无效的action'], 400);
}

// DELETE — 删除记录和文件
if ($method === 'DELETE') {
    $data = get_json_input();
    $id = (int)($data['id'] ?? 0);
    $type = $data['type'] ?? 'apk';
    if (!$id) json_response(['error' => '缺少id'], 400);

    if ($type === 'ipa') {
        $stmt = $pdo->prepare('SELECT ipa_url, task_id FROM generated_ipas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => '记录不存在'], 404);
        if ($row['ipa_url']) delete_upload($row['ipa_url']);
        if ($row['task_id']) $pdo->prepare('DELETE FROM build_tasks WHERE id = ?')->execute([$row['task_id']]);
        $pdo->prepare('DELETE FROM generated_ipas WHERE id = ?')->execute([$id]);
    } else {
        $stmt = $pdo->prepare('SELECT apk_url, task_id FROM generated_apks WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => '记录不存在'], 404);
        if ($row['apk_url']) delete_upload($row['apk_url']);
        if ($row['task_id']) $pdo->prepare('DELETE FROM build_tasks WHERE id = ?')->execute([$row['task_id']]);
        $pdo->prepare('DELETE FROM generated_apks WHERE id = ?')->execute([$id]);
    }

    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
