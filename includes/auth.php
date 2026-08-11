<?php
/**
 * SaaS 租户后台鉴权。
 * 租户用户名即公开 slug，由中央 saas.db 全局唯一管理。
 */

function invalidate_session(): void {
    $_SESSION = [];
    set_current_tenant(null);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        @session_start();
    }
}

function is_logged_in(): bool {
    if (empty($_SESSION['admin_id']) || empty($_SESSION['tenant_slug'])) return false;

    $timeout = 7200;
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $timeout)) {
        invalidate_session();
        return false;
    }
    $_SESSION['last_activity'] = time();

    $lockFile = __DIR__ . '/../install/install.lock';
    if (file_exists($lockFile)) {
        $fingerprint = md5(filemtime($lockFile) . realpath($lockFile));
        if (($_SESSION['install_fp'] ?? '') !== $fingerprint) {
            invalidate_session();
            return false;
        }
    }

    try {
        $tenant = find_tenant((string)$_SESSION['tenant_slug']);
        if (!$tenant || (int)$tenant['id'] !== (int)$_SESSION['admin_id']) {
            invalidate_session();
            return false;
        }
        set_current_tenant($tenant);
        $pdo = get_db();
        $epoch = get_setting($pdo, 'auth_session_epoch', '1');
        if (!hash_equals($epoch, (string)($_SESSION['auth_session_epoch'] ?? ''))) {
            invalidate_session();
            return false;
        }
        schedule_daily_maintenance($pdo);
    } catch (Throwable $e) {
        error_log('[AppDown SaaS auth] ' . $e->getMessage());
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

    if (function_exists('enforce_backup_export_security')) enforce_backup_export_security();
}

function do_login(string $username, string $password): bool {
    $slug = normalize_tenant_slug($username);
    $tenant = find_tenant($slug);
    if (!$tenant || !password_verify($password, $tenant['password'])) return false;

    set_current_tenant($tenant);
    $pdo = get_db();

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$tenant['id'];
    $_SESSION['admin_user'] = $tenant['slug'];
    $_SESSION['tenant_slug'] = $tenant['slug'];
    $_SESSION['tenant_display_name'] = $tenant['display_name'];
    $_SESSION['last_activity'] = time();
    $_SESSION['auth_session_epoch'] = get_setting($pdo, 'auth_session_epoch', '1');

    $lockFile = __DIR__ . '/../install/install.lock';
    if (file_exists($lockFile)) $_SESSION['install_fp'] = md5(filemtime($lockFile) . realpath($lockFile));

    get_saas_db()->prepare("UPDATE tenants SET last_login = datetime('now'), updated_at = datetime('now') WHERE id = ?")
        ->execute([(int)$tenant['id']]);
    return true;
}

function do_logout(): void {
    $_SESSION = [];
    set_current_tenant(null);
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
