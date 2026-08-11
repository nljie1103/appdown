<?php
/**
 * 账户管理API — 修改用户名和密码
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT id, username, created_at, last_login FROM admin_users WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $user = $stmt->fetch();
    if (!$user) json_response(['error' => '用户不存在'], 404);
    json_response($user);
}

if ($method === 'PUT') {
    csrf_validate();
    $data = get_json_input();
    $action = $data['action'] ?? '';
    $userId = $_SESSION['admin_id'];

    $currentPassword = $data['current_password'] ?? '';
    if (!$currentPassword) json_response(['error' => '请输入当前密码'], 400);

    $stmt = $pdo->prepare('SELECT password FROM admin_users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($currentPassword, $user['password'])) {
        json_response(['error' => '当前密码错误'], 400);
    }

    if ($action === 'username') {
        $newUsername = trim($data['new_username'] ?? '');
        if (!$newUsername || strlen($newUsername) < 2 || strlen($newUsername) > 32) {
            json_response(['error' => '用户名长度需在2-32个字符之间'], 400);
        }
        if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fff}]+$/u', $newUsername)) {
            json_response(['error' => '用户名只能包含字母、数字、下划线和中文'], 400);
        }
        $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? AND id != ?');
        $stmt->execute([$newUsername, $userId]);
        if ($stmt->fetch()) json_response(['error' => '该用户名已被占用'], 400);

        $pdo->prepare('UPDATE admin_users SET username = ? WHERE id = ?')->execute([$newUsername, $userId]);
        $_SESSION['admin_user'] = $newUsername;
        json_response(['ok' => true, 'message' => '用户名已修改']);
    }

    if ($action === 'password') {
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        if (strlen($newPassword) < 8) json_response(['error' => '新密码长度不能少于8位'], 400);
        if ($newPassword !== $confirmPassword) json_response(['error' => '两次输入的新密码不一致'], 400);

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')->execute([$hash, $userId]);

        // 全局刷新 Session 世代：其他设备立即失效，当前设备继续有效。
        $epoch = bin2hex(random_bytes(16));
        set_setting($pdo, 'auth_session_epoch', $epoch);
        $_SESSION['auth_session_epoch'] = $epoch;
        session_regenerate_id(true);
        $_SESSION['last_activity'] = time();

        json_response(['ok' => true, 'message' => '密码已修改，其他已登录设备已退出']);
    }

    json_response(['error' => '无效操作'], 400);
}

json_response(['error' => 'method not allowed'], 405);
