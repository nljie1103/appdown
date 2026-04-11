#!/usr/bin/env php
<?php
/**
 * Android 环境安装/卸载 Worker（仅CLI运行）
 * 用法: php install-android-worker.php <log_file> [script_path]
 *
 * 调用指定脚本（默认 setup-android-env.sh）并将输出写入日志文件，
 * 通过 site_settings 表记录状态。
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

set_time_limit(0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$logFile = $argv[1] ?? '';
if (empty($logFile)) {
    fwrite(STDERR, "Usage: php install-android-worker.php <log_file> [script_path]\n");
    exit(1);
}

// 可选第二个参数：指定要运行的脚本（默认为安装脚本）
$customScript = $argv[2] ?? '';

$pdo = get_db();

try {
    // 标记为安装中
    set_setting($pdo, 'android_install_status', 'running');

    // 清空日志文件
    file_put_contents($logFile, '');

    // 定位脚本
    if ($customScript && file_exists($customScript)) {
        $script = $customScript;
    } else {
        $script = realpath(__DIR__ . '/setup-android-env.sh');
    }
    if (!$script || !file_exists($script)) {
        file_put_contents($logFile, "[错误] 脚本不存在\n");
        set_setting($pdo, 'android_install_status', 'failed');
        exit(1);
    }

    // 检测当前是否已经是 root
    $isRoot = (posix_getuid() === 0);

    if ($isRoot) {
        // 已经是 root，直接运行
        $cmd = sprintf('bash %s > %s 2>&1', escapeshellarg($script), escapeshellarg($logFile));
    } else {
        // 非 root，尝试 sudo（免密）
        // 先测试 sudo 是否可用
        exec('sudo -n true 2>/dev/null', $sudoOut, $sudoCode);
        if ($sudoCode === 0) {
            $cmd = sprintf('sudo bash %s > %s 2>&1', escapeshellarg($script), escapeshellarg($logFile));
        } else {
            // sudo 不可用，写入友好提示
            $fallbackCmd = 'sudo bash ' . $script;
            $msg = "[错误] 当前 PHP 用户没有 sudo 免密权限，无法自动安装。\n\n";
            $msg .= "请在服务器终端执行以下命令完成安装：\n\n";
            $msg .= "  $fallbackCmd\n\n";
            $msg .= "或者给 PHP 用户添加 sudo 免密权限后重试：\n\n";
            $msg .= "  echo '" . get_current_user() . " ALL=(ALL) NOPASSWD: " . $script . "' | sudo tee /etc/sudoers.d/appdown-android\n";
            file_put_contents($logFile, $msg);
            set_setting($pdo, 'android_install_status', 'failed');
            exit(1);
        }
    }

    $retCode = 0;
    exec($cmd, $output, $retCode);

    if ($retCode === 0) {
        file_put_contents($logFile, "\n[完成] 操作成功！\n", FILE_APPEND);
        set_setting($pdo, 'android_install_status', 'done');
        fwrite(STDOUT, "Script completed successfully\n");
    } else {
        file_put_contents($logFile, "\n[失败] 脚本退出码: $retCode\n", FILE_APPEND);
        set_setting($pdo, 'android_install_status', 'failed');
        fwrite(STDERR, "Script failed with exit code: $retCode\n");
        exit(1);
    }

} catch (Exception $e) {
    file_put_contents($logFile, "\n[异常] " . $e->getMessage() . "\n", FILE_APPEND);
    set_setting($pdo, 'android_install_status', 'failed');
    fwrite(STDERR, "Exception: " . $e->getMessage() . "\n");
    exit(1);
}
