<?php
/**
 * 总引导文件 - 所有PHP页面和API的入口
 */

date_default_timezone_set('Asia/Shanghai');

// API 请求的全局错误处理：捕获 PHP 错误，返回 JSON 而非 HTML
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    set_exception_handler(function ($e) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    });
}

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'use_strict_mode' => true,
]);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload.php';
