#!/usr/bin/env php
<?php
/**
 * APK 后台构建 Worker（仅CLI运行）
 * 用法: php build-worker.php <task_id>
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
    fwrite(STDERR, "Usage: php build-worker.php <task_id>\n");
    exit(1);
}

$pdo = get_db();
$buildDir = '';

try {
    // 加载任务
    $task = query_task($pdo, $taskId);
    if (!$task || $task['status'] !== 'pending') {
        fwrite(STDERR, "Task $taskId not found or not pending\n");
        exit(1);
    }

    $params = json_decode($task['params'], true);
    if (!$params) {
        fail_task($pdo, $taskId, '无效的构建参数');
        exit(1);
    }

    // 标记为构建中，记录PID
    update_task($pdo, $taskId, ['status' => 'building', 'progress' => 5, 'progress_msg' => '准备构建环境...', 'pid' => getmypid()]);

    // 验证环境（自动检测非标准路径）
    $javaHome = detect_java_home();
    if (!$javaHome) {
        fail_task($pdo, $taskId, "未检测到 Java 17 (JDK)\n请安装: sudo apt install openjdk-17-jdk\n或设置 JAVA_HOME 环境变量");
        exit(1);
    }

    $androidHome = detect_android_home();
    if (!$androidHome) {
        fail_task($pdo, $taskId, "未检测到 Android SDK\n请参照文档安装 Android SDK 命令行工具\n或设置 ANDROID_HOME 环境变量");
        exit(1);
    }

    // 复制模板
    update_task($pdo, $taskId, ['progress' => 10, 'progress_msg' => '复制模板项目...']);
    $templateDir = realpath(__DIR__ . '/../android-template');
    if (!$templateDir || !is_dir($templateDir)) {
        fail_task($pdo, $taskId, 'Android 模板项目不存在');
        exit(1);
    }
    $buildDir = sys_get_temp_dir() . '/apk_build_' . $taskId . '_' . time();
    recursive_copy($templateDir, $buildDir);
    chmod($buildDir . '/gradlew', 0755);

    // 写入配置
    update_task($pdo, $taskId, ['progress' => 15, 'progress_msg' => '写入应用配置...']);
    $config = [
        'url' => $params['url'],
        'app_name' => $params['app_name'],
        'splash_color' => $params['splash_color'] ?? '#FFFFFF',
        'status_bar_color' => $params['status_bar_color'] ?? '#000000',
        'enable_splash' => !empty($params['splash_url']),
        'splash_duration' => 2000,
    ];
    if (file_put_contents($buildDir . '/app/src/main/assets/config.json', json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
        fail_task($pdo, $taskId, '写入 config.json 失败');
        exit(1);
    }

    // 更新 strings.xml
    $stringsXml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
        '<resources>' . "\n" .
        '    <string name="app_name">' . htmlspecialchars($params['app_name']) . '</string>' . "\n" .
        '</resources>' . "\n";
    file_put_contents($buildDir . '/app/src/main/res/values/strings.xml', $stringsXml);

    // 更新 colors.xml
    $splashColor = $params['splash_color'] ?? '#FFFFFF';
    $statusColor = $params['status_bar_color'] ?? '#000000';
    $colorsXml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
        '<resources>' . "\n" .
        '    <color name="splash_bg">' . htmlspecialchars($splashColor) . '</color>' . "\n" .
        '    <color name="status_bar">' . htmlspecialchars($statusColor) . '</color>' . "\n" .
        '    <color name="primary">#2196F3</color>' . "\n" .
        '</resources>' . "\n";
    file_put_contents($buildDir . '/app/src/main/res/values/colors.xml', $colorsXml);

    // 处理图标
    update_task($pdo, $taskId, ['progress' => 25, 'progress_msg' => '处理应用图标...']);
    $iconPath = !empty($params['icon_url']) ? realpath(__DIR__ . '/../' . $params['icon_url']) : '';
    if ($iconPath && file_exists($iconPath)) {
        $sizes = [
            'mipmap-mdpi' => 48,
            'mipmap-hdpi' => 72,
            'mipmap-xhdpi' => 96,
            'mipmap-xxhdpi' => 144,
            'mipmap-xxxhdpi' => 192,
        ];
        foreach ($sizes as $dir => $size) {
            $destDir = $buildDir . '/app/src/main/res/' . $dir;
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            resize_image($iconPath, $destDir . '/ic_launcher.png', $size, $size);
        }
    }

    // 处理启动图
    update_task($pdo, $taskId, ['progress' => 30, 'progress_msg' => '处理启动画面...']);
    $splashPath = !empty($params['splash_url']) ? realpath(__DIR__ . '/../' . $params['splash_url']) : '';
    if ($splashPath && file_exists($splashPath)) {
        $drawableDir = $buildDir . '/app/src/main/res/drawable';
        if (!is_dir($drawableDir)) mkdir($drawableDir, 0755, true);
        copy($splashPath, $drawableDir . '/splash_image.png');
        // 更新 splash layout 以使用自定义图片
        $splashLayout = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
            '<FrameLayout xmlns:android="http://schemas.android.com/apk/res/android"' . "\n" .
            '    android:layout_width="match_parent"' . "\n" .
            '    android:layout_height="match_parent"' . "\n" .
            '    android:background="@color/splash_bg">' . "\n" .
            '    <ImageView' . "\n" .
            '        android:layout_width="match_parent"' . "\n" .
            '        android:layout_height="match_parent"' . "\n" .
            '        android:src="@drawable/splash_image"' . "\n" .
            '        android:scaleType="centerCrop" />' . "\n" .
            '</FrameLayout>' . "\n";
        file_put_contents($buildDir . '/app/src/main/res/layout/activity_splash.xml', $splashLayout);
    }

    // 获取签名密钥
    update_task($pdo, $taskId, ['progress' => 35, 'progress_msg' => '配置签名...']);
    $keystore = query_keystore($pdo, $task['keystore_id']);
    if (!$keystore) {
        fail_task($pdo, $taskId, '签名密钥不存在');
        exit(1);
    }
    $ksFilePath = realpath(__DIR__ . '/../' . $keystore['file_url']);
    if (!$ksFilePath || !file_exists($ksFilePath)) {
        fail_task($pdo, $taskId, '签名密钥文件不存在: ' . $keystore['file_url']);
        exit(1);
    }

    // 执行 Gradle 编译
    update_task($pdo, $taskId, ['progress' => 40, 'progress_msg' => '正在编译APK（可能需要几分钟）...']);

    $gradleCmd = sprintf(
        './gradlew assembleRelease ' .
        '-PappId=%s -PvName=%s -PvCode=%s ' .
        '-PksFile=%s -PksPwd=%s -PksAlias=%s -PksKeyPwd=%s ' .
        '--no-daemon --stacktrace',
        escapeshellarg($params['package_name']),
        escapeshellarg($params['version_name'] ?? '1.0.0'),
        escapeshellarg((string)($params['version_code'] ?? 1)),
        escapeshellarg($ksFilePath),
        escapeshellarg($keystore['store_password']),
        escapeshellarg($keystore['alias']),
        escapeshellarg($keystore['key_password'])
    );

    $env = [
        'JAVA_HOME' => $javaHome,
        'ANDROID_HOME' => $androidHome,
        'PATH' => getenv('PATH'),
        'HOME' => getenv('HOME') ?: '/tmp',
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($gradleCmd, $descriptors, $pipes, $buildDir, $env);
    if (!is_resource($proc)) {
        fail_task($pdo, $taskId, '无法启动Gradle进程');
        exit(1);
    }

    fclose($pipes[0]); // 关闭stdin
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $output = [];
    $startTime = time();
    $timeout = 600; // 10分钟超时
    $lastCancelCheck = 0;
    $lastProgress = 40;

    while (true) {
        // 读取stdout
        while (($line = fgets($pipes[1])) !== false) {
            $line = rtrim($line);
            if ($line === '') continue;
            $output[] = $line;
            $newPct = parse_gradle_progress($line, $lastProgress);
            if ($newPct > $lastProgress) {
                $lastProgress = $newPct;
                update_task($pdo, $taskId, [
                    'progress' => $newPct,
                    'progress_msg' => gradle_progress_msg($newPct),
                ]);
            }
        }
        // 读取stderr
        while (($line = fgets($pipes[2])) !== false) {
            $output[] = rtrim($line);
        }

        $status = proc_get_status($proc);
        if (!$status['running']) break;

        // 超时检查
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
            if (is_task_cancelled($pdo, $taskId)) {
                proc_terminate($proc, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                fwrite(STDERR, "Task $taskId cancelled by user\n");
                exit(0);
            }
        }

        usleep(200000); // 200ms
    }

    // 读取剩余输出
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
        fail_task($pdo, $taskId, "Gradle编译失败 (exit code: $retCode)\n" . $errorOutput);
        exit(1);
    }

    // 定位 APK
    update_task($pdo, $taskId, ['progress' => 85, 'progress_msg' => '构建完成，正在复制APK...']);
    $apkPath = $buildDir . '/app/build/outputs/apk/release/app-release.apk';
    if (!file_exists($apkPath)) {
        // 尝试 unsigned
        $apkPath = $buildDir . '/app/build/outputs/apk/release/app-release-unsigned.apk';
    }
    if (!file_exists($apkPath)) {
        fail_task($pdo, $taskId, 'APK文件未找到');
        exit(1);
    }

    // 复制到 uploads
    update_task($pdo, $taskId, ['progress' => 90, 'progress_msg' => '复制到目标目录...']);
    $apkDir = __DIR__ . '/../uploads/apks';
    if (!is_dir($apkDir)) mkdir($apkDir, 0755, true);
    $safeName = preg_replace('/[^\w\-]/', '_', $params['app_name']);
    $apkFilename = $safeName . '_' . ($params['version_name'] ?? '1.0.0') . '_' . time() . '.apk';
    $destPath = $apkDir . '/' . $apkFilename;
    if (!copy($apkPath, $destPath)) {
        fail_task($pdo, $taskId, 'APK复制到目标目录失败');
        exit(1);
    }
    $apkUrl = 'uploads/apks/' . $apkFilename;
    $apkSize = format_size(filesize($destPath));

    // 保存记录
    update_task($pdo, $taskId, ['progress' => 95, 'progress_msg' => '保存记录...']);
    $stmt = $pdo->prepare('INSERT INTO generated_apks (task_id, app_name, package_name, version_name, version_code, url, icon_url, splash_url, apk_url, apk_size, keystore_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $taskId,
        $params['app_name'],
        $params['package_name'],
        $params['version_name'] ?? '1.0.0',
        (int)($params['version_code'] ?? 1),
        $params['url'],
        $params['icon_url'] ?? '',
        $params['splash_url'] ?? '',
        $apkUrl,
        $apkSize,
        $task['keystore_id'],
    ]);

    // 完成
    update_task($pdo, $taskId, [
        'status' => 'done',
        'progress' => 100,
        'progress_msg' => '构建完成',
        'result_url' => $apkUrl,
        'result_size' => $apkSize,
    ]);

    fwrite(STDOUT, "Build completed: $apkUrl ($apkSize)\n");

} catch (Exception $e) {
    fail_task($pdo, $taskId, '构建异常: ' . $e->getMessage());
    exit(1);
} finally {
    // 清理临时目录
    if ($buildDir && is_dir($buildDir)) {
        recursive_delete($buildDir);
    }
}

// ========== 辅助函数 ==========

function query_task(PDO $pdo, int $id): array|false {
    $stmt = $pdo->prepare('SELECT * FROM build_tasks WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function query_keystore(PDO $pdo, int $id): array|false {
    $stmt = $pdo->prepare('SELECT * FROM keystores WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

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

function is_task_cancelled(PDO $pdo, int $taskId): bool {
    $stmt = $pdo->prepare('SELECT status FROM build_tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $row = $stmt->fetch();
    return !$row || $row['status'] === 'failed';
}

function parse_gradle_progress(string $line, int $current): int {
    if (str_contains($line, 'Downloading') || str_contains($line, 'downloading')) return max($current, 45);
    if (str_contains($line, 'compileReleaseJava') || str_contains($line, 'compileReleaseKotlin')) return max($current, 55);
    if (str_contains($line, 'mergeReleaseResources') || str_contains($line, 'processReleaseResources')) return max($current, 60);
    if (str_contains($line, 'dexBuilder') || str_contains($line, 'mergeDex') || str_contains($line, 'mergeExtDex')) return max($current, 70);
    if (str_contains($line, 'packageRelease')) return max($current, 75);
    if (str_contains($line, 'assembleRelease') && !str_contains($line, 'Task :')) return max($current, 80);
    if (str_contains($line, 'BUILD SUCCESSFUL')) return 85;
    return $current;
}

function gradle_progress_msg(int $pct): string {
    return match (true) {
        $pct <= 45 => '下载依赖...',
        $pct <= 55 => '编译Java/Kotlin代码...',
        $pct <= 60 => '合并资源文件...',
        $pct <= 70 => '生成DEX...',
        $pct <= 75 => '打包APK...',
        $pct <= 80 => '签名APK...',
        default    => '编译完成',
    };
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
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}

function resize_image(string $src, string $dst, int $w, int $h): void {
    $info = getimagesize($src);
    if (!$info) {
        copy($src, $dst);
        return;
    }

    $mime = $info['mime'];
    $srcImg = match ($mime) {
        'image/png' => imagecreatefrompng($src),
        'image/jpeg' => imagecreatefromjpeg($src),
        'image/gif' => imagecreatefromgif($src),
        'image/webp' => imagecreatefromwebp($src),
        default => null,
    };

    if (!$srcImg) {
        copy($src, $dst);
        return;
    }

    $dstImg = imagecreatetruecolor($w, $h);
    // 保持透明
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
