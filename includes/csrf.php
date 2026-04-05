<?php
/**
 * CSRF令牌
 */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_POST['_csrf']
        ?? get_json_input()['_csrf']
        ?? '';

    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_response(['error' => 'csrf_invalid'], 403);
    }
}

function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token) . '">';
}
