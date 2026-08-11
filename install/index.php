<?php
/**
 * AppDown SaaS 安装器
 * 只初始化中央控制库和超级管理员；普通租户由 /super 创建。
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'use_strict_mode' => true,
]);

$root = dirname(__DIR__);
$lockFile = __DIR__ . '/install.lock';
$accessLog = __DIR__ . '/access.log';

function install_abort(string $title, string $message, int $status = 400): void {
    http_response_code($status);
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $msgEsc = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $titleEsc . '</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f7fb;font-family:system-ui,sans-serif;color:#172033}.card{max-width:560px;margin:20px;background:#fff;border:1px solid #e6eaf0;border-radius:18px;padding:34px;box-shadow:0 18px 60px rgba(30,50,80,.08)}h2{margin:0 0 12px}p{line-height:1.8;color:#667085}a{color:#2563eb}</style></head><body><div class="card"><h2>' . $titleEsc . '</h2><p>' . $msgEsc . '</p><p><a href="/">返回首页</a></p></div></body></html>';
    exit;
}

if (file_exists($lockFile)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    @file_put_contents($accessLog, '[' . date('Y-m-d H:i:s') . '] 已安装实例访问安装器 IP: ' . $ip . "\n", FILE_APPEND | LOCK_EX);
    install_abort('安装器已锁定', 'AppDown SaaS 已完成初始化。如确需重新部署，请先做好 data/ 与 uploads/ 备份，再由服务器管理员处理 install.lock。', 403);
}

function ensure_install_dir(string $path, int $mode = 0750): bool {
    if (!is_dir($path) && !@mkdir($path, $mode, true) && !is_dir($path)) return false;
    return is_writable($path);
}

function check_environment(string $root): array {
    $dataDir = $root . '/data';
    $uploadsDir = $root . '/uploads';
    $checks = [
        ['name' => 'PHP 版本', 'current' => PHP_VERSION, 'required' => '>= 8.0', 'pass' => version_compare(PHP_VERSION, '8.0.0', '>='), 'optional' => false],
        ['name' => 'PDO SQLite', 'current' => extension_loaded('pdo_sqlite') ? '已启用' : '未启用', 'required' => '必须', 'pass' => extension_loaded('pdo_sqlite'), 'optional' => false],
        ['name' => 'Fileinfo', 'current' => extension_loaded('fileinfo') ? '已启用' : '未启用', 'required' => '必须', 'pass' => extension_loaded('fileinfo'), 'optional' => false],
        ['name' => 'OpenSSL', 'current' => extension_loaded('openssl') ? '已启用' : '未启用', 'required' => '强烈建议', 'pass' => true, 'optional' => true],
        ['name' => 'Zip / ZipArchive', 'current' => class_exists('ZipArchive') ? '已启用' : '未启用', 'required' => '建议（APK/IPA校验与备份）', 'pass' => true, 'optional' => true],
        ['name' => 'Sodium', 'current' => extension_loaded('sodium') ? '已启用' : '未启用', 'required' => '建议（备份 Argon2id）', 'pass' => true, 'optional' => true],
        ['name' => 'cURL', 'current' => extension_loaded('curl') ? '已启用' : '未启用', 'required' => '建议', 'pass' => true, 'optional' => true],
        ['name' => 'GD', 'current' => extension_loaded('gd') ? '已启用' : '未启用', 'required' => '建议（图片处理）', 'pass' => true, 'optional' => true],
        ['name' => 'data 目录', 'current' => ensure_install_dir($dataDir) ? '可写' : '不可写', 'required' => '必须可写', 'pass' => is_dir($dataDir) && is_writable($dataDir), 'optional' => false],
        ['name' => 'uploads 目录', 'current' => ensure_install_dir($uploadsDir, 0755) ? '可写' : '不可写', 'required' => '必须可写', 'pass' => is_dir($uploadsDir) && is_writable($uploadsDir), 'optional' => false],
        ['name' => 'install 目录', 'current' => is_writable(__DIR__) ? '可写' : '不可写', 'required' => '必须可写', 'pass' => is_writable(__DIR__), 'optional' => false],
    ];
    return $checks;
}

$checks = check_environment($root);
$requiredPass = true;
foreach ($checks as $check) {
    if (!$check['optional'] && !$check['pass']) $requiredPass = false;
}

// 只有 SQLite 环境可用时才检查中央库，防止缺扩展时直接 fatal。
if (extension_loaded('pdo_sqlite')) {
    require_once $root . '/includes/saas.php';
    try {
        $control = get_saas_db();
        $existingSuper = (int)$control->query('SELECT COUNT(*) FROM super_users')->fetchColumn();
        if ($existingSuper > 0) {
            @file_put_contents($lockFile, json_encode([
                'mode' => 'saas', 'relocked_at' => date(DATE_ATOM), 'reason' => 'existing_super_user'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            @chmod($lockFile, 0600);
            install_abort('检测到已有实例', '中央控制库中已经存在超级管理员。安装器已自动重新创建 install.lock，以防止覆盖现有账号。', 403);
        }
    } catch (Throwable $e) {
        $control = null;
    }
} else {
    $control = null;
}

$error = '';
$success = false;
if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$requiredPass) {
        $error = '必需环境检查未通过，请先修复服务器环境。';
    } elseif (!hash_equals((string)$_SESSION['install_csrf'], (string)($_POST['_csrf'] ?? ''))) {
        $error = '页面已过期，请刷新后重试。';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
            $error = '超级管理员用户名需为 3-32 位字母、数字或下划线。';
        } elseif (strlen($password) < 8) {
            $error = '超级管理员密码至少 8 位。';
        } elseif ($password !== $confirm) {
            $error = '两次输入的密码不一致。';
        } else {
            try {
                require_once $root . '/includes/saas.php';
                $control = get_saas_db();
                $control->beginTransaction();
                $stmt = $control->prepare('INSERT INTO super_users (username, password) VALUES (?, ?)');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                $control->commit();

                $lockPayload = [
                    'mode' => 'saas',
                    'installed_at' => date(DATE_ATOM),
                    'version' => 'saas-v1.0.0',
                ];
                if (@file_put_contents($lockFile, json_encode($lockPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n", LOCK_EX) === false) {
                    throw new RuntimeException('无法写入 install.lock');
                }
                @chmod($lockFile, 0600);
                $success = true;
                unset($_SESSION['install_csrf']);
            } catch (Throwable $e) {
                if (isset($control) && $control instanceof PDO && $control->inTransaction()) $control->rollBack();
                error_log('[AppDown SaaS install] ' . $e);
                $error = '初始化失败：' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>安装 AppDown SaaS</title>
<style>
*{box-sizing:border-box}body{margin:0;background:linear-gradient(145deg,#edf8ff,#f1edff 48%,#fff5ef);color:#172033;font-family:system-ui,-apple-system,sans-serif;min-height:100vh;padding:44px 20px}.wrap{max-width:880px;margin:auto}.head{text-align:center;margin-bottom:28px}.logo{width:58px;height:58px;border-radius:17px;background:linear-gradient(135deg,#2b8cff,#7c55ef);display:grid;place-items:center;color:#fff;font-weight:800;margin:0 auto 15px;box-shadow:0 10px 30px rgba(64,91,210,.22)}h1{margin:0 0 8px;font-size:2rem}.sub{color:#667085;margin:0;line-height:1.7}.card{background:rgba(255,255,255,.86);border:1px solid rgba(255,255,255,.95);border-radius:20px;padding:24px;box-shadow:0 20px 65px rgba(44,65,98,.1);backdrop-filter:blur(20px);margin-bottom:18px}.card h2{font-size:1.05rem;margin:0 0 16px}.checks{display:grid;grid-template-columns:1fr 1fr;gap:9px}.check{padding:11px 13px;border:1px solid #edf0f4;border-radius:11px;background:#fbfcfe;display:flex;justify-content:space-between;gap:12px;font-size:.88rem}.pass{color:#16803c;font-weight:700}.warn{color:#a16207;font-weight:700}.fail{color:#c53030;font-weight:700}.formgrid{display:grid;gap:14px}label{display:block;font-size:.88rem;font-weight:700;margin-bottom:6px}.input{width:100%;padding:12px 14px;border:1px solid #dce2eb;border-radius:10px;font:inherit;background:#fff}.input:focus{outline:0;border-color:#4f86f7;box-shadow:0 0 0 3px rgba(79,134,247,.12)}button,.btn{border:0;border-radius:10px;background:#172033;color:#fff;padding:13px 18px;font-weight:750;font-size:1rem;cursor:pointer;text-decoration:none;display:inline-block;text-align:center}.btnrow{margin-top:4px}.error{background:#fff1f0;border:1px solid #fecaca;color:#b42318;padding:11px 13px;border-radius:10px;margin-bottom:14px}.success{text-align:center;padding:22px 0}.success h2{font-size:1.45rem;color:#166534}.success p{color:#667085;line-height:1.8}.success .btn{margin-top:12px;background:#2563eb}.tip{font-size:.86rem;color:#667085;line-height:1.7;margin-top:10px}@media(max-width:680px){.checks{grid-template-columns:1fr}body{padding:25px 14px}}
</style></head><body><main class="wrap"><header class="head"><div class="logo">AD</div><h1>安装 AppDown SaaS</h1><p class="sub">初始化中央控制库和超级管理员。安装完成后，在 /super 创建各个独立租户。</p></header>
<?php if($success): ?><section class="card success"><h2>安装完成</h2><p>超级后台已经初始化。下一步创建第一个租户，然后访问 <code>/用户名/</code> 查看独立分发页。</p><a class="btn" href="/super/">进入超级后台</a></section>
<?php else: ?>
<section class="card"><h2>环境检查</h2><div class="checks"><?php foreach($checks as $c): ?><div class="check"><span><?=htmlspecialchars($c['name'])?><br><small style="color:#98a2b3"><?=htmlspecialchars($c['required'])?></small></span><span class="<?=$c['pass']?($c['optional']&&str_contains($c['current'],'未启用')?'warn':'pass'):'fail'?>"><?=htmlspecialchars($c['current'])?></span></div><?php endforeach; ?></div></section>
<section class="card"><h2>创建超级管理员</h2><?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?><form method="post" class="formgrid"><input type="hidden" name="_csrf" value="<?=htmlspecialchars((string)$_SESSION['install_csrf'])?>"><div><label>超级管理员用户名</label><input class="input" name="username" required minlength="3" maxlength="32" pattern="[A-Za-z0-9_]+" autocomplete="username" placeholder="例如 admin"></div><div><label>密码</label><input class="input" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="至少 8 位"></div><div><label>确认密码</label><input class="input" type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div><div class="btnrow"><button type="submit" <?=$requiredPass?'':'disabled style="opacity:.45;cursor:not-allowed"'?>>初始化 SaaS</button></div></form><p class="tip">超级管理员只用于 <code>/super</code> 管理租户，不是普通分发页账号。每个租户的用户名和密码由超级后台单独创建。</p></section>
<?php endif; ?></main></body></html>
