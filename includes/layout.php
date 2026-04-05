<?php
/**
 * 后台公共布局
 */

function admin_header(string $title, string $currentPage = ''): void {
    $user = $_SESSION['admin_user'] ?? 'Admin';
    $csrf = csrf_token();
    $nav = [
        ['dashboard', '仪表盘', 'fas fa-chart-line', '/admin/dashboard.php'],
        ['apps',      '应用管理', 'fas fa-mobile-alt', '/admin/apps.php'],
        ['settings',  '站点设置', 'fas fa-cog', '/admin/settings.php'],
        ['features',  '特色卡片', 'fas fa-star', '/admin/features.php'],
        ['links',     '友情链接', 'fas fa-link', '/admin/links.php'],
        ['fonts',     '字体管理', 'fas fa-font', '/admin/fonts.php'],
        ['code',      '自定义代码', 'fas fa-code', '/admin/custom-code.php'],
    ];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title><?= htmlspecialchars($title) ?> - 后台管理</title>
    <link rel="stylesheet" href="/admin/assets/admin.css">
    <link rel="stylesheet" href="/static/fontawesome-free-7.1.0-web/css/all.min.css">
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2>影视APP后台</h2>
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
    <script src="/admin/assets/admin.js"></script>
</body>
</html>
<?php
}
