<?php
/**
 * 系统管理 API — Android 环境检测 + 一键安装
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();
$action = $_GET['action'] ?? '';

// GET — 查询
if ($method === 'GET') {

    // Android 环境检测
    if ($action === 'android_status') {
        $androidHome = getenv('ANDROID_HOME') ?: '/opt/android-sdk';

        // Java 17
        $javaOut = [];
        exec('java -version 2>&1', $javaOut);
        $javaVerLine = $javaOut[0] ?? '';
        $hasJava = (bool)preg_match('/version\s+"?17/', $javaVerLine);
        $javaVer = '';
        if (preg_match('/version\s+"([^"]+)"/', $javaVerLine, $m)) $javaVer = $m[1];

        // Android SDK
        $hasHome = is_dir($androidHome);

        // sdkmanager
        $sdkMgr = $androidHome . '/cmdline-tools/latest/bin/sdkmanager';
        $hasSdk = file_exists($sdkMgr);

        // build-tools
        $btDir = $androidHome . '/build-tools/34.0.0';
        $hasBt = is_dir($btDir);

        // platform
        $pfDir = $androidHome . '/platforms/android-34';
        $hasPf = is_dir($pfDir);

        // keytool
        $ktOut = [];
        exec('which keytool 2>/dev/null', $ktOut, $ktCode);
        $hasKt = ($ktCode === 0);

        json_response([
            'java'         => ['ok' => $hasJava, 'version' => $javaVer],
            'android_home' => ['ok' => $hasHome, 'path' => $androidHome],
            'sdkmanager'   => ['ok' => $hasSdk],
            'build_tools'  => ['ok' => $hasBt, 'version' => '34.0.0'],
            'platform'     => ['ok' => $hasPf, 'version' => 'android-34'],
            'keytool'      => ['ok' => $hasKt],
            'all_ok'       => $hasJava && $hasHome && $hasSdk && $hasBt && $hasPf && $hasKt,
        ]);
    }

    // 安装日志轮询
    if ($action === 'install_log') {
        $logPath = __DIR__ . '/../../data/android_install.log';
        $log = file_exists($logPath) ? file_get_contents($logPath) : '';
        $status = get_setting($pdo, 'android_install_status', 'idle');
        json_response(['status' => $status, 'log' => $log]);
    }

    json_response(['error' => '无效的action'], 400);
}

// POST — 触发安装
csrf_validate();

if ($method === 'POST') {
    if ($action === 'install_android') {
        // 防重复
        $currentStatus = get_setting($pdo, 'android_install_status', 'idle');
        if ($currentStatus === 'running') {
            json_response(['error' => '安装程序正在运行中，请勿重复操作'], 400);
        }

        // 验证安装脚本存在
        $workerScript = realpath(__DIR__ . '/../../tools/install-android-worker.php');
        if (!$workerScript || !file_exists($workerScript)) {
            json_response(['error' => '安装 worker 脚本不存在'], 500);
        }

        $setupScript = realpath(__DIR__ . '/../../tools/setup-android-env.sh');
        if (!$setupScript || !file_exists($setupScript)) {
            json_response(['error' => '安装脚本 setup-android-env.sh 不存在'], 500);
        }

        // 准备日志文件
        $dataDir = realpath(__DIR__ . '/../../data');
        $logFile = $dataDir . '/android_install.log';
        file_put_contents($logFile, "正在启动安装...\n");

        // 先标记为 running，防止并发
        set_setting($pdo, 'android_install_status', 'running');

        // 后台启动 worker
        $phpBin = PHP_BINARY ?: 'php';
        $cmd = sprintf(
            'nohup %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($phpBin),
            escapeshellarg($workerScript),
            escapeshellarg($logFile)
        );
        exec($cmd);

        json_response(['ok' => true, 'log_file' => 'data/android_install.log']);
    }

    // 重置安装状态（用于手动清除卡住的状态）
    if ($action === 'reset_install_status') {
        set_setting($pdo, 'android_install_status', 'idle');
        $logPath = __DIR__ . '/../../data/android_install.log';
        if (file_exists($logPath)) @unlink($logPath);
        json_response(['ok' => true]);
    }

    json_response(['error' => '无效的action'], 400);
}

json_response(['error' => 'method not allowed'], 405);
