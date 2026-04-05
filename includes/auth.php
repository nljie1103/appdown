<?php
/**
 * 登录鉴权
 */

function is_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

function require_auth(): void {
    if (!is_logged_in()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json')) {
            json_response(['error' => 'unauthorized'], 401);
        }
        header('Location: /admin/login.php');
        exit;
    }
}

function do_login(string $username, string $password): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, password FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_user'] = $username;

    $pdo->prepare('UPDATE admin_users SET last_login = datetime("now") WHERE id = ?')
        ->execute([$user['id']]);

    return true;
}

function do_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
