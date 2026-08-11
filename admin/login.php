<?php
/**
 * AppDown Admin 2.0 登录页。
 */
require_once __DIR__ . '/../includes/init.php';

$lockFile = __DIR__ . '/../install/install.lock';
if (!file_exists($lockFile)) {
    header('Location: /install/');
    exit;
}

if (is_logged_in()) {
    header('Location: /admin/app.php#/dashboard');
    exit;
}

$pdo = get_db();
$settingsRows = $pdo->query("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('site_title','captcha_enabled')")->fetchAll();
$settings = [];
foreach ($settingsRows as $r) $settings[$r['setting_key']] = $r['setting_val'];
$siteName = $settings['site_title'] ?? 'AppDown';
$captchaEnabled = ($settings['captcha_enabled'] ?? '0') === '1';

if ($captchaEnabled) {
    if (empty($_SESSION['captcha_a']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $a = random_int(1, 20);
        $b = random_int(1, 20);
        $_SESSION['captcha_a'] = $a;
        $_SESSION['captcha_b'] = $b;
        $_SESSION['captcha_answer'] = $a + $b;
    }
}

$maxAttempts = 5;
$lockMinutes = 15;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = get_client_ip();
    $cutoff = date('Y-m-d H:i:s', time() - $lockMinutes * 60);
    $stmt = $pdo->prepare('SELECT COUNT(*) as c FROM login_attempts WHERE ip = ? AND attempted_at > ?');
    $stmt->execute([$ip, $cutoff]);
    $recentAttempts = (int)$stmt->fetch()['c'];

    if ($recentAttempts >= $maxAttempts) {
        $error = "登录尝试过多，请 {$lockMinutes} 分钟后再试";
    } else {
        if ($captchaEnabled) {
            $userAnswer = (int)($_POST['captcha'] ?? 0);
            if ($userAnswer !== ($_SESSION['captcha_answer'] ?? -1)) {
                $error = '验证码错误';
                $a = random_int(1, 20); $b = random_int(1, 20);
                $_SESSION['captcha_a'] = $a;
                $_SESSION['captcha_b'] = $b;
                $_SESSION['captcha_answer'] = $a + $b;
            }
        }

        if (!$error) {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if (do_login($username, $password)) {
                $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
                header('Location: /admin/app.php#/dashboard');
                exit;
            }
            $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
            $recentAttempts++;
            $left = $maxAttempts - $recentAttempts;
            $error = $left <= 0 ? "登录失败次数过多，已锁定 {$lockMinutes} 分钟" : "用户名或密码错误（还可尝试 {$left} 次）";
            if ($captchaEnabled) {
                $a = random_int(1, 20); $b = random_int(1, 20);
                $_SESSION['captcha_a'] = $a;
                $_SESSION['captcha_b'] = $b;
                $_SESSION['captcha_answer'] = $a + $b;
            }
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="color-scheme" content="light dark">
<title><?= htmlspecialchars($siteName) ?> · AppDown Admin</title>
<style>
:root{color-scheme:light;--bg:#f7f8fa;--surface:#fff;--text:#18181b;--muted:#71717a;--border:#e6e8ec;--primary:#4f46e5;--danger:#dc2626}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;display:grid;place-items:center;padding:24px}.login{width:min(390px,100%)}.brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:24px}.mark{width:34px;height:34px;border-radius:10px;background:linear-gradient(145deg,#4f46e5,#7c3aed);display:grid;place-items:center;color:#fff;font-weight:800;box-shadow:0 8px 20px rgba(79,70,229,.22)}.brand b{font-size:15px;letter-spacing:-.02em}.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:26px;box-shadow:0 12px 40px rgba(16,24,40,.07)}h1{font-size:22px;letter-spacing:-.035em;margin:0;text-align:center}.sub{text-align:center;color:var(--muted);font-size:12px;line-height:1.6;margin:7px 0 22px}.field{margin-top:13px}.field label{display:block;font-size:11px;font-weight:700;margin-bottom:6px}.field input{width:100%;height:40px;border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:9px;padding:0 11px;outline:none;font-size:13px}.field input:focus{border-color:#818cf8;box-shadow:0 0 0 3px rgba(79,70,229,.1)}.captcha{display:grid;grid-template-columns:auto 1fr;gap:8px}.captcha-q{height:40px;padding:0 12px;border-radius:9px;background:#f3f4f6;border:1px solid var(--border);display:flex;align-items:center;font-size:12px;font-weight:700;white-space:nowrap}.submit{width:100%;height:40px;border:0;border-radius:9px;background:var(--primary);color:#fff;font-size:12px;font-weight:750;margin-top:20px;cursor:pointer}.submit:hover{background:#4338ca}.error{margin:12px 0 0;padding:10px 11px;border-radius:9px;background:#fef2f2;border:1px solid #fecaca;color:var(--danger);font-size:11px;line-height:1.5}.foot{text-align:center;color:#a1a1aa;font-size:10px;margin-top:16px}@media(prefers-color-scheme:dark){:root{color-scheme:dark;--bg:#0a0a0c;--surface:#111114;--text:#f5f5f6;--muted:#92929c;--border:#27272e;--primary:#818cf8;--danger:#f87171}.captcha-q{background:#18181c}.error{background:#2b1215;border-color:#4b1d22}}
</style>
</head><body><main class="login"><div class="brand"><div class="mark">A</div><b>AppDown Admin 2.0</b></div><section class="card"><h1>欢迎回来</h1><p class="sub"><?= htmlspecialchars($siteName) ?><br>登录后进入全新的 Vue 3 管理后台</p><form method="post"><div class="field"><label>用户名</label><input type="text" name="username" required autofocus autocomplete="username"></div><div class="field"><label>密码</label><input type="password" name="password" required autocomplete="current-password"></div><?php if ($captchaEnabled): ?><div class="field"><label>验证码</label><div class="captcha"><div class="captcha-q"><?= $_SESSION['captcha_a'] ?? 0 ?> + <?= $_SESSION['captcha_b'] ?? 0 ?> = ?</div><input type="number" name="captcha" required inputmode="numeric"></div></div><?php endif; ?><button class="submit" type="submit">登录</button></form><?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?></section><div class="foot">AppDown · Admin 2.0</div></main></body></html>
