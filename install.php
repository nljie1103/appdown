<?php
/**
 * 一次性安装脚本 - 初始化数据库
 * 使用后请删除此文件！
 */

require_once __DIR__ . '/includes/init.php';

// 防止重复安装
$pdo = get_db();
$exists = $pdo->query("SELECT COUNT(*) as c FROM admin_users")->fetch()['c'];
if ($exists > 0) {
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;text-align:center;">
    <h2 style="color:#e74c3c;">已安装过，请删除 install.php</h2></body></html>');
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

        // 导入默认站点设置（通用模板）
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

        // 导入一个示例应用
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
        $fStmt->execute(['多端支持', '支持Android、iOS、Web多平台', 'fas fa-devices', 3]);

        // 示例友情链接
        $lStmt = $pdo->prepare('INSERT INTO friend_links (name, url, sort_order) VALUES (?, ?, ?)');
        $lStmt->execute(['示例链接', '#', 1]);

        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#27ae60;">安装成功！</h2>
        <p>管理员: <strong>' . htmlspecialchars($username) . '</strong></p>
        <p>站点名称: <strong>' . htmlspecialchars($siteName) . '</strong></p>
        <p style="margin-top:16px;">请登录后台添加您的应用、上传图片、配置下载链接。</p>
        <p style="color:#e74c3c;font-weight:bold;margin-top:20px;">请立即删除此文件 (install.php)！</p>
        <p style="margin-top:16px;"><a href="/admin/login.php" style="color:#007AFF;font-size:1.1em;">前往后台登录 →</a></p>
        </body></html>';
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
        body { font-family: system-ui, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 420px; }
        h1 { font-size: 1.5em; margin-bottom: 4px; text-align: center; }
        p.sub { color: #666; text-align: center; margin-bottom: 24px; font-size: 0.9em; }
        label { display: block; font-weight: 600; margin-bottom: 6px; margin-top: 16px; font-size: 0.95em; }
        label small { font-weight: 400; color: #999; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1em; }
        input:focus { outline: none; border-color: #007AFF; box-shadow: 0 0 0 3px rgba(0,122,255,0.15); }
        button { width: 100%; padding: 12px; background: #007AFF; color: white; border: none; border-radius: 8px; font-size: 1em; font-weight: 600; cursor: pointer; margin-top: 24px; }
        button:hover { background: #0066e0; }
        .error { color: #e74c3c; text-align: center; margin-top: 12px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="card">
        <h1>AppDown 安装</h1>
        <p class="sub">创建管理员账号，初始化数据库</p>
        <form method="POST">
            <label>站点名称 <small>(可稍后在后台修改)</small></label>
            <input type="text" name="site_name" placeholder="如: XX影视APP下载中心" value="<?= htmlspecialchars($_POST['site_name'] ?? '') ?>">
            <label>管理员用户名</label>
            <input type="text" name="username" required minlength="3" placeholder="至少3个字符" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <label>管理员密码</label>
            <input type="password" name="password" required minlength="6" placeholder="至少6个字符">
            <button type="submit">开始安装</button>
        </form>
        <?php if ($msg): ?>
            <p class="error"><?= htmlspecialchars($msg) ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
