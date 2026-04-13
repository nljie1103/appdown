#!/usr/bin/env php
<?php
/**
 * iOS 环境安装/卸载 Worker（仅CLI运行）
 * 用法: php install-ios-worker.php <log_file> [script_path]
 *
 * 调用指定脚本（默认 setup-ios-env.sh）并将输出写入日志文件，
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
    fwrite(STDERR, "Usage: php install-ios-worker.php <log_file> [script_path]\n");
    exit(1);
}

// 可选第二个参数：指定要运行的脚本（默认为安装脚本）
$customScript = $argv[2] ?? '';

$pdo = get_db();

try {
    // 标记为运行中
    set_setting($pdo, 'ios_install_status', 'running');

    // 清空日志文件
    file_put_contents($logFile, '');

    // 定位脚本
    if ($customScript && file_exists($customScript)) {
        $script = $customScript;
    } else {
        $script = realpath(__DIR__ . '/setup-ios-env.sh');
    }
    if (!$script || !file_exists($script)) {
        file_put_contents($logFile, "[错误] 脚本不存在\n");
        set_setting($pdo, 'ios_install_status', 'failed');
        exit(1);
    }

    // 检测当前是否已经是 root
    $isRoot = function_exists('posix_getuid') ? (posix_getuid() === 0) : (trim(shell_exec('id -u') ?? '') === '0');

    // 从数据库读取自定义配置，通过环境变量传给安装脚本
    $envParts = [];
    $envKeys = [
        'custom_docker_data_root' => 'DOCKER_DATA_ROOT',
        'custom_docker_mirror'    => 'DOCKER_MIRROR',
        'custom_docker_osx_image' => 'DOCKER_OSX_IMAGE',
        'custom_ios_ssh_port'     => 'SSH_PORT',
        'custom_ios_container'    => 'CONTAINER_NAME',
    ];
    foreach ($envKeys as $settingKey => $envName) {
        $val = get_setting($pdo, $settingKey);
        if ($val !== '' && $val !== null) {
            $envParts[] = $envName . '=' . escapeshellarg($val);
        }
    }
    $envPrefix = $envParts ? implode(' ', $envParts) . ' ' : '';

    if ($isRoot) {
        $cmd = sprintf('%sbash %s > %s 2>&1', $envPrefix, escapeshellarg($script), escapeshellarg($logFile));
    } else {
        // 非 root，尝试 sudo（免密）
        exec('sudo -n true 2>/dev/null', $sudoOut, $sudoCode);
        if ($sudoCode === 0) {
            $cmd = sprintf('sudo %sbash %s > %s 2>&1', $envPrefix, escapeshellarg($script), escapeshellarg($logFile));
        } else {
            $fallbackCmd = 'sudo bash ' . $script;
            $msg = "[错误] 当前 PHP 用户没有 sudo 免密权限，无法自动安装。\n\n";
            $msg .= "请在服务器终端执行以下命令完成安装：\n\n";
            $msg .= "  $fallbackCmd\n\n";
            $msg .= "或者给 PHP 用户添加 sudo 免密权限后重试：\n\n";
            $msg .= "  echo '" . get_current_user() . " ALL=(ALL) NOPASSWD: " . $script . "' | sudo tee /etc/sudoers.d/appdown-ios\n";
            file_put_contents($logFile, $msg);
            set_setting($pdo, 'ios_install_status', 'failed');
            exit(1);
        }
    }

    $retCode = 0;
    exec($cmd, $output, $retCode);

    if ($retCode === 0) {
        file_put_contents($logFile, "\n[完成] 操作成功！\n", FILE_APPEND);
        set_setting($pdo, 'ios_install_status', 'done');
        fwrite(STDOUT, "Script completed successfully\n");
    } else {
        file_put_contents($logFile, "\n[失败] 脚本退出码: $retCode\n", FILE_APPEND);
        set_setting($pdo, 'ios_install_status', 'failed');
        fwrite(STDERR, "Script failed with exit code: $retCode\n");
        exit(1);
    }

} catch (Exception $e) {
    file_put_contents($logFile, "\n[异常] " . $e->getMessage() . "\n", FILE_APPEND);
    set_setting($pdo, 'ios_install_status', 'failed');
    fwrite(STDERR, "Exception: " . $e->getMessage() . "\n");
    exit(1);
}
