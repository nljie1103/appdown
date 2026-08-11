<?php
/**
 * 登录鉴权
 */

function invalidate_session(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        @session_start();
    }
}

function is_logged_in(): bool {
    if (empty($_SESSION['admin_id'])) return false;

    // Session 超时检查：超过 2 小时自动登出
    $timeout = 7200;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        invalidate_session();
        return false;
    }
    $_SESSION['last_activity'] = time();

    // 验证安装指纹：如果网站重装了，旧session失效
    $lockFile = __DIR__ . '/../install/install.lock';
    if (file_exists($lockFile)) {
        $fingerprint = md5(filemtime($lockFile) . realpath($lockFile));
        if (($_SESSION['install_fp'] ?? '') !== $fingerprint) {
            invalidate_session();
            return false;
        }
    }

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        if (!$stmt->fetch()) {
            invalidate_session();
            return false;
        }

        // 修改密码后立即使其他设备上的旧 Session 失效。
        $epoch = get_setting($pdo, 'auth_session_epoch', '1');
        if (!hash_equals($epoch, (string)($_SESSION['auth_session_epoch'] ?? ''))) {
            invalidate_session();
            return false;
        }

        schedule_daily_maintenance($pdo);
    } catch (\Exception $e) {
        return false;
    }

    return true;
}

function require_auth(): void {
    $lockFile = __DIR__ . '/../install/install.lock';
    if (!file_exists($lockFile)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'json') !== false) {
            json_response(['error' => 'not_installed'], 503);
        }
        header('Location: /install/');
        exit;
    }

    if (!is_logged_in()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'json') !== false) {
            json_response(['error' => 'unauthorized'], 401);
        }
        header('Location: /admin/login.php');
        exit;
    }

    // 只有认证成功后，才允许高敏感备份导出执行 Secrets 预检。
    if (function_exists('enforce_backup_export_security')) {
        enforce_backup_export_security();
    }
}

function do_login(string $username, string $password): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, password FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) return false;

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_user'] = $username;
    $_SESSION['last_activity'] = time();
    $_SESSION['auth_session_epoch'] = get_setting($pdo, 'auth_session_epoch', '1');

    $lockFile = __DIR__ . '/../install/install.lock';
    if (file_exists($lockFile)) {
        $_SESSION['install_fp'] = md5(filemtime($lockFile) . realpath($lockFile));
    }

    $pdo->prepare("UPDATE admin_users SET last_login = datetime('now') WHERE id = ?")->execute([$user['id']]);
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
