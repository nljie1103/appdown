<?php
/**
 * 工具函数
 */

function json_response(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_client_ip(): string {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = explode(',', $_SERVER[$h])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function get_request_method(): string {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

function get_json_input(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function sanitize_string(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function sanitize_filename(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._\-\x{4e00}-\x{9fff}]/u', '_', $name);
    return substr($name, 0, 100);
}

function require_method(string ...$methods): void {
    if (!in_array(get_request_method(), $methods, true)) {
        json_response(['error' => 'Method not allowed'], 405);
    }
}

function get_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare('SELECT setting_val FROM site_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_val'] : $default;
}

function set_setting(PDO $pdo, string $key, string $val): void {
    $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_val, updated_at)
                           VALUES (?, ?, datetime("now"))
                           ON CONFLICT(setting_key) DO UPDATE SET setting_val = excluded.setting_val, updated_at = excluded.updated_at');
    $stmt->execute([$key, $val]);
}

function today(): string {
    return date('Y-m-d');
}

/**
 * 检测 JAVA_HOME 路径（兜底检测非标准路径）
 * 优先级：环境变量 > which java > 候选路径
 */
function detect_java_home(): string {
    // 1. 环境变量 JAVA_HOME
    $envJava = getenv('JAVA_HOME');
    if ($envJava) {
        $out = [];
        @exec('test -d ' . escapeshellarg($envJava) . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $envJava;
    }

    // 2. which java → readlink → 向上两级（bin/java → bin → JAVA_HOME）
    $whichOut = [];
    @exec('which java 2>/dev/null', $whichOut);
    $javaBin = trim($whichOut[0] ?? '');
    if ($javaBin) {
        // 解析符号链接获取真实路径
        $realOut = [];
        @exec('readlink -f ' . escapeshellarg($javaBin) . ' 2>/dev/null', $realOut);
        $realPath = trim($realOut[0] ?? '') ?: $javaBin;
        // java 通常在 JAVA_HOME/bin/java，向上2级
        $candidate = dirname(dirname($realPath));
        $verOut = [];
        @exec(escapeshellarg($candidate . '/bin/java') . ' -version 2>&1', $verOut);
        if (preg_match('/version\s+"?17/', $verOut[0] ?? '')) {
            return $candidate;
        }
    }

    // 3. 常见候选路径
    $candidates = [
        '/usr/lib/jvm/java-17-openjdk-amd64',
        '/usr/lib/jvm/java-17-openjdk',
        '/usr/lib/jvm/java-17',
        '/usr/lib/jvm/java-17-openjdk-arm64',
        '/usr/java/jdk-17',
        '/opt/jdk-17',
    ];
    foreach ($candidates as $c) {
        $out = [];
        @exec('test -d ' . escapeshellarg($c) . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $c;
    }

    return '';
}

/**
 * 检测 ANDROID_HOME 路径（兜底检测非标准路径）
 * 优先级：ANDROID_HOME > ANDROID_SDK_ROOT > which sdkmanager > 候选路径
 */
function detect_android_home(): string {
    // 1. 环境变量 ANDROID_HOME
    $envAndroid = getenv('ANDROID_HOME');
    if ($envAndroid) {
        $out = [];
        @exec('test -d ' . escapeshellarg($envAndroid) . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $envAndroid;
    }

    // 2. 环境变量 ANDROID_SDK_ROOT（旧名称）
    $envSdkRoot = getenv('ANDROID_SDK_ROOT');
    if ($envSdkRoot) {
        $out = [];
        @exec('test -d ' . escapeshellarg($envSdkRoot) . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $envSdkRoot;
    }

    // 3. which sdkmanager → 向上3级（cmdline-tools/latest/bin/sdkmanager）
    $whichOut = [];
    @exec('which sdkmanager 2>/dev/null', $whichOut);
    $sdkBin = trim($whichOut[0] ?? '');
    if ($sdkBin) {
        $realOut = [];
        @exec('readlink -f ' . escapeshellarg($sdkBin) . ' 2>/dev/null', $realOut);
        $realPath = trim($realOut[0] ?? '') ?: $sdkBin;
        // sdkmanager 通常在 ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager
        $candidate = dirname(dirname(dirname(dirname($realPath))));
        $out = [];
        @exec('test -d ' . escapeshellarg($candidate . '/cmdline-tools') . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $candidate;
    }

    // 4. 常见候选路径
    $home = getenv('HOME') ?: '/root';
    $candidates = [
        '/opt/android-sdk',
        $home . '/Android/Sdk',
        '/usr/local/android-sdk',
        '/usr/lib/android-sdk',
        $home . '/android-sdk',
    ];
    foreach ($candidates as $c) {
        $out = [];
        @exec('test -d ' . escapeshellarg($c) . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $c;
    }

    return '';
}
