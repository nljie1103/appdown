<?php
/**
 * 后台登录页
 */

require_once __DIR__ . '/../includes/init.php';

if (is_logged_in()) {
    header('Location: /admin/dashboard.php');
    exit;
}

// 读取站点名称
$pdo = get_db();
$siteRow = $pdo->query("SELECT setting_val FROM site_settings WHERE setting_key = 'site_title'")->fetch();
$siteName = $siteRow ? $siteRow['setting_val'] : 'AppDown';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (do_login($username, $password)) {
        header('Location: /admin/dashboard.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?> - 后台登录</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); width: 100%; max-width: 380px; }
        h1 { font-size: 1.5em; text-align: center; margin-bottom: 8px; color: #1a1a1a; }
        .sub { text-align: center; color: #888; margin-bottom: 28px; font-size: 0.9em; }
        label { display: block; font-weight: 600; margin-bottom: 6px; margin-top: 18px; font-size: 0.9em; color: #333; }
        input { width: 100%; padding: 11px 14px; border: 1.5px solid #e0e0e0; border-radius: 10px; font-size: 1em; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.15); }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 10px; font-size: 1em; font-weight: 600; cursor: pointer; margin-top: 28px; transition: opacity 0.2s; }
        button:hover { opacity: 0.9; }
        .error { color: #e74c3c; text-align: center; margin-top: 14px; font-size: 0.9em; padding: 8px; background: #fef0f0; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>后台管理</h1>
        <p class="sub"><?= htmlspecialchars($siteName) ?> 管理系统</p>
        <form method="POST">
            <label>用户名</label>
            <input type="text" name="username" required autofocus>
            <label>密码</label>
            <input type="password" name="password" required>
            <button type="submit">登 录</button>
        </form>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
