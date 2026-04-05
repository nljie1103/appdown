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
