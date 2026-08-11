<?php
/**
 * 工具函数
 */

function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ip_in_cidr(string $ip, string $cidr): bool {
    $cidr = trim($cidr);
    if ($cidr === '') return false;
    if (!str_contains($cidr, '/')) return hash_equals(strtolower($cidr), strtolower($ip));
    [$network, $prefix] = explode('/', $cidr, 2);
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($network);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) return false;
    $bits = (int)$prefix;
    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) return false;
    $bytes = intdiv($bits, 8);
    $rem = $bits % 8;
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) return false;
    if ($rem === 0) return true;
    $mask = (0xFF << (8 - $rem)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
}

function is_trusted_proxy(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    // 同机 Nginx/Apache 反代默认可信；额外代理通过环境变量显式配置。
    $trusted = ['127.0.0.1/32', '::1/128'];
    $env = trim((string)getenv('APPDOWN_TRUSTED_PROXIES'));
    if ($env !== '') {
        foreach (explode(',', $env) as $item) {
            $item = trim($item);
            if ($item !== '') $trusted[] = $item;
        }
    }
    foreach ($trusted as $cidr) {
        if (ip_in_cidr($ip, $cidr)) return true;
    }
    return false;
}

function get_client_ip(): string {
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!filter_var($remote, FILTER_VALIDATE_IP)) $remote = '0.0.0.0';

    if (!is_trusted_proxy($remote)) return $remote;

    // 仅在 REMOTE_ADDR 是可信反代时读取转发头，避免客户端伪造 X-Forwarded-For 绕过限流。
    $candidates = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) $candidates[] = $_SERVER['HTTP_X_REAL_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $forwarded) $candidates[] = trim($forwarded);
    }
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
    }
    return $remote;
}

function get_request_method(): string {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

function get_json_input(): array {
    static $loaded = false;
    static $cached = [];
    if ($loaded) return $cached;
    $loaded = true;
    $raw = file_get_contents('php://input');
    if (empty($raw)) return $cached;
    $data = json_decode($raw, true);
    $cached = is_array($data) ? $data : [];
    return $cached;
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
    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_val, updated_at)
                           VALUES (?, ?, datetime('now'))
                           ON CONFLICT(setting_key) DO UPDATE SET setting_val = excluded.setting_val, updated_at = excluded.updated_at");
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
    $envJava = getenv('JAVA_HOME');
    if ($envJava) {
        $out = [];
        @exec('test -d ' . escapeshellarg($envJava) . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $envJava;
    }

    $whichOut = [];
    @exec('which java 2>/dev/null', $whichOut);
    $javaBin = trim($whichOut[0] ?? '');
    if ($javaBin) {
        $realOut = [];
        @exec('readlink -f ' . escapeshellarg($javaBin) . ' 2>/dev/null', $realOut);
        $realPath = trim($realOut[0] ?? '') ?: $javaBin;
        $candidate = dirname(dirname($realPath));
        $verOut = [];
        @exec(escapeshellarg($candidate . '/bin/java') . ' -version 2>&1', $verOut);
        if (preg_match('/version\s+"?17/', $verOut[0] ?? '')) return $candidate;
    }

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
    $envAndroid = getenv('ANDROID_HOME');
    if ($envAndroid) {
        $out = [];
        @exec('test -d ' . escapeshellarg($envAndroid) . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $envAndroid;
    }

    $envSdkRoot = getenv('ANDROID_SDK_ROOT');
    if ($envSdkRoot) {
        $out = [];
        @exec('test -d ' . escapeshellarg($envSdkRoot) . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $envSdkRoot;
    }

    $whichOut = [];
    @exec('which sdkmanager 2>/dev/null', $whichOut);
    $sdkBin = trim($whichOut[0] ?? '');
    if ($sdkBin) {
        $realOut = [];
        @exec('readlink -f ' . escapeshellarg($sdkBin) . ' 2>/dev/null', $realOut);
        $realPath = trim($realOut[0] ?? '') ?: $sdkBin;
        $candidate = dirname(dirname(dirname(dirname($realPath))));
        $out = [];
        @exec('test -d ' . escapeshellarg($candidate . '/cmdline-tools') . ' && echo 1', $out);
        if (trim($out[0] ?? '') === '1') return $candidate;
    }

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
