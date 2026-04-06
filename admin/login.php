<?php
/**
 * 后台登录页
 */

require_once __DIR__ . '/../includes/init.php';

// 未安装时跳转到安装页
$lockFile = __DIR__ . '/../install/install.lock';
if (!file_exists($lockFile)) {
    header('Location: /install/');
    exit;
}

if (is_logged_in()) {
    header('Location: /admin/dashboard.php');
    exit;
}

// 读取站点名称和验证码开关
$pdo = get_db();
$settingsRows = $pdo->query("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('site_title','captcha_enabled')")->fetchAll();
$settings = [];
foreach ($settingsRows as $r) $settings[$r['setting_key']] = $r['setting_val'];
$siteName = $settings['site_title'] ?? 'AppDown';
$captchaEnabled = ($settings['captcha_enabled'] ?? '0') === '1';

// 生成算术验证码
if ($captchaEnabled) {
    if (empty($_SESSION['captcha_a']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $a = rand(1, 20);
        $b = rand(1, 20);
        $_SESSION['captcha_a'] = $a;
        $_SESSION['captcha_b'] = $b;
        $_SESSION['captcha_answer'] = $a + $b;
    }
}

// 登录频率限制
$maxAttempts = 5;
$lockMinutes = 15;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // 基于数据库的登录频率限制（不可被清cookie绕过）
    $cutoff = date('Y-m-d H:i:s', time() - $lockMinutes * 60);
    $stmt = $pdo->prepare('SELECT COUNT(*) as c FROM login_attempts WHERE ip = ? AND attempted_at > ?');
    $stmt->execute([$ip, $cutoff]);
    $recentAttempts = (int)$stmt->fetch()['c'];

    if ($recentAttempts >= $maxAttempts) {
        $error = "登录尝试过多，请 {$lockMinutes} 分钟后再试";
    } else {
        // 验证码校验
        if ($captchaEnabled) {
            $userAnswer = (int)($_POST['captcha'] ?? 0);
            if ($userAnswer !== ($_SESSION['captcha_answer'] ?? -1)) {
                $error = '验证码错误';
                // 重新生成
                $a = rand(1, 20); $b = rand(1, 20);
                $_SESSION['captcha_a'] = $a;
                $_SESSION['captcha_b'] = $b;
                $_SESSION['captcha_answer'] = $a + $b;
            }
        }

        if (!$error) {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (do_login($username, $password)) {
                // 登录成功，清除该IP的失败记录
                $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
                header('Location: /admin/dashboard.php');
                exit;
            } else {
                // 记录失败
                $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
                $recentAttempts++;
                $left = $maxAttempts - $recentAttempts;
                if ($left <= 0) {
                    $error = "登录失败次数过多，已锁定 {$lockMinutes} 分钟";
                } else {
                    $error = "用户名或密码错误（还可尝试 {$left} 次）";
                }

                // 重新生成验证码
                if ($captchaEnabled) {
                    $a = rand(1, 20); $b = rand(1, 20);
                    $_SESSION['captcha_a'] = $a;
                    $_SESSION['captcha_b'] = $b;
                    $_SESSION['captcha_answer'] = $a + $b;
                }
            }
        }
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
        .captcha-row { display: flex; gap: 10px; align-items: center; }
        .captcha-row input { flex: 1; }
        .captcha-q { background: #f0f0f5; padding: 10px 16px; border-radius: 10px; font-size: 1em; font-weight: 600; color: #333; white-space: nowrap; user-select: none; }
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
<?php if ($captchaEnabled): ?>
            <label>验证码</label>
            <div class="captcha-row">
                <div class="captcha-q"><?= $_SESSION['captcha_a'] ?? 0 ?> + <?= $_SESSION['captcha_b'] ?? 0 ?> = ?</div>
                <input type="number" name="captcha" required placeholder="输入计算结果">
            </div>
<?php endif; ?>
            <button type="submit">登 录</button>
        </form>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
