<?php
/**
 * 系统信息页 - 显示环境检测和运行状态
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

$pdo = get_db();

// 环境检测
$checks = [];

// PHP版本
$phpVer = PHP_VERSION;
$checks[] = ['PHP 版本', '>= 8.0', $phpVer, version_compare($phpVer, '8.0.0', '>=')];

// PDO SQLite
$checks[] = ['PDO SQLite 扩展', '已启用', extension_loaded('pdo_sqlite') ? '已启用' : '未启用', extension_loaded('pdo_sqlite')];

// Fileinfo
$checks[] = ['Fileinfo 扩展', '已启用', extension_loaded('fileinfo') ? '已启用' : '未启用', extension_loaded('fileinfo')];

// JSON
$checks[] = ['JSON 扩展', '已启用', extension_loaded('json') ? '已启用' : '未启用', extension_loaded('json')];

// Session
$checks[] = ['Session 支持', '已启用', extension_loaded('session') ? '已启用' : '未启用', extension_loaded('session')];

// data 目录
$dataDir = __DIR__ . '/../data';
$checks[] = ['data/ 目录可写', '可写', is_writable($dataDir) ? '可写' : '不可写', is_writable($dataDir)];

// uploads 目录
$uploadsDir = __DIR__ . '/../uploads';
$checks[] = ['uploads/ 目录可写', '可写', is_writable($uploadsDir) ? '可写' : '不可写', is_writable($uploadsDir)];

// install.lock
$lockFile = __DIR__ . '/../install/install.lock';
$lockExists = file_exists($lockFile);
$checks[] = ['安装锁定文件', '已锁定', $lockExists ? '已锁定' : '未锁定', $lockExists];

// 系统信息
$dbPath = $dataDir . '/app.db';
$dbSize = file_exists($dbPath) ? filesize($dbPath) : 0;

$sysInfo = [
    ['PHP 版本', phpversion()],
    ['PHP SAPI', php_sapi_name()],
    ['服务器软件', $_SERVER['SERVER_SOFTWARE'] ?? '未知'],
    ['操作系统', PHP_OS . ' ' . php_uname('r')],
    ['服务器时间', date('Y-m-d H:i:s')],
    ['时区', date_default_timezone_get()],
    ['最大上传', ini_get('upload_max_filesize')],
    ['POST 限制', ini_get('post_max_size')],
    ['内存限制', ini_get('memory_limit')],
    ['执行时限', ini_get('max_execution_time') . '秒'],
    ['SQLite 版本', $pdo->query('SELECT sqlite_version()')->fetchColumn()],
    ['数据库大小', number_format($dbSize / 1024, 1) . ' KB'],
    ['数据库路径', realpath($dbPath) ?: $dbPath],
    ['项目根目录', realpath(__DIR__ . '/..')],
];

// 统计信息
$appCount = $pdo->query('SELECT COUNT(*) FROM apps')->fetchColumn();
$dlBtnCount = $pdo->query('SELECT COUNT(*) FROM app_downloads')->fetchColumn();
$imgCount = $pdo->query('SELECT COUNT(*) FROM app_images')->fetchColumn();
$featureCount = $pdo->query('SELECT COUNT(*) FROM feature_cards')->fetchColumn();
$linkCount = $pdo->query('SELECT COUNT(*) FROM friend_links')->fetchColumn();

admin_header('系统信息', 'system');
?>

<div class="page-header"><h1>系统信息</h1></div>

<div class="card">
    <h3>环境检测</h3>
    <table class="table" style="margin-top:12px;">
        <thead><tr><th>检测项</th><th>要求</th><th>当前</th><th>状态</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c[0]) ?></td>
                <td><?= htmlspecialchars($c[1]) ?></td>
                <td style="font-family:monospace;"><?= htmlspecialchars($c[2]) ?></td>
                <td style="font-size:1.2em;text-align:center;"><?= $c[3]
                    ? '<span style="color:#27ae60;">&#10004;</span>'
                    : '<span style="color:#e74c3c;">&#10008;</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>运行环境</h3>
    <table class="table" style="margin-top:12px;">
        <tbody>
        <?php foreach ($sysInfo as $info): ?>
            <tr>
                <td style="font-weight:600;width:140px;"><?= $info[0] ?></td>
                <td style="font-family:monospace;word-break:break-all;"><?= htmlspecialchars($info[1]) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>数据统计</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-top:12px;">
        <div style="background:#f0f9ff;padding:16px;border-radius:8px;text-align:center;">
            <div style="font-size:1.8em;font-weight:700;color:#007AFF;"><?= $appCount ?></div>
            <div style="color:#666;font-size:0.85em;margin-top:4px;">应用数</div>
        </div>
        <div style="background:#f0fdf4;padding:16px;border-radius:8px;text-align:center;">
            <div style="font-size:1.8em;font-weight:700;color:#27ae60;"><?= $dlBtnCount ?></div>
            <div style="color:#666;font-size:0.85em;margin-top:4px;">下载按钮</div>
        </div>
        <div style="background:#fef3c7;padding:16px;border-radius:8px;text-align:center;">
            <div style="font-size:1.8em;font-weight:700;color:#d97706;"><?= $imgCount ?></div>
            <div style="color:#666;font-size:0.85em;margin-top:4px;">轮播图</div>
        </div>
        <div style="background:#fdf2f8;padding:16px;border-radius:8px;text-align:center;">
            <div style="font-size:1.8em;font-weight:700;color:#db2777;"><?= $featureCount ?></div>
            <div style="color:#666;font-size:0.85em;margin-top:4px;">特色卡片</div>
        </div>
        <div style="background:#f5f3ff;padding:16px;border-radius:8px;text-align:center;">
            <div style="font-size:1.8em;font-weight:700;color:#7c3aed;"><?= $linkCount ?></div>
            <div style="color:#666;font-size:0.85em;margin-top:4px;">友情链接</div>
        </div>
    </div>
</div>

<div class="card">
    <h3>已加载 PHP 扩展</h3>
    <p style="margin-top:10px;color:#666;font-size:0.85em;line-height:2;word-break:break-all;">
        <?= implode('、', get_loaded_extensions()) ?>
    </p>
</div>

<?php admin_footer(); ?>
