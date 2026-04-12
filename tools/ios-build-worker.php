#!/usr/bin/env php
<?php
/**
 * IPA 后台构建 Worker（仅CLI运行）
 * 用法: php ios-build-worker.php <task_id>
 *
 * 通过 SSH 连接 Docker-OSX 容器，使用 xcodebuild 编译 IPA。
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

set_time_limit(0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$taskId = (int)($argv[1] ?? 0);
if (!$taskId) {
    fwrite(STDERR, "Usage: php ios-build-worker.php <task_id>\n");
    exit(1);
}

$pdo = get_db();
$buildDir = '';
$localBuildDir = '';

// SSH 配置
$SSH_PORT = get_setting($pdo, 'custom_ios_ssh_port') ?: '50922';
$SSH_HOST = 'localhost';
$SSH_USER = 'user';
$SSH_OPTS = "-o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -p $SSH_PORT";

function ssh_exec(string $command): array {
    global $SSH_OPTS, $SSH_USER, $SSH_HOST;
    $fullCmd = "ssh $SSH_OPTS $SSH_USER@$SSH_HOST " . escapeshellarg($command) . " 2>&1";
    $output = [];
    $retCode = 0;
    exec($fullCmd, $output, $retCode);
    return ['output' => $output, 'code' => $retCode];
}

try {
    // 加载任务
    $stmt = $pdo->prepare('SELECT * FROM build_tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task || $task['status'] !== 'pending') {
        fwrite(STDERR, "Task $taskId not found or not pending\n");
        exit(1);
    }

    $params = json_decode($task['params'], true);
    if (!$params) {
        fail_task($pdo, $taskId, '无效的构建参数');
        exit(1);
    }

    // 标记为构建中
    update_task($pdo, $taskId, ['status' => 'building', 'progress' => 5, 'progress_msg' => '准备构建环境...', 'pid' => getmypid()]);

    // 检查 SSH 连接
    $result = ssh_exec('echo ok');
    if ($result['code'] !== 0 || trim($result['output'][0] ?? '') !== 'ok') {
        fail_task($pdo, $taskId, "无法通过 SSH 连接到 macOS 容器\n请确认 Docker-OSX 容器正在运行");
        exit(1);
    }

    // 检查 xcodebuild
    $result = ssh_exec('xcodebuild -version 2>/dev/null | head -1');
    if ($result['code'] !== 0 || strpos($result['output'][0] ?? '', 'Xcode') === false) {
        fail_task($pdo, $taskId, "macOS 容器中未安装 Xcode\n请先在终端执行: sudo bash tools/setup-ios-xcode.sh");
        exit(1);
    }

    // 复制模板到共享构建目录
    update_task($pdo, $taskId, ['progress' => 10, 'progress_msg' => '复制模板项目...']);
    $templateDir = realpath(__DIR__ . '/../ios-template');
    if (!$templateDir || !is_dir($templateDir)) {
        fail_task($pdo, $taskId, 'iOS 模板项目不存在');
        exit(1);
    }

    $projectRoot = realpath(__DIR__ . '/..');
    $localBuildDir = $projectRoot . '/data/ios-build/task_' . $taskId;
    if (is_dir($localBuildDir)) {
        recursive_delete($localBuildDir);
    }
    recursive_copy($templateDir, $localBuildDir);

    $remoteBuildDir = '/mnt/build/task_' . $taskId;

    // 写入配置
    update_task($pdo, $taskId, ['progress' => 15, 'progress_msg' => '写入应用配置...']);
    $config = [
        'url' => $params['url'],
        'app_name' => $params['app_name'],
        'status_bar_color' => $params['status_bar_color'] ?? '#000000',
    ];
    file_put_contents($localBuildDir . '/WebViewApp/config.json', json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // 更新 Info.plist（替换 Bundle ID、版本号、Display Name）
    update_task($pdo, $taskId, ['progress' => 20, 'progress_msg' => '配置应用信息...']);
    $bundleId = $params['bundle_id'] ?? 'com.example.webviewapp';
    $versionName = $params['version_name'] ?? '1.0.0';
    $versionCode = (string)($params['version_code'] ?? 1);

    // 修改 project.pbxproj 中的 bundle id 和版本号
    $pbxPath = $localBuildDir . '/WebViewApp.xcodeproj/project.pbxproj';
    $pbxContent = file_get_contents($pbxPath);
    $pbxContent = str_replace('com.example.webviewapp', $bundleId, $pbxContent);
    $pbxContent = preg_replace('/MARKETING_VERSION = [^;]+;/', 'MARKETING_VERSION = ' . $versionName . ';', $pbxContent);
    $pbxContent = preg_replace('/CURRENT_PROJECT_VERSION = [^;]+;/', 'CURRENT_PROJECT_VERSION = ' . $versionCode . ';', $pbxContent);
    // 如果需要设置显示名称
    if (!empty($params['app_name'])) {
        $pbxContent = str_replace(
            'PRODUCT_NAME = "$(TARGET_NAME)"',
            'PRODUCT_NAME = "' . addslashes($params['app_name']) . '"',
            $pbxContent
        );
    }
    file_put_contents($pbxPath, $pbxContent);

    // 处理图标
    update_task($pdo, $taskId, ['progress' => 25, 'progress_msg' => '处理应用图标...']);
    $iconPath = !empty($params['icon_url']) ? realpath($projectRoot . '/' . $params['icon_url']) : '';
    if ($iconPath && file_exists($iconPath)) {
        $iconSetDir = $localBuildDir . '/WebViewApp/Assets.xcassets/AppIcon.appiconset';
        $sizes = [
            'icon-20@2x.png' => 40,
            'icon-20@3x.png' => 60,
            'icon-29@2x.png' => 58,
            'icon-29@3x.png' => 87,
            'icon-40@2x.png' => 80,
            'icon-40@3x.png' => 120,
            'icon-60@2x.png' => 120,
            'icon-60@3x.png' => 180,
            'icon-76@1x.png' => 76,
            'icon-76@2x.png' => 152,
            'icon-83.5@2x.png' => 167,
            'icon-1024.png' => 1024,
        ];
        foreach ($sizes as $filename => $size) {
            resize_image($iconPath, $iconSetDir . '/' . $filename, $size, $size);
        }
    }

    // 通过 SSH 执行 xcodebuild
    update_task($pdo, $taskId, ['progress' => 30, 'progress_msg' => '正在编译IPA（可能需要几分钟）...']);

    $xcodeCmd = "cd $remoteBuildDir && " .
        "xcodebuild -project WebViewApp.xcodeproj -scheme WebViewApp " .
        "-configuration Release -destination 'generic/platform=iOS' " .
        "-archivePath build/app.xcarchive archive " .
        "CODE_SIGNING_ALLOWED=NO 2>&1";

    // 使用 proc_open 实时读取输出
    $sshFullCmd = "ssh $SSH_OPTS $SSH_USER@$SSH_HOST " . escapeshellarg($xcodeCmd);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($sshFullCmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        fail_task($pdo, $taskId, '无法启动编译进程');
        exit(1);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $output = [];
    $startTime = time();
    $timeout = 900; // 15分钟超时
    $lastCancelCheck = 0;
    $lastProgress = 30;

    while (true) {
        while (($line = fgets($pipes[1])) !== false) {
            $line = rtrim($line);
            if ($line === '') continue;
            $output[] = $line;
            $newPct = parse_xcode_progress($line, $lastProgress);
            if ($newPct > $lastProgress) {
                $lastProgress = $newPct;
                update_task($pdo, $taskId, [
                    'progress' => $newPct,
                    'progress_msg' => xcode_progress_msg($newPct),
                ]);
            }
        }
        while (($line = fgets($pipes[2])) !== false) {
            $output[] = rtrim($line);
        }

        $status = proc_get_status($proc);
        if (!$status['running']) break;

        if (time() - $startTime > $timeout) {
            proc_terminate($proc, 9);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            fail_task($pdo, $taskId, "构建超时（超过 {$timeout} 秒），已终止");
            exit(1);
        }

        // 每5秒检查是否被取消
        if (time() - $lastCancelCheck >= 5) {
            $lastCancelCheck = time();
            $cancelStmt = $pdo->prepare('SELECT status FROM build_tasks WHERE id = ?');
            $cancelStmt->execute([$taskId]);
            $cancelRow = $cancelStmt->fetch();
            if (!$cancelRow || $cancelRow['status'] === 'failed') {
                proc_terminate($proc, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                fwrite(STDERR, "Task $taskId cancelled by user\n");
                exit(0);
            }
        }

        usleep(200000);
    }

    while (($line = fgets($pipes[1])) !== false) { $output[] = rtrim($line); }
    while (($line = fgets($pipes[2])) !== false) { $output[] = rtrim($line); }
    fclose($pipes[1]);
    fclose($pipes[2]);

    $retCode = $status['exitcode'];
    if ($retCode === -1) {
        $retCode = proc_close($proc);
    } else {
        proc_close($proc);
    }

    if ($retCode !== 0) {
        $errorOutput = implode("\n", array_slice($output, -50));
        fail_task($pdo, $taskId, "xcodebuild 编译失败 (exit code: $retCode)\n" . $errorOutput);
        exit(1);
    }

    // 打包 IPA：.app -> Payload/ -> zip
    update_task($pdo, $taskId, ['progress' => 85, 'progress_msg' => '打包IPA文件...']);

    $packCmd = "cd $remoteBuildDir/build && " .
        "mkdir -p Payload && " .
        "cp -r app.xcarchive/Products/Applications/*.app Payload/ && " .
        "zip -r -q app.ipa Payload/";
    $result = ssh_exec($packCmd);
    if ($result['code'] !== 0) {
        fail_task($pdo, $taskId, "IPA 打包失败\n" . implode("\n", $result['output']));
        exit(1);
    }

    // 复制 IPA 到 uploads 目录
    update_task($pdo, $taskId, ['progress' => 90, 'progress_msg' => '复制到目标目录...']);
    $ipaDir = $projectRoot . '/uploads/ipas';
    if (!is_dir($ipaDir)) mkdir($ipaDir, 0755, true);

    $safeName = preg_replace('/[^\w\-]/', '_', $params['app_name']);
    $ipaFilename = $safeName . '_' . ($params['version_name'] ?? '1.0.0') . '_' . time() . '.ipa';
    $localIpaPath = $localBuildDir . '/build/app.ipa';
    $destPath = $ipaDir . '/' . $ipaFilename;

    if (!file_exists($localIpaPath)) {
        fail_task($pdo, $taskId, 'IPA 文件未找到（共享卷同步可能延迟）');
        exit(1);
    }

    if (!copy($localIpaPath, $destPath)) {
        fail_task($pdo, $taskId, 'IPA 复制到目标目录失败');
        exit(1);
    }

    $ipaUrl = 'uploads/ipas/' . $ipaFilename;
    $ipaSize = format_size(filesize($destPath));

    // 保存记录
    update_task($pdo, $taskId, ['progress' => 95, 'progress_msg' => '保存记录...']);
    $stmt = $pdo->prepare('INSERT INTO generated_ipas (task_id, app_name, bundle_id, version_name, version_code, url, icon_url, ipa_url, ipa_size, signing_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $taskId,
        $params['app_name'],
        $params['bundle_id'] ?? 'com.example.webviewapp',
        $params['version_name'] ?? '1.0.0',
        (int)($params['version_code'] ?? 1),
        $params['url'],
        $params['icon_url'] ?? '',
        $ipaUrl,
        $ipaSize,
        'unsigned',
    ]);

    // 完成
    update_task($pdo, $taskId, [
        'status' => 'done',
        'progress' => 100,
        'progress_msg' => '构建完成',
        'result_url' => $ipaUrl,
        'result_size' => $ipaSize,
    ]);

    fwrite(STDOUT, "Build completed: $ipaUrl ($ipaSize)\n");

} catch (Exception $e) {
    fail_task($pdo, $taskId, '构建异常: ' . $e->getMessage());
    exit(1);
} finally {
    // 清理远程构建目录
    if ($taskId) {
        ssh_exec('rm -rf /mnt/build/task_' . $taskId);
    }
    // 清理本地构建目录
    if ($localBuildDir && is_dir($localBuildDir)) {
        recursive_delete($localBuildDir);
    }
}

// ========== 辅助函数 ==========

function update_task(PDO $pdo, int $id, array $fields): void {
    $sets = [];
    $params = [];
    foreach ($fields as $k => $v) {
        $sets[] = "$k = ?";
        $params[] = $v;
    }
    $sets[] = "updated_at = datetime('now')";
    $params[] = $id;
    $pdo->prepare("UPDATE build_tasks SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
}

function fail_task(PDO $pdo, int $id, string $error): void {
    update_task($pdo, $id, ['status' => 'failed', 'error_msg' => $error]);
    fwrite(STDERR, "Task $id failed: $error\n");
}

function parse_xcode_progress(string $line, int $current): int {
    if (strpos($line, 'Compiling') !== false || strpos($line, 'CompileSwift') !== false) return max($current, 45);
    if (strpos($line, 'Linking') !== false) return max($current, 60);
    if (strpos($line, 'CopySwiftLibs') !== false || strpos($line, 'Copy') !== false) return max($current, 70);
    if (strpos($line, 'GenerateAssetSymbols') !== false || strpos($line, 'CompileAssetCatalog') !== false) return max($current, 55);
    if (strpos($line, 'ARCHIVE SUCCEEDED') !== false || strpos($line, 'Archive Succeeded') !== false) return 80;
    if (strpos($line, 'BUILD SUCCEEDED') !== false || strpos($line, '** ARCHIVE SUCCEEDED **') !== false) return 80;
    return $current;
}

function xcode_progress_msg(int $pct): string {
    if ($pct <= 45) return '编译Swift代码...';
    if ($pct <= 55) return '处理资源文件...';
    if ($pct <= 60) return '链接...';
    if ($pct <= 70) return '复制Swift库...';
    if ($pct <= 80) return '归档完成...';
    return '编译完成';
}

function recursive_copy(string $src, string $dst): void {
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
            recursive_copy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

function recursive_delete(string $dir): void {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($dir);
}

function resize_image(string $src, string $dst, int $w, int $h): void {
    $info = @getimagesize($src);
    if (!$info) { copy($src, $dst); return; }
    $srcImg = null;
    switch ($info['mime']) {
        case 'image/png':  $srcImg = imagecreatefrompng($src); break;
        case 'image/jpeg': $srcImg = imagecreatefromjpeg($src); break;
        case 'image/gif':  $srcImg = imagecreatefromgif($src); break;
        case 'image/webp': $srcImg = imagecreatefromwebp($src); break;
    }
    if (!$srcImg) { copy($src, $dst); return; }
    $dstImg = imagecreatetruecolor($w, $h);
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
    $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
    imagefill($dstImg, 0, 0, $transparent);
    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $w, $h, imagesx($srcImg), imagesy($srcImg));
    imagepng($dstImg, $dst);
    imagedestroy($srcImg);
    imagedestroy($dstImg);
}

function format_size(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
