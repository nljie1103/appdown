<?php
/**
 * 一次性安装脚本 - 初始化数据库并导入种子数据
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

    if (strlen($username) < 3 || strlen($password) < 6) {
        $msg = '用户名至少3位，密码至少6位';
    } else {
        // 创建管理员
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password) VALUES (?, ?)');
        $stmt->execute([$username, $hash]);

        // 导入默认站点设置
        $settings = [
            'site_title'        => '杰哩杰哩影视APP - 官方下载',
            'site_heading'      => '杰哩杰哩影视APP综合下载中心',
            'logo_url'          => 'img/logo.png',
            'favicon_url'       => 'img/favicon.ico',
            'notice_text'       => '推荐使用sk影视，sk有多接口轮询功能和一起看功能，get推荐原生安卓，流程一点，支持苹果手机端，ipad端，pc端敬请期待...',
            'notice_enabled'    => '1',
            'copyright'         => '© 2025 杰哩杰哩影视APP. All rights reserved.',
            'carousel_interval' => '4000',
            'stats_downloads'   => '999999',
            'stats_rating'      => '4.9',
            'stats_daily_active'=> '9999',
            'font_url'          => 'img/1.ttf',
            'font_family'       => 'CustomFont',
        ];
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO site_settings (setting_key, setting_val) VALUES (?, ?)');
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // 导入应用数据
        $apps = [
            ['sk', 'sk影视', 'fas fa-clapperboard', '#3DDC84', 'itms-services://?action=download-manifest&url=https://ysapp.jiuliu.org/ios/app/sk-jljl.plist', 'Etisalat - Emirates Telecommunications Corporation', '杰哩杰哩是一款聚合了pptv、优酷、爱奇艺、腾讯视频、风行等近30家影视视频软件；软件免费看平台的付费视频、VIP视频、超前点播等视频，电影院热播电影抢先看。', '7.2.3', '4.5 MB', 1],
            ['get', 'get影视', 'fas fa-tv', '#007AFF', 'itms-services://?action=download-manifest&url=https://ysapp.jiuliu.org/ios/app/get-jljl.plist', 'Etisalat - Emirates Telecommunications Corporation', '聚合影视平台，支持多源切换、Flutter原生UI，流畅体验。', '3.1.0', '12 MB', 2],
            ['qianyue', '千月影视', 'fas fa-moon', '#FF69B4', '', '', '', '', '', 3],
            ['lecai', '乐彩影视', 'fas fa-play-circle', '#9B59B6', '', '', '', '', '', 4],
            ['lvdou', '绿豆影视', 'fas fa-seedling', '#F1C40F', '', '', '', '', '', 5],
            ['tvbox', 'TVBox', 'fas fa-tv', '#1A1A1A', '', '', '', '', '', 6],
        ];
        $appStmt = $pdo->prepare('INSERT INTO apps (slug, name, icon, theme_color, ios_plist_url, ios_cert_name, ios_description, ios_version, ios_size, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($apps as $a) {
            $appStmt->execute($a);
        }

        // 获取刚插入的app ID映射
        $appIds = [];
        $rows = $pdo->query('SELECT id, slug FROM apps')->fetchAll();
        foreach ($rows as $r) {
            $appIds[$r['slug']] = $r['id'];
        }

        // 导入下载按钮
        $downloads = [
            ['sk', 'android', 'Android', '点击下载', 'android/sk-安卓.apk', 1],
            ['sk', 'windows', 'Windows', '电脑版', 'pc/sk-电脑.exe', 2],
            ['sk', 'ios', 'IOS', '苹果版', '/ios/?app=sk', 3],
            ['sk', 'web', 'WEB', '网页版', 'https://ying.jiuliu.org', 4],
            ['get', 'android', 'Android1', '安卓原生版', 'android/get-安卓原生.apk', 1],
            ['get', 'android', 'Android2', '安卓Flutter', 'android/get-安卓-flutter.apk', 2],
            ['get', 'ios', 'IOS', 'iOS版', '/ios/?app=get', 3],
            ['get', 'web', 'WEB', '网页播放器', 'https://art.jiuliu.org', 4],
            ['qianyue', 'android', 'Android', '安卓版', '#', 1],
            ['qianyue', 'ios', 'IOS', 'iOS版', '#', 2],
            ['qianyue', 'web', 'WEB', '网页版', '#', 3],
            ['lecai', 'android', 'Android', '安卓版', '#', 1],
            ['lecai', 'ios', 'IOS', 'iOS版', '#', 2],
            ['lecai', 'web', 'WEB', '网页版', '#', 3],
            ['lvdou', 'android', 'Android', '安卓版', '#', 1],
            ['lvdou', 'ios', 'IOS', 'iOS版', '#', 2],
            ['lvdou', 'web', 'WEB', '网页版', '#', 3],
            ['tvbox', 'android', 'Android', '安卓版', '#', 1],
            ['tvbox', 'tv', 'TV', '电视版', '#', 2],
            ['tvbox', 'web', 'WEB', '网页版', '#', 3],
        ];
        $dlStmt = $pdo->prepare('INSERT INTO app_downloads (app_id, btn_type, btn_text, btn_subtext, href, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($downloads as $d) {
            $dlStmt->execute([$appIds[$d[0]], $d[1], $d[2], $d[3], $d[4], $d[5]]);
        }

        // 导入轮播图
        $imgStmt = $pdo->prepare('INSERT INTO app_images (app_id, image_url, alt_text, sort_order) VALUES (?, ?, ?, ?)');
        $imgCounts = ['sk' => 10, 'get' => 7, 'qianyue' => 10, 'lecai' => 10, 'lvdou' => 10, 'tvbox' => 10];
        $descriptions = ['首页界面','播放界面','搜索功能','分类浏览','个人中心','下载管理','设置选项','收藏列表','历史记录','推荐页面'];
        foreach ($imgCounts as $slug => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $url = "img/{$slug}/{$slug}-{$i}.webp";
                $alt = $descriptions[$i - 1] ?? "截图{$i}";
                $imgStmt->execute([$appIds[$slug], $url, $alt, $i]);
            }
        }

        // 导入特色卡片
        $features = [
            ['海量资源', '海量影视资源，持续更新，满足您的观影需求', 1],
            ['高清播放', '高清流畅播放，画质清晰锐利', 2],
            ['投屏观看', '支持投屏观看，享受家庭影院级观影体验', 3],
            ['智能推荐', '个性化推荐，精准找片，发现更多好内容', 4],
            ['离线缓存', '支持视频离线缓存，随时随地观看无压力', 5],
            ['无广告', '无广告，无弹窗，纯净观影体验', 6],
        ];
        $fStmt = $pdo->prepare('INSERT INTO feature_cards (title, description, sort_order) VALUES (?, ?, ?)');
        foreach ($features as $f) {
            $fStmt->execute($f);
        }

        // 导入友情链接
        $links = [
            ['链接1', '#', 1], ['链接2', '#', 2], ['链接3', '#', 3],
            ['链接4', '#', 4], ['链接5', '#', 5],
        ];
        $lStmt = $pdo->prepare('INSERT INTO friend_links (name, url, sort_order) VALUES (?, ?, ?)');
        foreach ($links as $l) {
            $lStmt->execute($l);
        }

        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#3DDC84;">✅ 安装成功！</h2>
        <p>管理员账号: <strong>' . htmlspecialchars($username) . '</strong></p>
        <p>数据库已初始化，种子数据已导入。</p>
        <p style="color:#e74c3c;font-weight:bold;margin-top:20px;">⚠️ 请立即删除此文件 (install.php)！</p>
        <p><a href="/admin/login.php" style="color:#007AFF;">前往后台登录 →</a></p>
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
    <title>安装 - 杰哩杰哩影视APP后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { font-size: 1.5em; margin-bottom: 8px; text-align: center; }
        p.sub { color: #666; text-align: center; margin-bottom: 24px; font-size: 0.9em; }
        label { display: block; font-weight: 600; margin-bottom: 6px; margin-top: 16px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1em; }
        input:focus { outline: none; border-color: #007AFF; box-shadow: 0 0 0 3px rgba(0,122,255,0.15); }
        button { width: 100%; padding: 12px; background: #007AFF; color: white; border: none; border-radius: 8px; font-size: 1em; font-weight: 600; cursor: pointer; margin-top: 24px; }
        button:hover { background: #0066e0; }
        .error { color: #e74c3c; text-align: center; margin-top: 12px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="card">
        <h1>系统安装</h1>
        <p class="sub">创建管理员账号，初始化数据库</p>
        <form method="POST">
            <label>管理员用户名</label>
            <input type="text" name="username" required minlength="3" placeholder="至少3个字符">
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
