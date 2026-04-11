<?php
/**
 * APK 生成任务 API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

// GET — 查询任务/APK列表
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    // 单个任务状态（轮询用）
    if ($action === 'task_status') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少id'], 400);
        $stmt = $pdo->prepare('SELECT id, status, progress, progress_msg, result_url, result_size, error_msg, created_at, updated_at FROM build_tasks WHERE id = ?');
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if (!$task) json_response(['error' => '任务不存在'], 404);
        json_response($task);
    }

    // 任务列表
    if ($action === 'list_tasks') {
        $rows = $pdo->query('SELECT id, status, progress, progress_msg, result_url, result_size, error_msg, created_at, updated_at, params FROM build_tasks ORDER BY id DESC')->fetchAll();
        foreach ($rows as &$row) {
            $p = json_decode($row['params'], true);
            $row['app_name'] = $p['app_name'] ?? '';
            $row['package_name'] = $p['package_name'] ?? '';
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

    if ($action !== 'build') {
        json_response(['error' => '无效的action'], 400);
    }

    // 检查是否有正在构建的任务
    $running = $pdo->query("SELECT COUNT(*) FROM build_tasks WHERE status IN ('pending','building')")->fetchColumn();
    if ($running > 0) {
        json_response(['error' => '当前有任务正在构建中，请等待完成后再试'], 400);
    }

    // 验证参数
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

    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        json_response(['error' => '请输入有效的URL'], 400);
    }
    if (empty($appName)) {
        json_response(['error' => '请输入应用名称'], 400);
    }
    if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){1,}$/', $packageName)) {
        json_response(['error' => '包名格式不正确，例: com.example.app'], 400);
    }
    if ($versionCode < 1) {
        json_response(['error' => '版本代码必须 >= 1'], 400);
    }
    if (!$keystoreId) {
        json_response(['error' => '请选择签名密钥'], 400);
    }

    // 验证密钥存在
    $ks = $pdo->prepare('SELECT id FROM keystores WHERE id = ?');
    $ks->execute([$keystoreId]);
    if (!$ks->fetch()) {
        json_response(['error' => '签名密钥不存在'], 400);
    }

    // 验证路径安全
    foreach ([$iconUrl, $splashUrl] as $path) {
        if ($path && str_contains($path, '..')) {
            json_response(['error' => '文件路径不合法'], 400);
        }
    }

    // 创建任务
    $params = json_encode([
        'url' => $url,
        'app_name' => $appName,
        'package_name' => $packageName,
        'version_name' => $versionName,
        'version_code' => $versionCode,
        'icon_url' => $iconUrl,
        'splash_url' => $splashUrl,
        'splash_color' => $splashColor,
        'status_bar_color' => $statusBarColor,
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare('INSERT INTO build_tasks (status, params, keystore_id) VALUES (?, ?, ?)');
    $stmt->execute(['pending', $params, $keystoreId]);
    $taskId = $pdo->lastInsertId();

    // 后台启动 worker
    $phpBin = PHP_BINARY ?: 'php';
    $workerScript = realpath(__DIR__ . '/../../tools/build-worker.php');
    $cmd = sprintf(
        'nohup %s %s %d > /dev/null 2>&1 &',
        escapeshellarg($phpBin),
        escapeshellarg($workerScript),
        $taskId
    );
    exec($cmd);

    json_response(['ok' => true, 'task_id' => (int)$taskId]);
}

// PUT — 关联APK到应用
if ($method === 'PUT') {
    $data = get_json_input();
    $action = $data['action'] ?? '';

    if ($action === 'associate') {
        $apkId = (int)($data['apk_id'] ?? 0);
        $appId = $data['app_id'] !== null ? (int)$data['app_id'] : null;

        if (!$apkId) json_response(['error' => '缺少apk_id'], 400);

        if ($appId) {
            $app = $pdo->prepare('SELECT id FROM apps WHERE id = ?');
            $app->execute([$appId]);
            if (!$app->fetch()) json_response(['error' => '应用不存在'], 400);
        }

        $stmt = $pdo->prepare('UPDATE generated_apks SET app_id = ? WHERE id = ?');
        $stmt->execute([$appId, $apkId]);
        json_response(['ok' => true]);
    }

    json_response(['error' => '无效的action'], 400);
}

// DELETE — 删除APK记录和文件
if ($method === 'DELETE') {
    $data = get_json_input();
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_response(['error' => '缺少id'], 400);

    $stmt = $pdo->prepare('SELECT apk_url FROM generated_apks WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => '记录不存在'], 404);

    // 删除APK文件
    if ($row['apk_url']) {
        delete_upload($row['apk_url']);
    }

    // 删除关联的构建任务
    $taskStmt = $pdo->prepare('SELECT task_id FROM generated_apks WHERE id = ?');
    $taskStmt->execute([$id]);
    $taskRow = $taskStmt->fetch();
    if ($taskRow && $taskRow['task_id']) {
        $pdo->prepare('DELETE FROM build_tasks WHERE id = ?')->execute([$taskRow['task_id']]);
    }

    $pdo->prepare('DELETE FROM generated_apks WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
