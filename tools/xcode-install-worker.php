#!/usr/bin/env php
<?php
/**
 * Xcode Web 交互式安装 Worker（仅CLI运行）
 * 用法: php xcode-install-worker.php <credentials_file> <log_file>
 *
 * 通过 SSH 连接 Docker-OSX 容器，使用 xcodes CLI 下载安装 Xcode。
 * 支持 2FA 验证码通过文件 IPC 传入（data/ios_2fa_code.txt）。
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

set_time_limit(0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$credsFile = $argv[1] ?? '';
$logFile   = $argv[2] ?? '';

if (!$credsFile || !$logFile) {
    fwrite(STDERR, "Usage: php xcode-install-worker.php <credentials_file> <log_file>\n");
    exit(1);
}

// 读取凭据并立即删除
if (!file_exists($credsFile)) {
    fwrite(STDERR, "Credentials file not found: $credsFile\n");
    exit(1);
}
$creds = json_decode(file_get_contents($credsFile), true);
@unlink($credsFile);

if (empty($creds['apple_id']) || empty($creds['password'])) {
    fwrite(STDERR, "Invalid credentials\n");
    exit(1);
}

$appleId  = $creds['apple_id'];
$password = $creds['password'];
unset($creds); // 尽早清除内存中的密码

$pdo = get_db();

// SSH 配置
$SSH_PORT = 50922;
$SSH_HOST = 'localhost';
$SSH_USER = 'user';
$SSH_OPTS = "-o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -p $SSH_PORT";

// 2FA IPC 文件路径
$dataDir = realpath(__DIR__ . '/../data');
$twofaFile = $dataDir . '/ios_2fa_code.txt';

function log_msg(string $msg): void {
    global $logFile;
    file_put_contents($logFile, $msg . "\n", FILE_APPEND);
}

function set_xcode_status(string $status): void {
    global $pdo;
    set_setting($pdo, 'ios_xcode_status', $status);
}

function ssh_exec_simple(string $command): array {
    global $SSH_OPTS, $SSH_USER, $SSH_HOST;
    $fullCmd = "ssh $SSH_OPTS $SSH_USER@$SSH_HOST " . escapeshellarg($command) . " 2>&1";
    $output = [];
    $retCode = 0;
    exec($fullCmd, $output, $retCode);
    return ['output' => $output, 'code' => $retCode];
}

try {
    set_xcode_status('installing');
    log_msg("=== Xcode Web 安装 ===");
    log_msg("时间: " . date('Y-m-d H:i:s'));
    log_msg("");

    // 1. 检查 SSH 连接
    log_msg("[Step 1] 检查 SSH 连接到 macOS 容器...");
    $result = ssh_exec_simple('echo ok');
    if ($result['code'] !== 0 || trim($result['output'][0] ?? '') !== 'ok') {
        log_msg("错误: 无法连接到 macOS 容器");
        log_msg("请确认 Docker-OSX 容器正在运行（Phase 1 已完成）");
        set_xcode_status('failed');
        exit(1);
    }
    log_msg("SSH 连接成功");
    log_msg("");

    // 2. 检查 xcodes CLI
    log_msg("[Step 2] 检查 xcodes CLI...");
    $result = ssh_exec_simple('which xcodes');
    if ($result['code'] !== 0) {
        log_msg("xcodes 未安装，正在通过 Homebrew 安装...");
        $result = ssh_exec_simple('brew install xcodesorg/made/xcodes 2>&1');
        if ($result['code'] !== 0) {
            log_msg("xcodes 安装失败:");
            log_msg(implode("\n", $result['output']));
            set_xcode_status('failed');
            exit(1);
        }
        log_msg("xcodes 安装完成");
    } else {
        log_msg("xcodes 已就绪");
    }
    log_msg("");

    // 3. 通过 xcodes 安装 Xcode
    log_msg("[Step 3] 开始安装 Xcode（使用 Apple ID 认证）...");
    log_msg("提示: Xcode 约 12GB，下载可能需要较长时间");
    log_msg("");

    // 构建远程命令：通过环境变量传入凭据
    $remoteCmd = sprintf(
        'export XCODES_USERNAME=%s XCODES_PASSWORD=%s; xcodes install --latest --no-superuser 2>&1',
        escapeshellarg($appleId),
        escapeshellarg($password)
    );
    // 清除密码变量
    $appleId = '';
    $password = '';

    $sshFullCmd = "ssh $SSH_OPTS $SSH_USER@$SSH_HOST " . escapeshellarg($remoteCmd);

    $descriptors = [
        0 => ['pipe', 'r'],  // stdin — 传入 2FA 码
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ];

    $proc = proc_open($sshFullCmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        log_msg("错误: 无法启动 xcodes 进程");
        set_xcode_status('failed');
        exit(1);
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $startTime = time();
    $timeout = 7200; // 2小时超时
    $twofaHandled = false;
    $outputBuffer = '';

    while (true) {
        // 读取 stdout
        while (($chunk = fread($pipes[1], 4096)) !== false && $chunk !== '') {
            $outputBuffer .= $chunk;
            // 按行处理
            while (($pos = strpos($outputBuffer, "\n")) !== false) {
                $line = substr($outputBuffer, 0, $pos);
                $outputBuffer = substr($outputBuffer, $pos + 1);
                $line = rtrim($line, "\r");
                if ($line !== '') {
                    log_msg($line);
                }
            }
            // 检查残留 buffer 中的 2FA 提示（可能没有换行符）
            if (!$twofaHandled && preg_match('/(?:two.?factor|2fa|verification\s*code|enter.*code)/i', $outputBuffer)) {
                log_msg($outputBuffer);
                $outputBuffer = '';
                handle_2fa($pipes[0], $twofaFile, $pdo);
                $twofaHandled = true;
            }
        }

        // 读取 stderr
        while (($chunk = fread($pipes[2], 4096)) !== false && $chunk !== '') {
            foreach (explode("\n", $chunk) as $line) {
                $line = rtrim($line, "\r");
                if ($line !== '') log_msg($line);
            }
        }

        // 检查进程状态
        $status = proc_get_status($proc);
        if (!$status['running']) break;

        // 超时检查
        if (time() - $startTime > $timeout) {
            proc_terminate($proc, 9);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            log_msg("");
            log_msg("错误: 安装超时（超过 2 小时），已终止");
            set_xcode_status('failed');
            exit(1);
        }

        usleep(300000); // 300ms
    }

    // 读取剩余输出
    if ($outputBuffer !== '') log_msg($outputBuffer);
    while (($chunk = fread($pipes[1], 4096)) !== false && $chunk !== '') {
        foreach (explode("\n", $chunk) as $line) {
            $line = rtrim($line, "\r");
            if ($line !== '') log_msg($line);
        }
    }
    while (($chunk = fread($pipes[2], 4096)) !== false && $chunk !== '') {
        foreach (explode("\n", $chunk) as $line) {
            $line = rtrim($line, "\r");
            if ($line !== '') log_msg($line);
        }
    }

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = $status['exitcode'];
    if ($exitCode === -1) {
        $exitCode = proc_close($proc);
    } else {
        proc_close($proc);
    }

    if ($exitCode !== 0) {
        log_msg("");
        log_msg("xcodes 安装失败 (exit code: $exitCode)");
        log_msg("请检查 Apple ID 和密码是否正确，或稍后重试。");
        set_xcode_status('failed');
        exit(1);
    }

    log_msg("");
    log_msg("xcodes 安装完成");
    log_msg("");

    // 4. 接受 Xcode 许可协议
    log_msg("[Step 4] 接受 Xcode 许可协议...");
    $result = ssh_exec_simple('sudo xcodebuild -license accept 2>&1');
    log_msg(implode("\n", $result['output']));
    if ($result['code'] !== 0) {
        log_msg("警告: 许可协议接受可能失败，但不影响后续使用");
    }
    log_msg("");

    // 5. 验证安装
    log_msg("[Step 5] 验证 Xcode 安装...");
    $result = ssh_exec_simple('xcodebuild -version 2>&1');
    $xcodeInfo = implode("\n", $result['output']);
    log_msg($xcodeInfo);

    if (strpos($xcodeInfo, 'Xcode') === false) {
        log_msg("");
        log_msg("错误: Xcode 验证失败");
        set_xcode_status('failed');
        exit(1);
    }

    log_msg("");
    log_msg("=== Xcode 安装成功！===");
    log_msg("请刷新页面查看最新状态。");
    set_xcode_status('done');

} catch (Exception $e) {
    log_msg("异常: " . $e->getMessage());
    set_xcode_status('failed');
    exit(1);
}

// ========== 辅助函数 ==========

function handle_2fa($stdinPipe, string $twofaFile, PDO $pdo): void {
    log_msg("");
    log_msg(">>> 需要两步验证：请在管理后台输入您 Apple 设备上收到的验证码 <<<");
    log_msg("");

    set_setting($pdo, 'ios_xcode_status', 'awaiting_2fa');

    // 清除可能残留的旧文件
    if (file_exists($twofaFile)) @unlink($twofaFile);

    $waitStart = time();
    $waitTimeout = 300; // 5分钟等待 2FA

    while (true) {
        if (time() - $waitStart > $waitTimeout) {
            log_msg("错误: 两步验证码等待超时（5分钟），请重试");
            fclose($stdinPipe);
            set_setting($pdo, 'ios_xcode_status', 'failed');
            exit(1);
        }

        if (file_exists($twofaFile)) {
            $code = trim(file_get_contents($twofaFile));
            @unlink($twofaFile);

            if ($code !== '') {
                log_msg("收到验证码，正在提交...");
                fwrite($stdinPipe, $code . "\n");
                fflush($stdinPipe);
                log_msg("");
                set_setting($pdo, 'ios_xcode_status', 'downloading');
                return;
            }
        }

        sleep(2);
    }
}
