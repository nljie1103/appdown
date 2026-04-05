<?php
/**
 * 后台公共布局
 */

function admin_header(string $title, string $currentPage = ''): void {
    $user = $_SESSION['admin_user'] ?? 'Admin';
    $csrf = csrf_token();
    $ver = '20260406b'; // 静态资源版本号，更新后修改此值强制刷新缓存
    $pdo = get_db();
    $siteTitle = $pdo->query("SELECT setting_val FROM site_settings WHERE setting_key='site_title'")->fetchColumn() ?: '管理后台';
    $nav = [
        ['dashboard', '仪表盘', 'fas fa-chart-line', '/admin/dashboard.php'],
        ['apps',      '应用管理', 'fas fa-mobile-alt', '/admin/apps.php'],
        ['attachments','附件管理', 'fas fa-paperclip', '/admin/attachments.php'],
        ['settings',  '站点设置', 'fas fa-cog', '/admin/settings.php'],
        ['features',  '特色卡片', 'fas fa-star', '/admin/features.php'],
        ['links',     '友情链接', 'fas fa-link', '/admin/links.php'],
        ['fonts',     '字体管理', 'fas fa-font', '/admin/fonts.php'],
        ['code',      '自定义代码', 'fas fa-code', '/admin/custom-code.php'],
        ['system',    '系统信息', 'fas fa-server', '/admin/system.php'],
        ['backup',    '导入导出', 'fas fa-exchange-alt', '/admin/backup.php'],
        ['account',   '账户管理', 'fas fa-user-cog', '/admin/account.php'],
    ];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title><?= htmlspecialchars($title) ?> - 后台管理</title>
    <link rel="stylesheet" href="/admin/assets/admin.css?v=<?= $ver ?>">
    <link rel="stylesheet" href="/static/fontawesome-free-7.1.0-web/css/all.min.css">
    <script src="/admin/assets/admin.js?v=<?= $ver ?>"></script>
</head>
<body>
    <button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');this.classList.toggle('active')" aria-label="菜单">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('open');document.querySelector('.sidebar-toggle').classList.remove('active')"></div>
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2><?= htmlspecialchars($siteTitle) ?></h2>
            <small><?= htmlspecialchars($user) ?></small>
        </div>
        <div class="sidebar-nav">
            <?php foreach ($nav as $item): ?>
                <a href="<?= $item[3] ?>" class="<?= $item[0] === $currentPage ? 'active' : '' ?>">
                    <i class="<?= $item[2] ?>"></i> <?= $item[1] ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="sidebar-footer">
            <a href="/admin/logout.php"><i class="fas fa-sign-out-alt"></i> 退出登录</a>
        </div>
    </nav>
    <div class="main-content">
<?php
}

function admin_footer(): void {
?>
    </div>
</body>
</html>
<?php
}
