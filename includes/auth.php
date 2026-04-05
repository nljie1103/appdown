<?php
/**
 * 登录鉴权
 */

function is_logged_in(): bool {
    if (empty($_SESSION['admin_id'])) return false;

    // 验证安装指纹：如果网站重装了，旧session失效
    $lockFile = __DIR__ . '/../install/install.lock';
    if (file_exists($lockFile)) {
        $fingerprint = md5(filemtime($lockFile) . realpath($lockFile));
        if (($_SESSION['install_fp'] ?? '') !== $fingerprint) {
            // 安装指纹不匹配，说明是旧session或不同站点
            session_destroy();
            session_start();
            return false;
        }
    }

    // 验证用户在数据库中确实存在
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        if (!$stmt->fetch()) {
            $_SESSION = [];
            return false;
        }
    } catch (\Exception $e) {
        return false;
    }

    return true;
}

function require_auth(): void {
    // 未安装时禁止访问后台
    $lockFile = __DIR__ . '/../install/install.lock';
    if (!file_exists($lockFile)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json')) {
            json_response(['error' => 'not_installed'], 503);
        }
        header('Location: /install/');
        exit;
    }

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

    // 记录安装指纹，防止重装后session串用
    $lockFile = __DIR__ . '/../install/install.lock';
    if (file_exists($lockFile)) {
        $_SESSION['install_fp'] = md5(filemtime($lockFile) . realpath($lockFile));
    }

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
