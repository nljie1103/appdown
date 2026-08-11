<?php
/** 超级管理员鉴权，只操作中央 saas.db。 */

function super_session_fingerprint(): string {
    $lock = __DIR__ . '/../install/install.lock';
    if (!file_exists($lock)) return '';
    return md5(filemtime($lock) . (realpath($lock) ?: $lock));
}

function is_super_logged_in(): bool {
    if (empty($_SESSION['super_id'])) return false;
    if (isset($_SESSION['super_last_activity']) && time() - (int)$_SESSION['super_last_activity'] > 7200) {
        super_logout();
        return false;
    }
    if (($_SESSION['super_install_fp'] ?? '') !== super_session_fingerprint()) {
        super_logout();
        return false;
    }
    $stmt = get_saas_db()->prepare('SELECT id, username FROM super_users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['super_id']]);
    if (!$stmt->fetch()) {
        super_logout();
        return false;
    }
    $_SESSION['super_last_activity'] = time();
    return true;
}

function require_super_auth(): void {
    if (!file_exists(__DIR__ . '/../install/install.lock')) {
        header('Location: /install/');
        exit;
    }
    if (!is_super_logged_in()) {
        header('Location: /super/login.php');
        exit;
    }
}

function super_login(string $username, string $password): bool {
    $stmt = get_saas_db()->prepare('SELECT id, username, password FROM super_users WHERE username = ?');
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) return false;
    session_regenerate_id(true);
    $_SESSION['super_id'] = (int)$user['id'];
    $_SESSION['super_user'] = $user['username'];
    $_SESSION['super_last_activity'] = time();
    $_SESSION['super_install_fp'] = super_session_fingerprint();
    get_saas_db()->prepare("UPDATE super_users SET last_login = datetime('now') WHERE id = ?")->execute([(int)$user['id']]);
    return true;
}

function super_logout(): void {
    unset($_SESSION['super_id'], $_SESSION['super_user'], $_SESSION['super_last_activity'], $_SESSION['super_install_fp']);
}

function super_csrf_token(): string {
    if (empty($_SESSION['super_csrf'])) $_SESSION['super_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['super_csrf'];
}

function super_csrf_validate(): void {
    $token = (string)($_POST['_csrf'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['super_csrf'] ?? ''), $token)) {
        http_response_code(403);
        die('CSRF validation failed');
    }
}
