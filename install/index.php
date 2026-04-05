<?php
/**
 * 安装脚本 - 初始化数据库
 * 安装完成后自动生成 install.lock 锁定文件，无需手动删除
 */

$lockFile = __DIR__ . '/install.lock';

// 已安装：显示警告并记录IP
if (file_exists($lockFile)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $time = date('Y-m-d H:i:s');
    @file_put_contents(__DIR__ . '/access.log', "[{$time}] 非法访问尝试 IP: {$ip}\n", FILE_APPEND);
    http_response_code(403);
    die('<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>禁止访问</title>
    <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:system-ui,sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh}
    .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:500px;text-align:center}
    h2{color:#e74c3c;margin-bottom:12px}p{color:#666;line-height:1.8;margin-top:8px}.ip{background:#f8f8f8;padding:8px 16px;border-radius:6px;font-family:monospace;margin-top:16px;color:#333;display:inline-block}</style>
    </head><body><div class="card">
    <h2>⚠ 非法访问</h2>
    <p>程序已经初始化安装过，当前操作已被记录。</p>
    <p>如需重新安装，请手动删除 <code>install/install.lock</code> 文件。</p>
    <div class="ip">您的IP: ' . htmlspecialchars($ip) . '</div>
    </div></body></html>');
}

// 环境检测
function check_environment(): array {
    $checks = [];

    // PHP版本
    $phpVer = PHP_VERSION;
    $checks['php_version'] = [
        'name' => 'PHP 版本',
        'required' => '>= 8.0',
        'current' => $phpVer,
        'pass' => version_compare($phpVer, '8.0.0', '>='),
    ];

    // PDO SQLite
    $checks['pdo_sqlite'] = [
        'name' => 'PDO SQLite 扩展',
        'required' => '已启用',
        'current' => extension_loaded('pdo_sqlite') ? '已启用' : '未启用',
        'pass' => extension_loaded('pdo_sqlite'),
    ];

    // fileinfo
    $checks['fileinfo'] = [
        'name' => 'Fileinfo 扩展',
        'required' => '已启用',
        'current' => extension_loaded('fileinfo') ? '已启用' : '未启用',
        'pass' => extension_loaded('fileinfo'),
    ];

    // JSON
    $checks['json'] = [
        'name' => 'JSON 扩展',
        'required' => '已启用',
        'current' => extension_loaded('json') ? '已启用' : '未启用',
        'pass' => extension_loaded('json'),
    ];

    // data 目录可写
    $dataDir = dirname(__DIR__) . '/data';
    if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
    $checks['data_writable'] = [
        'name' => 'data/ 目录可写',
        'required' => '可写',
        'current' => is_writable($dataDir) ? '可写' : '不可写',
        'pass' => is_writable($dataDir),
    ];

    // uploads 目录可写
    $uploadsDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);
    $checks['uploads_writable'] = [
        'name' => 'uploads/ 目录可写',
        'required' => '可写',
        'current' => is_writable($uploadsDir) ? '可写' : '不可写',
        'pass' => is_writable($uploadsDir),
    ];

    // install 目录可写（用于写 lock 文件）
    $checks['install_writable'] = [
        'name' => 'install/ 目录可写',
        'required' => '可写',
        'current' => is_writable(__DIR__) ? '可写' : '不可写',
        'pass' => is_writable(__DIR__),
    ];

    return $checks;
}

$envChecks = check_environment();
$envAllPass = !in_array(false, array_column($envChecks, 'pass'));

require_once dirname(__DIR__) . '/includes/init.php';

