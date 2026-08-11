<?php
/**
 * SaaS 租户后台统一登录页。
 * 用户名就是公开站点 slug，例如 leon -> /leon/。
 */

require_once __DIR__ . '/../includes/init.php';

$lockFile = __DIR__ . '/../install/install.lock';
if (!file_exists($lockFile)) {
    header('Location: /install/');
    exit;
}

if (is_logged_in()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$control = get_saas_db();
$control->exec("
    CREATE TABLE IF NOT EXISTS tenant_login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tenant_slug TEXT NOT NULL,
        ip TEXT NOT NULL,
        attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_tenant_login_attempts ON tenant_login_attempts(tenant_slug, ip, attempted_at);
");

$prefill = normalize_tenant_slug((string)($_GET['tenant'] ?? $_POST['username'] ?? ''));
$prefillTenant = $prefill !== '' ? find_tenant($prefill) : null;
$siteName = $prefillTenant['display_name'] ?? 'AppDown SaaS';
$maxAttempts = 5;
$lockMinutes = 15;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = normalize_tenant_slug((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $ip = get_client_ip();
    $cutoff = date('Y-m-d H:i:s', time() - $lockMinutes * 60);

    $valid = validate_tenant_slug($username);
    $rateSlug = $valid['ok'] ? $valid['slug'] : '__invalid__';
    $stmt = $control->prepare('SELECT COUNT(*) FROM tenant_login_attempts WHERE tenant_slug = ? AND ip = ? AND attempted_at > ?');
    $stmt->execute([$rateSlug, $ip, $cutoff]);
    $recentAttempts = (int)$stmt->fetchColumn();

    if ($recentAttempts >= $maxAttempts) {
        $error = "登录尝试过多，请 {$lockMinutes} 分钟后再试";
    } elseif ($valid['ok'] && do_login($username, $password)) {
        $control->prepare('DELETE FROM tenant_login_attempts WHERE tenant_slug = ? AND ip = ?')->execute([$rateSlug, $ip]);
        header('Location: /admin/dashboard.php');
        exit;
    } else {
        $control->prepare('INSERT INTO tenant_login_attempts (tenant_slug, ip) VALUES (?, ?)')->execute([$rateSlug, $ip]);
        $left = $maxAttempts - $recentAttempts - 1;
        $error = $left <= 0
            ? "用户名或密码错误，已锁定 {$lockMinutes} 分钟"
            : "用户名或密码错误（还可尝试 {$left} 次）";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?> - 租户后台登录</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
        .card{background:white;padding:40px;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.22);width:100%;max-width:390px}
        .mark{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:20px;margin:0 auto 18px}
        h1{font-size:1.5em;text-align:center;margin-bottom:8px;color:#171717}
        .sub{text-align:center;color:#888;margin-bottom:28px;font-size:.9em;line-height:1.6}
        label{display:block;font-weight:650;margin-bottom:6px;margin-top:18px;font-size:.9em;color:#333}
        input{width:100%;padding:12px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:1em;transition:.2s}
        input:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.15)}
        button{width:100%;padding:12px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:0;border-radius:10px;font-size:1em;font-weight:650;cursor:pointer;margin-top:28px}
        .error{color:#c0392b;text-align:center;margin-top:14px;font-size:.9em;padding:9px;background:#fff1f0;border-radius:8px}
        .home{text-align:center;margin-top:20px;font-size:.86em}.home a{color:#667eea;text-decoration:none}
    </style>
</head>
<body>
<div class="card">
    <div class="mark">AD</div>
    <h1>租户后台</h1>
    <p class="sub"><?= htmlspecialchars($siteName) ?><br>使用你的分发页用户名登录</p>
    <form method="POST">
        <label>用户名</label>
        <input type="text" name="username" value="<?= htmlspecialchars($prefill) ?>" required autofocus autocomplete="username" placeholder="例如 leon">
        <label>密码</label>
        <input type="password" name="password" required autocomplete="current-password">
        <button type="submit">登 录</button>
    </form>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <p class="home"><a href="/">← 返回 AppDown 首页</a></p>
</div>
</body>
</html>
