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
        $customAndroid = get_setting($pdo, 'custom_android_home');
        $androidHome = ($customAndroid && $customAndroid !== '') ? $customAndroid : (detect_android_home() ?: '/opt/android-sdk');

        // Java 17
        $javaOut = [];
        $customJava = get_setting($pdo, 'custom_java_home');
        if ($customJava && $customJava !== '') {
            exec(escapeshellarg($customJava . '/bin/java') . ' -version 2>&1', $javaOut);
        } else {
            exec('java -version 2>&1', $javaOut);
        }
        $javaVerLine = $javaOut[0] ?? '';
        $hasJava = (bool)preg_match('/version\s+"?17/', $javaVerLine);
        $javaVer = '';
        if (preg_match('/version\s+"([^"]+)"/', $javaVerLine, $m)) $javaVer = $m[1];

        // Android SDK（使用exec绕过open_basedir限制）
        @exec('test -d ' . escapeshellarg($androidHome) . ' && echo 1', $homeOut);
        $hasHome = (trim($homeOut[0] ?? '') === '1');

        // sdkmanager
        $sdkMgr = $androidHome . '/cmdline-tools/latest/bin/sdkmanager';
        @exec('test -f ' . escapeshellarg($sdkMgr) . ' && echo 1', $sdkOut);
        $hasSdk = (trim($sdkOut[0] ?? '') === '1');

        // build-tools
        $btDir = $androidHome . '/build-tools/34.0.0';
        @exec('test -d ' . escapeshellarg($btDir) . ' && echo 1', $btOut);
        $hasBt = (trim($btOut[0] ?? '') === '1');

        // platform
        $pfDir = $androidHome . '/platforms/android-34';
        @exec('test -d ' . escapeshellarg($pfDir) . ' && echo 1', $pfOut);
        $hasPf = (trim($pfOut[0] ?? '') === '1');

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

    // ========== iOS 环境检测 ==========
    if ($action === 'ios_status') {
        $iosContainer = get_setting($pdo, 'custom_ios_container') ?: 'ysapp-ios-builder';
        $iosSshPort = get_setting($pdo, 'custom_ios_ssh_port') ?: '50922';

        // Docker 已安装
        $dockerOut = [];
        @exec('docker --version 2>/dev/null', $dockerOut);
        $hasDocker = !empty($dockerOut[0]) && str_contains($dockerOut[0], 'Docker');

        // Docker 运行中
        $dockerRunning = false;
        if ($hasDocker) {
            @exec('docker info > /dev/null 2>&1', $drOut, $drCode);
            $dockerRunning = ($drCode === 0);
        }

        // KVM 可用
        $kvmOut = [];
        @exec('test -e /dev/kvm && echo 1', $kvmOut);
        $hasKvm = (trim($kvmOut[0] ?? '') === '1');

        // 容器已创建
        $containerExists = false;
        $containerRunning = false;
        if ($dockerRunning) {
            $ceOut = [];
            @exec('docker ps -a --format "{{.Names}}" 2>/dev/null', $ceOut);
            $containerExists = in_array($iosContainer, $ceOut);

            $crOut = [];
            @exec('docker ps --format "{{.Names}}" 2>/dev/null', $crOut);
            $containerRunning = in_array($iosContainer, $crOut);
        }

        // SSH 可连接
        $sshOk = false;
        if ($containerRunning) {
            $sshOut = [];
            @exec('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -o BatchMode=yes -p ' . escapeshellarg($iosSshPort) . ' user@localhost "echo ok" 2>/dev/null', $sshOut);
            $sshOk = (trim($sshOut[0] ?? '') === 'ok');
        }

        // Xcode 已安装
        $xcodeVer = '';
        $hasXcode = false;
        if ($sshOk) {
            $xcOut = [];
            @exec('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -o BatchMode=yes -p ' . escapeshellarg($iosSshPort) . ' user@localhost "xcodebuild -version 2>/dev/null | head -1" 2>/dev/null', $xcOut);
            $xcLine = trim($xcOut[0] ?? '');
            if (str_contains($xcLine, 'Xcode')) {
                $hasXcode = true;
                $xcodeVer = $xcLine;
            }
        }

        json_response([
            'docker'            => ['ok' => $hasDocker, 'version' => trim($dockerOut[0] ?? '')],
            'docker_running'    => ['ok' => $dockerRunning],
            'kvm'               => ['ok' => $hasKvm],
            'container_exists'  => ['ok' => $containerExists],
            'container_running' => ['ok' => $containerRunning],
            'ssh'               => ['ok' => $sshOk],
            'xcode'             => ['ok' => $hasXcode, 'version' => $xcodeVer],
            'all_ok'            => $hasDocker && $dockerRunning && $hasKvm && $containerExists && $containerRunning && $sshOk && $hasXcode,
        ]);
    }

    // iOS 安装日志轮询
    if ($action === 'ios_install_log') {
        $logPath = __DIR__ . '/../../data/ios_install.log';
        $log = file_exists($logPath) ? file_get_contents($logPath) : '';
        $status = get_setting($pdo, 'ios_install_status', 'idle');
        json_response(['status' => $status, 'log' => $log]);
    }

    // iOS Xcode 安装日志轮询
    if ($action === 'ios_xcode_log') {
        $logPath = __DIR__ . '/../../data/ios_xcode_install.log';
        $log = file_exists($logPath) ? file_get_contents($logPath) : '';
        $status = get_setting($pdo, 'ios_xcode_status', 'idle');
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

        // 后台启动 worker（PHP_BINARY 在 FPM 下返回 php-fpm，需用 PHP_BINDIR）
        $phpBin = PHP_BINDIR . '/php';
        if (!file_exists($phpBin)) $phpBin = 'php';
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

    // 一键卸载 Android 环境
    if ($action === 'uninstall_android') {
        $currentStatus = get_setting($pdo, 'android_install_status', 'idle');
        if ($currentStatus === 'running') {
            json_response(['error' => '当前有安装/卸载任务正在运行'], 400);
        }

        $workerScript = realpath(__DIR__ . '/../../tools/install-android-worker.php');
        if (!$workerScript) {
            json_response(['error' => 'worker 脚本不存在'], 500);
        }

        $uninstallScript = realpath(__DIR__ . '/../../tools/uninstall-android-env.sh');
        if (!$uninstallScript) {
            json_response(['error' => '卸载脚本不存在'], 500);
        }

        $dataDir = realpath(__DIR__ . '/../../data');
        $logFile = $dataDir . '/android_install.log';
        file_put_contents($logFile, "正在启动卸载...\n");

        set_setting($pdo, 'android_install_status', 'running');

        // 复用 install worker，传 uninstall 脚本路径
        $phpBin = PHP_BINDIR . '/php';
        if (!file_exists($phpBin)) $phpBin = 'php';
        $cmd = sprintf(
            'nohup %s %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($phpBin),
            escapeshellarg($workerScript),
            escapeshellarg($logFile),
            escapeshellarg($uninstallScript)
        );
        exec($cmd);

        json_response(['ok' => true]);
    }

    // ========== iOS 环境安装/卸载 ==========
    if ($action === 'install_ios') {
        $currentStatus = get_setting($pdo, 'ios_install_status', 'idle');
        if ($currentStatus === 'running') {
            json_response(['error' => '安装程序正在运行中，请勿重复操作'], 400);
        }

        $workerScript = realpath(__DIR__ . '/../../tools/install-ios-worker.php');
        if (!$workerScript || !file_exists($workerScript)) {
            json_response(['error' => 'iOS worker 脚本不存在'], 500);
        }

        $setupScript = realpath(__DIR__ . '/../../tools/setup-ios-env.sh');
        if (!$setupScript || !file_exists($setupScript)) {
            json_response(['error' => 'iOS 安装脚本不存在'], 500);
        }

        $dataDir = realpath(__DIR__ . '/../../data');
        $logFile = $dataDir . '/ios_install.log';
        file_put_contents($logFile, "正在启动 iOS 环境安装...\n");

        set_setting($pdo, 'ios_install_status', 'running');

        $phpBin = PHP_BINDIR . '/php';
        if (!file_exists($phpBin)) $phpBin = 'php';
        $cmd = sprintf(
            'nohup %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($phpBin),
            escapeshellarg($workerScript),
            escapeshellarg($logFile)
        );
        exec($cmd);

        json_response(['ok' => true, 'log_file' => 'data/ios_install.log']);
    }

    // 验证 Xcode 安装
    if ($action === 'verify_ios_xcode') {
        $iosSshPort = get_setting($pdo, 'custom_ios_ssh_port') ?: '50922';
        $xcOut = [];
        @exec('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -p ' . escapeshellarg($iosSshPort) . ' user@localhost "xcodebuild -version 2>/dev/null" 2>/dev/null', $xcOut, $xcCode);
        $xcodeInfo = implode("\n", $xcOut);
        $hasXcode = str_contains($xcodeInfo, 'Xcode');
        json_response([
            'ok' => $hasXcode,
            'info' => $xcodeInfo ?: '无法连接到 macOS 容器或 Xcode 未安装',
        ]);
    }

    // 卸载 iOS 环境
    if ($action === 'uninstall_ios') {
        $currentStatus = get_setting($pdo, 'ios_install_status', 'idle');
        if ($currentStatus === 'running') {
            json_response(['error' => '当前有安装/卸载任务正在运行'], 400);
        }

        $workerScript = realpath(__DIR__ . '/../../tools/install-ios-worker.php');
        if (!$workerScript) {
            json_response(['error' => 'worker 脚本不存在'], 500);
        }

        $uninstallScript = realpath(__DIR__ . '/../../tools/uninstall-ios-env.sh');
        if (!$uninstallScript) {
            json_response(['error' => 'iOS 卸载脚本不存在'], 500);
        }

        $dataDir = realpath(__DIR__ . '/../../data');
        $logFile = $dataDir . '/ios_install.log';
        file_put_contents($logFile, "正在启动 iOS 环境卸载...\n");

        set_setting($pdo, 'ios_install_status', 'running');

        $phpBin = PHP_BINDIR . '/php';
        if (!file_exists($phpBin)) $phpBin = 'php';
        $cmd = sprintf(
            'nohup %s %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($phpBin),
            escapeshellarg($workerScript),
            escapeshellarg($logFile),
            escapeshellarg($uninstallScript)
        );
        exec($cmd);

        json_response(['ok' => true]);
    }

    // 重置 iOS 安装状态
    if ($action === 'reset_ios_install_status') {
        set_setting($pdo, 'ios_install_status', 'idle');
        set_setting($pdo, 'ios_xcode_status', 'idle');
        $logPath = __DIR__ . '/../../data/ios_install.log';
        if (file_exists($logPath)) @unlink($logPath);
        $logPath2 = __DIR__ . '/../../data/ios_xcode_install.log';
        if (file_exists($logPath2)) @unlink($logPath2);
        json_response(['ok' => true]);
    }

    // Web 界面安装 Xcode（Apple ID + 2FA）
    if ($action === 'install_ios_xcode') {
        $currentStatus = get_setting($pdo, 'ios_xcode_status', 'idle');
        if (in_array($currentStatus, ['installing', 'awaiting_2fa', 'downloading'])) {
            json_response(['error' => 'Xcode 安装正在进行中，请勿重复操作'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $appleId = trim($input['apple_id'] ?? '');
        $pw = $input['password'] ?? '';
        if ($appleId === '' || $pw === '') {
            json_response(['error' => '请填写 Apple ID 和密码'], 400);
        }

        $workerScript = realpath(__DIR__ . '/../../tools/xcode-install-worker.php');
        if (!$workerScript || !file_exists($workerScript)) {
            json_response(['error' => 'Xcode 安装 worker 脚本不存在'], 500);
        }

        $dataDir = realpath(__DIR__ . '/../../data');

        // 写入临时凭据文件（worker 读取后立即删除）
        $credsFile = $dataDir . '/ios_xcode_creds.json';
        file_put_contents($credsFile, json_encode(['apple_id' => $appleId, 'password' => $pw]));
        chmod($credsFile, 0600);

        $logFile = $dataDir . '/ios_xcode_install.log';
        file_put_contents($logFile, '');

        set_setting($pdo, 'ios_xcode_status', 'installing');

        $phpBin = PHP_BINDIR . '/php';
        if (!file_exists($phpBin)) $phpBin = 'php';
        $cmd = sprintf(
            'nohup %s %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($phpBin),
            escapeshellarg($workerScript),
            escapeshellarg($credsFile),
            escapeshellarg($logFile)
        );
        exec($cmd);

        json_response(['ok' => true]);
    }

    // 提交 2FA 验证码
    if ($action === 'submit_ios_2fa') {
        $currentStatus = get_setting($pdo, 'ios_xcode_status', 'idle');
        if ($currentStatus !== 'awaiting_2fa') {
            json_response(['error' => '当前不在等待验证码状态'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $code = trim($input['code'] ?? '');
        if ($code === '') {
            json_response(['error' => '请输入验证码'], 400);
        }

        $twofaFile = realpath(__DIR__ . '/../../data') . '/ios_2fa_code.txt';
        file_put_contents($twofaFile, $code);

        json_response(['ok' => true]);
    }

    // 保存自定义环境路径
    if ($action === 'save_env_paths') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $allowed = ['custom_java_home', 'custom_android_home', 'custom_ios_ssh_port', 'custom_ios_container'];
        foreach ($allowed as $k) {
            if (isset($input[$k])) {
                set_setting($pdo, $k, trim($input[$k]));
            }
        }
        json_response(['ok' => true]);
    }

    json_response(['error' => '无效的action'], 400);
}

json_response(['error' => 'method not allowed'], 405);
