<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/super_auth.php';

if (!file_exists(__DIR__ . '/../install/install.lock')) {
    header('Location: /install/');
    exit;
}
if (is_super_logged_in()) {
    header('Location: /super/dashboard.php');
    exit;
}

$pdo = get_saas_db();
$pdo->exec("
    CREATE TABLE IF NOT EXISTS super_login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_super_login_attempts ON super_login_attempts(ip, attempted_at);
");
$error = '';
$maxAttempts = 5;
$lockMinutes = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = get_client_ip();
    $cutoff = date('Y-m-d H:i:s', time() - $lockMinutes * 60);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM super_login_attempts WHERE ip = ? AND attempted_at > ?');
    $stmt->execute([$ip, $cutoff]);
    $recent = (int)$stmt->fetchColumn();
    if ($recent >= $maxAttempts) {
        $error = "登录尝试过多，请 {$lockMinutes} 分钟后再试";
    } elseif (super_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        $pdo->prepare('DELETE FROM super_login_attempts WHERE ip = ?')->execute([$ip]);
        header('Location: /super/dashboard.php');
        exit;
    } else {
        $pdo->prepare('INSERT INTO super_login_attempts (ip) VALUES (?)')->execute([$ip]);
        $left = $maxAttempts - $recent - 1;
        $error = $left <= 0 ? "账号或密码错误，已锁定 {$lockMinutes} 分钟" : "账号或密码错误（还可尝试 {$left} 次）";
    }
}
?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>AppDown 超级后台</title>
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;font-family:system-ui,-apple-system,sans-serif;background:#090d18;color:#eaf0ff}.card{width:min(390px,100%);background:#111827;border:1px solid #243047;border-radius:20px;padding:38px;box-shadow:0 28px 80px rgba(0,0,0,.38)}.mark{width:58px;height:58px;border-radius:17px;display:grid;place-items:center;margin:0 auto 18px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);font-weight:800}h1{text-align:center;font-size:1.45rem;margin:0 0 7px}.sub{text-align:center;color:#94a3b8;font-size:.9rem;margin:0 0 25px}label{display:block;margin:16px 0 6px;font-weight:650;font-size:.88rem}input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid #334155;background:#0b1220;color:#fff;font-size:1rem}input:focus{outline:0;border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.15)}button{width:100%;margin-top:25px;padding:12px;border:0;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);color:#fff;font-size:1rem;font-weight:700;cursor:pointer}.error{background:#3b1519;color:#fecaca;padding:9px;border-radius:9px;font-size:.87rem;text-align:center;margin:15px 0 0}.back{text-align:center;margin-top:18px}.back a{color:#93c5fd;text-decoration:none;font-size:.85rem}</style></head><body><div class="card"><div class="mark">S</div><h1>超级后台</h1><p class="sub">管理 AppDown SaaS 租户</p><form method="post"><label>超级管理员</label><input name="username" required autofocus autocomplete="username"><label>密码</label><input type="password" name="password" required autocomplete="current-password"><button>登 录</button></form><?php if($error):?><p class="error"><?=htmlspecialchars($error)?></p><?php endif;?><div class="back"><a href="/">← 返回首页</a></div></div></body></html>
