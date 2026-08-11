<?php
/** SaaS 租户账户 API：slug 由超级后台管理，租户本人只改密码。 */
require_once __DIR__ . '/../../includes/init.php';
require_auth();

$method = get_request_method();
$tenant = require_tenant_context();
$control = get_saas_db();

if ($method === 'GET') {
    json_response([
        'id' => (int)$tenant['id'],
        'username' => $tenant['slug'],
        'created_at' => $tenant['created_at'],
        'last_login' => $tenant['last_login'],
        'display_name' => $tenant['display_name'],
        'username_locked' => true,
    ]);
}

if ($method === 'PUT') {
    csrf_validate();
    $data = get_json_input();
    $action = $data['action'] ?? '';
    $currentPassword = (string)($data['current_password'] ?? '');
    if ($currentPassword === '') json_response(['error' => '请输入当前密码'], 400);

    $fresh = find_tenant($tenant['slug'], true);
    if (!$fresh || !password_verify($currentPassword, $fresh['password'])) json_response(['error' => '当前密码错误'], 400);

    if ($action === 'username') {
        json_response(['error' => 'SaaS 版用户名同时是公开 URL，只能由超级管理员修改/迁移'], 400);
    }

    if ($action === 'password') {
        $newPassword = (string)($data['new_password'] ?? '');
        $confirmPassword = (string)($data['confirm_password'] ?? '');
        if (strlen($newPassword) < 8) json_response(['error' => '新密码长度不能少于8位'], 400);
        if ($newPassword !== $confirmPassword) json_response(['error' => '两次输入的新密码不一致'], 400);

        $control->prepare("UPDATE tenants SET password = ?, updated_at = datetime('now') WHERE id = ?")
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$tenant['id']]);

        $pdo = get_db();
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
