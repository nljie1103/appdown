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
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/upload.php';

$taskId = (int)($argv[1] ?? 0);
if (!$taskId) {
    fwrite(STDERR, "Usage: php build-worker.php <task_id>\n");
    exit(1);
}

$pdo = get_db();
$buildDir = '';
$activeSecrets = [];

try {
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

    update_task($pdo, $taskId, ['status' => 'building', 'progress' => 5, 'progress_msg' => '准备构建环境...', 'pid' => getmypid()]);

    $customJava = get_setting($pdo, 'custom_java_home');
    $javaHome = ($customJava && is_dir($customJava)) ? $customJava : detect_java_home();
    if (!$javaHome) {
        fail_task($pdo, $taskId, "未检测到 Java 17 (JDK)\n请安装: sudo apt install openjdk-17-jdk\n或设置 JAVA_HOME 环境变量");
        exit(1);
    }

    $customAndroid = get_setting($pdo, 'custom_android_home');
    $androidHome = ($customAndroid && is_dir($customAndroid)) ? $customAndroid : detect_android_home();
    if (!$androidHome) {
        fail_task($pdo, $taskId, "未检测到 Android SDK\n请参照文档安装 Android SDK 命令行工具\n或设置 ANDROID_HOME 环境变量");
        exit(1);
    }

    update_task($pdo, $taskId, ['progress' => 10, 'progress_msg' => '复制模板项目...']);
    $projectRoot = realpath(__DIR__ . '/..');
    $gradleCacheDir = $projectRoot . '/data/gradle-cache';
    if (!is_dir($gradleCacheDir)) mkdir($gradleCacheDir, 0755, true);
    $templateDir = realpath(__DIR__ . '/../android-template');
    if (!$templateDir || !is_dir($templateDir)) {
        fail_task($pdo, $taskId, 'Android 模板项目不存在');
        exit(1);
    }
    $buildDir = sys_get_temp_dir() . '/apk_build_' . $taskId . '_' . bin2hex(random_bytes(4));
    recursive_copy($templateDir, $buildDir);
    chmod($buildDir . '/gradlew', 0755);

    $gradleMirror = get_setting($pdo, 'custom_gradle_mirror');
    if ($gradleMirror) {
        $propsFile = $buildDir . '/gradle/wrapper/gradle-wrapper.properties';
        if (file_exists($propsFile)) {
            $props = file_get_contents($propsFile);
            $props = preg_replace('/distributionUrl=.*/', 'distributionUrl=' . str_replace(':', '\\:', $gradleMirror), $props);
            file_put_contents($propsFile, $props);
        }
    }

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

    $stringsXml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
        '<resources>' . "\n" .
        '    <string name="app_name">' . htmlspecialchars($params['app_name']) . '</string>' . "\n" .
        '</resources>' . "\n";
    file_put_contents($buildDir . '/app/src/main/res/values/strings.xml', $stringsXml);

    $splashColor = $params['splash_color'] ?? '#FFFFFF';
    $statusColor = $params['status_bar_color'] ?? '#000000';
    $colorsXml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
        '<resources>' . "\n" .
        '    <color name="splash_bg">' . htmlspecialchars($splashColor) . '</color>' . "\n" .
        '    <color name="status_bar">' . htmlspecialchars($statusColor) . '</color>' . "\n" .
        '    <color name="primary">#2196F3</color>' . "\n" .
        '</resources>' . "\n";
    file_put_contents($buildDir . '/app/src/main/res/values/colors.xml', $colorsXml);

    update_task($pdo, $taskId, ['progress' => 25, 'progress_msg' => '处理应用图标...']);
    $iconPath = safe_project_file($projectRoot, $params['icon_url'] ?? '');
    if ($iconPath) {
        $sizes = ['mipmap-mdpi' => 48, 'mipmap-hdpi' => 72, 'mipmap-xhdpi' => 96, 'mipmap-xxhdpi' => 144, 'mipmap-xxxhdpi' => 192];
        foreach ($sizes as $dir => $size) {
            $destDir = $buildDir . '/app/src/main/res/' . $dir;
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            resize_image($iconPath, $destDir . '/ic_launcher.png', $size, $size);
        }
    }

    update_task($pdo, $taskId, ['progress' => 30, 'progress_msg' => '处理启动画面...']);
    $splashPath = safe_project_file($projectRoot, $params['splash_url'] ?? '');
    if ($splashPath) {
        $drawableDir = $buildDir . '/app/src/main/res/drawable';
        if (!is_dir($drawableDir)) mkdir($drawableDir, 0755, true);
        copy($splashPath, $drawableDir . '/splash_image.png');
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

    update_task($pdo, $taskId, ['progress' => 35, 'progress_msg' => '配置签名...']);
    $keystore = query_keystore($pdo, $task['keystore_id']);
    if (!$keystore) {
        fail_task($pdo, $taskId, '签名密钥不存在');
        exit(1);
    }
    $activeSecrets = [$keystore['store_password'], $keystore['key_password']];

    $keystoreRoot = realpath(__DIR__ . '/../uploads/keystores');
    $ksFilePath = realpath(__DIR__ . '/../' . $keystore['file_url']);
    if (!$ksFilePath || !$keystoreRoot || !str_starts_with($ksFilePath, $keystoreRoot . DIRECTORY_SEPARATOR) || !is_file($ksFilePath)) {
        fail_task($pdo, $taskId, '签名密钥文件不存在或路径不合法');
        exit(1);
    }

    update_task($pdo, $taskId, ['progress' => 40, 'progress_msg' => '正在编译APK（可能需要几分钟）...']);
    $gradleCmd = sprintf(
        './gradlew assembleRelease -PappId=%s -PvName=%s -PvCode=%s --no-daemon --stacktrace',
        escapeshellarg($params['package_name']),
        escapeshellarg($params['version_name'] ?? '1.0.0'),
        escapeshellarg((string)($params['version_code'] ?? 1))
    );

    $env = [
        'JAVA_HOME' => $javaHome,
        'ANDROID_HOME' => $androidHome,
        'PATH' => getenv('PATH'),
        'HOME' => getenv('HOME') ?: '/tmp',
        'GRADLE_USER_HOME' => $projectRoot . '/data/gradle-cache',
        'APPDOWN_KS_FILE' => $ksFilePath,
        'APPDOWN_KS_STORE_PASSWORD' => $keystore['store_password'],
        'APPDOWN_KS_ALIAS' => $keystore['alias'],
        'APPDOWN_KS_KEY_PASSWORD' => $keystore['key_password'],
    ];

    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($gradleCmd, $descriptors, $pipes, $buildDir, $env);
    if (!is_resource($proc)) {
        fail_task($pdo, $taskId, '无法启动Gradle进程');
        exit(1);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $output = [];
    $startTime = time();
    $timeout = 600;
    $lastCancelCheck = 0;
    $lastProgress = 40;

    while (true) {
        while (($line = fgets($pipes[1])) !== false) {
            $line = rtrim($line);
            if ($line === '') continue;
            $output[] = redact_sensitive_text($line, $activeSecrets);
            $newPct = parse_gradle_progress($line, $lastProgress);
            if ($newPct > $lastProgress) {
                $lastProgress = $newPct;
                update_task($pdo, $taskId, ['progress' => $newPct, 'progress_msg' => gradle_progress_msg($newPct)]);
            }
        }
        while (($line = fgets($pipes[2])) !== false) $output[] = redact_sensitive_text(rtrim($line), $activeSecrets);

        $status = proc_get_status($proc);
        if (!$status['running']) break;
        if (time() - $startTime > $timeout) {
            proc_terminate($proc, 9);
            fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
            fail_task($pdo, $taskId, "构建超时（超过 {$timeout} 秒），已终止");
            exit(1);
        }
        if (time() - $lastCancelCheck >= 5) {
            $lastCancelCheck = time();
            if (is_task_cancelled($pdo, $taskId)) {
                proc_terminate($proc, 9);
                fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
                fwrite(STDERR, "Task $taskId cancelled by user\n");
                exit(0);
            }
        }
        usleep(200000);
    }

    while (($line = fgets($pipes[1])) !== false) $output[] = redact_sensitive_text(rtrim($line), $activeSecrets);
    while (($line = fgets($pipes[2])) !== false) $output[] = redact_sensitive_text(rtrim($line), $activeSecrets);
    fclose($pipes[1]); fclose($pipes[2]);
    $retCode = $status['exitcode'];
    if ($retCode === -1) $retCode = proc_close($proc); else proc_close($proc);
    if ($retCode !== 0) {
        fail_task($pdo, $taskId, "Gradle编译失败 (exit code: $retCode)\n" . implode("\n", array_slice($output, -50)));
        exit(1);
    }

    update_task($pdo, $taskId, ['progress' => 85, 'progress_msg' => '构建完成，正在复制APK...']);
    $apkPath = $buildDir . '/app/build/outputs/apk/release/app-release.apk';
    if (!file_exists($apkPath)) $apkPath = $buildDir . '/app/build/outputs/apk/release/app-release-unsigned.apk';
    if (!file_exists($apkPath)) {
        fail_task($pdo, $taskId, 'APK文件未找到');
        exit(1);
    }

    update_task($pdo, $taskId, ['progress' => 90, 'progress_msg' => '复制到目标目录...']);
    $apkDir = __DIR__ . '/../uploads/apks';
    if (!is_dir($apkDir)) mkdir($apkDir, 0755, true);
    $safeName = preg_replace('/[^\w\x{4e00}-\x{9fff}\-]/u', '_', $params['app_name']);
    $safeName = trim(preg_replace('/_+/', '_', $safeName), '_') ?: 'app';
    $version = $params['version_name'] ?? '1.0.0';
    $apkFilename = resolve_filename_collision($apkDir, $safeName . '-' . $version, 'apk');
    $destPath = $apkDir . '/' . $apkFilename;
    if (!copy($apkPath, $destPath)) {
        fail_task($pdo, $taskId, 'APK复制到目标目录失败');
        exit(1);
    }
    $apkUrl = 'uploads/apks/' . $apkFilename;
    $apkSize = format_size(filesize($destPath));

    update_task($pdo, $taskId, ['progress' => 95, 'progress_msg' => '保存记录...']);
    $stmt = $pdo->prepare('INSERT INTO generated_apks (task_id, app_name, package_name, version_name, version_code, url, icon_url, splash_url, apk_url, apk_size, keystore_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$taskId, $params['app_name'], $params['package_name'], $params['version_name'] ?? '1.0.0', (int)($params['version_code'] ?? 1), $params['url'], $params['icon_url'] ?? '', $params['splash_url'] ?? '', $apkUrl, $apkSize, $task['keystore_id']]);

    update_task($pdo, $taskId, ['status' => 'done', 'progress' => 100, 'progress_msg' => '构建完成', 'result_url' => $apkUrl, 'result_size' => $apkSize]);
    fwrite(STDOUT, "Build completed: $apkUrl ($apkSize)\n");
} catch (Exception $e) {
    fail_task($pdo, $taskId, '构建异常: ' . redact_sensitive_text($e->getMessage(), $activeSecrets));
    exit(1);
} finally {
    if ($buildDir && is_dir($buildDir)) recursive_delete($buildDir);
    $activeSecrets = [];
}

function query_task(PDO $pdo, int $id) {
    $stmt = $pdo->prepare('SELECT * FROM build_tasks WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function query_keystore(PDO $pdo, int $id) {
    $stmt = $pdo->prepare('SELECT * FROM keystores WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return false;
    $storeRaw = (string)($row['store_password'] ?? '');
    $keyRaw = (string)($row['key_password'] ?? '');
    $row['store_password'] = decrypt_secret($storeRaw);
    $row['key_password'] = decrypt_secret($keyRaw);
    // 构建时也顺带迁移旧明文，确保不依赖用户先打开密钥管理页。
    if (($storeRaw !== '' && !is_encrypted_secret($storeRaw)) || ($keyRaw !== '' && !is_encrypted_secret($keyRaw))) {
        try {
            $pdo->prepare("UPDATE keystores SET store_password=?, key_password=?, updated_at=datetime('now') WHERE id=?")
                ->execute([encrypt_secret($row['store_password']), encrypt_secret($row['key_password']), $id]);
        } catch (Throwable $e) {}
    }
    return $row;
}

function update_task(PDO $pdo, int $id, array $fields): void {
    $sets = []; $params = [];
    foreach ($fields as $k => $v) { $sets[] = "$k = ?"; $params[] = $v; }
    $sets[] = "updated_at = datetime('now')";
    $params[] = $id;
    $pdo->prepare('UPDATE build_tasks SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
}

function fail_task(PDO $pdo, int $id, string $error): void {
    global $activeSecrets;
    $error = redact_sensitive_text($error, $activeSecrets ?? []);
    update_task($pdo, $id, ['status' => 'failed', 'error_msg' => $error]);
    fwrite(STDERR, "Task $id failed: $error\n");
}

function is_task_cancelled(PDO $pdo, int $taskId): bool {
    $stmt = $pdo->prepare('SELECT status FROM build_tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $row = $stmt->fetch();
    return !$row || $row['status'] === 'failed';
}

function safe_project_file(string $root, string $relative): string {
    if ($relative === '' || str_contains($relative, '..')) return '';
    $path = realpath($root . '/' . ltrim($relative, '/'));
    return ($path && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) ? $path : '';
}

function parse_gradle_progress(string $line, int $current): int {
    if (stripos($line, 'Downloading') !== false) return max($current, 45);
    if (strpos($line, 'compileReleaseJava') !== false || strpos($line, 'compileReleaseKotlin') !== false) return max($current, 55);
    if (strpos($line, 'mergeReleaseResources') !== false || strpos($line, 'processReleaseResources') !== false) return max($current, 60);
    if (strpos($line, 'dexBuilder') !== false || strpos($line, 'mergeDex') !== false || strpos($line, 'mergeExtDex') !== false) return max($current, 70);
    if (strpos($line, 'packageRelease') !== false) return max($current, 75);
    if (strpos($line, 'assembleRelease') !== false && strpos($line, 'Task :') === false) return max($current, 80);
    if (strpos($line, 'BUILD SUCCESSFUL') !== false) return 85;
    return $current;
}

function gradle_progress_msg(int $pct): string {
    if ($pct <= 45) return '下载依赖...';
    if ($pct <= 55) return '编译Java/Kotlin代码...';
    if ($pct <= 60) return '合并资源文件...';
    if ($pct <= 70) return '生成DEX...';
    if ($pct <= 75) return '打包APK...';
    if ($pct <= 80) return '签名APK...';
    return '编译完成';
}

function recursive_copy(string $src, string $dst): void {
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) recursive_copy($srcPath, $dstPath); else copy($srcPath, $dstPath);
    }
    closedir($dir);
}

function recursive_delete(string $dir): void {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { if ($item->isDir()) rmdir($item->getPathname()); else unlink($item->getPathname()); }
    rmdir($dir);
}

function resize_image(string $src, string $dst, int $w, int $h): void {
    $info = getimagesize($src);
    if (!$info) { copy($src, $dst); return; }
    $srcImg = match ($info['mime']) {
        'image/png' => @imagecreatefrompng($src),
        'image/jpeg' => @imagecreatefromjpeg($src),
        'image/gif' => @imagecreatefromgif($src),
        'image/webp' => @imagecreatefromwebp($src),
        default => null,
    };
    if (!$srcImg) { copy($src, $dst); return; }
    $dstImg = imagecreatetruecolor($w, $h);
    imagealphablending($dstImg, false); imagesavealpha($dstImg, true);
    $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127); imagefill($dstImg, 0, 0, $transparent);
    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $w, $h, imagesx($srcImg), imagesy($srcImg));
    imagepng($dstImg, $dst); imagedestroy($srcImg); imagedestroy($dstImg);
}

function format_size(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