// 二次检查：数据库中已有管理员
$pdo = get_db();
$exists = $pdo->query("SELECT COUNT(*) as c FROM admin_users")->fetch()['c'];
if ($exists > 0) {
    @file_put_contents($lockFile, json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'note' => 'Lock file recreated from existing data'
    ]));
    http_response_code(403);
    die('<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>已安装</title>
    <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:system-ui,sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh}
    .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:460px;text-align:center}
    h2{color:#e67e22;margin-bottom:12px}p{color:#666;line-height:1.8}</style>
    </head><body><div class="card">
    <h2>已安装</h2>
    <p>检测到数据库中已有管理员账号，已自动锁定安装程序。</p>
    <p style="margin-top:12px;"><a href="/admin/login.php" style="color:#007AFF">前往后台登录 →</a></p>
    </div></body></html>');
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $siteName = trim($_POST['site_name'] ?? '');

    if (strlen($username) < 3 || strlen($password) < 6) {
        $msg = '用户名至少3位，密码至少6位';
    } else {
        if (empty($siteName)) $siteName = 'APP下载中心';

        // 创建管理员
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password) VALUES (?, ?)');
        $stmt->execute([$username, $hash]);

        // 导入默认站点设置
        $settings = [
            'site_title'        => $siteName,
            'site_heading'      => $siteName,
            'logo_url'          => '',
            'favicon_url'       => '',
            'notice_text'       => '',
            'notice_enabled'    => '0',
            'copyright'         => '© ' . date('Y') . ' ' . $siteName . '. All rights reserved.',
            'carousel_interval' => '4000',
            'stats_downloads'   => '100000',
            'stats_rating'      => '4.9',
            'stats_daily_active'=> '1000',
            'font_url'          => '',
            'font_family'       => 'system-ui',
        ];
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO site_settings (setting_key, setting_val) VALUES (?, ?)');
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // 示例应用
        $pdo->exec("INSERT INTO apps (slug, name, icon, theme_color, sort_order) VALUES ('demo', '示例应用', 'fas fa-mobile-alt', '#007AFF', 1)");
        $demoId = $pdo->lastInsertId();

        // 示例下载按钮
        $dlStmt = $pdo->prepare('INSERT INTO app_downloads (app_id, btn_type, btn_text, btn_subtext, href, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        $dlStmt->execute([$demoId, 'android', 'Android', '安卓版', '#', 1]);
        $dlStmt->execute([$demoId, 'ios', 'iOS', '苹果版', '#', 2]);
        $dlStmt->execute([$demoId, 'web', 'Web', '网页版', '#', 3]);

        // 示例特色卡片
        $fStmt = $pdo->prepare('INSERT INTO feature_cards (title, description, icon, sort_order) VALUES (?, ?, ?, ?)');
        $fStmt->execute(['海量资源', '丰富的内容资源，持续更新', 'fas fa-database', 1]);
        $fStmt->execute(['高清播放', '高清流畅播放，画质清晰', 'fas fa-play-circle', 2]);
        $fStmt->execute(['多端支持', '支持Android、iOS、Web多平台', 'fas fa-laptop', 3]);

        // 示例友情链接
        $lStmt = $pdo->prepare('INSERT INTO friend_links (name, url, sort_order) VALUES (?, ?, ?)');
        $lStmt->execute(['示例链接', '#', 1]);

        // 写入锁定文件
        $lockData = [
            'installed_at' => date('Y-m-d H:i:s'),
            'admin_user'   => $username,
            'site_name'    => $siteName,
            'ip'           => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];
        file_put_contents($lockFile, json_encode($lockData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>安装成功</title>
        <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:system-ui,sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh}
        .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:460px;text-align:center}
        h2{color:#27ae60;margin-bottom:16px}p{color:#666;line-height:1.8;margin-top:8px}strong{color:#333}.lock-info{background:#f0fdf4;border:1px solid #bbf7d0;padding:12px 16px;border-radius:8px;margin-top:16px;color:#166534;font-size:.9em}</style>
        </head><body><div class="card">
        <h2>安装成功！</h2>
        <p>管理员: <strong>' . htmlspecialchars($username) . '</strong></p>
        <p>站点名称: <strong>' . htmlspecialchars($siteName) . '</strong></p>
        <p style="margin-top:16px;">请登录后台添加您的应用、上传图片、配置下载链接。</p>
        <div class="lock-info">安装程序已自动锁定（install.lock），无需手动删除文件。</div>
        <p style="margin-top:16px;"><a href="/admin/login.php" style="color:#007AFF;font-size:1.1em;">前往后台登录 →</a></p>
        </div></body></html>';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装 - AppDown</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 480px; }
        h1 { font-size: 1.5em; margin-bottom: 4px; text-align: center; }
        p.sub { color: #666; text-align: center; margin-bottom: 24px; font-size: 0.9em; }
        label { display: block; font-weight: 600; margin-bottom: 6px; margin-top: 16px; font-size: 0.95em; }
        label small { font-weight: 400; color: #999; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1em; }
        input:focus { outline: none; border-color: #007AFF; box-shadow: 0 0 0 3px rgba(0,122,255,0.15); }
        button { width: 100%; padding: 12px; background: #007AFF; color: white; border: none; border-radius: 8px; font-size: 1em; font-weight: 600; cursor: pointer; margin-top: 24px; }
        button:hover { background: #0066e0; }
        button:disabled { background: #ccc; cursor: not-allowed; }
        .error { color: #e74c3c; text-align: center; margin-top: 12px; font-size: 0.9em; }
        .env-check { margin-bottom: 20px; }
        .env-check h3 { font-size: 1em; margin-bottom: 10px; color: #333; }
        .env-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-radius: 6px; margin-bottom: 4px; font-size: 0.9em; }
        .env-item:nth-child(even) { background: #f9f9f9; }
        .env-name { color: #333; }
        .env-val { display: flex; align-items: center; gap: 6px; }
        .env-current { color: #666; font-family: monospace; font-size: 0.85em; }
        .env-pass { color: #27ae60; font-weight: bold; }
        .env-fail { color: #e74c3c; font-weight: bold; }
        .env-warn { background: #fef3cd; color: #856404; padding: 10px 14px; border-radius: 8px; margin-top: 10px; font-size: 0.85em; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <h1>AppDown 安装</h1>
        <p class="sub">创建管理员账号，初始化数据库</p>

        <div class="env-check">
            <h3>环境检测</h3>
            <?php foreach ($envChecks as $check): ?>
            <div class="env-item">
                <span class="env-name"><?= $check['name'] ?></span>
                <span class="env-val">
                    <span class="env-current"><?= htmlspecialchars($check['current']) ?></span>
                    <span class="<?= $check['pass'] ? 'env-pass' : 'env-fail' ?>"><?= $check['pass'] ? '&#10004;' : '&#10008;' ?></span>
                </span>
            </div>
            <?php endforeach; ?>
            <?php if (!$envAllPass): ?>
            <div class="env-warn">环境检测未通过，请先修复上述标红项再进行安装。</div>
            <?php endif; ?>
        </div>

        <form method="POST">
            <label>站点名称 <small>(可稍后在后台修改)</small></label>
            <input type="text" name="site_name" placeholder="如: XX影视APP下载中心" value="<?= htmlspecialchars($_POST['site_name'] ?? '') ?>">
            <label>管理员用户名</label>
            <input type="text" name="username" required minlength="3" placeholder="至少3个字符" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <label>管理员密码</label>
            <input type="password" name="password" required minlength="6" placeholder="至少6个字符">
            <button type="submit" <?= $envAllPass ? '' : 'disabled title="请先修复环境问题"' ?>>开始安装</button>
        </form>
        <?php if ($msg): ?>
            <p class="error"><?= htmlspecialchars($msg) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
