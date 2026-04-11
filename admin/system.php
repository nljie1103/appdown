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

// GD
$gdStatus = extension_loaded('gd') ? '已启用' : '未启用';
if (extension_loaded('gd') && function_exists('gd_info')) {
    $gi = gd_info();
    $gdFormats = [];
    if (!empty($gi['WebP Support'])) $gdFormats[] = 'WebP';
    if (!empty($gi['JPEG Support'] ?? $gi['JPG Support'] ?? false)) $gdFormats[] = 'JPEG';
    if (!empty($gi['PNG Support'])) $gdFormats[] = 'PNG';
    if (!empty($gi['GIF Read Support'])) $gdFormats[] = 'GIF';
    $gdStatus .= ' (' . implode(', ', $gdFormats) . ')';
}
$checks[] = ['GD 扩展（图片压缩转换）', '已启用', $gdStatus, extension_loaded('gd')];

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

// Android 构建环境检测
$androidChecks = [];
$androidHome = getenv('ANDROID_HOME') ?: '/opt/android-sdk';

$javaOut = [];
@exec('java -version 2>&1', $javaOut);
$javaVerLine = $javaOut[0] ?? '';
$hasJava = (bool)preg_match('/version\s+"?17/', $javaVerLine);
$javaVer = '';
if (preg_match('/version\s+"([^"]+)"/', $javaVerLine, $m)) $javaVer = $m[1];
$androidChecks[] = ['Java 17 (JDK)', '已安装', $hasJava ? $javaVer : '未安装', $hasJava];

$hasHome = is_dir($androidHome);
$androidChecks[] = ['Android SDK 目录', '存在', $hasHome ? $androidHome : '不存在', $hasHome];

$sdkMgr = $androidHome . '/cmdline-tools/latest/bin/sdkmanager';
$hasSdk = file_exists($sdkMgr);
$androidChecks[] = ['SDK Manager', '已安装', $hasSdk ? '已安装' : '未安装', $hasSdk];

$btDir = $androidHome . '/build-tools/34.0.0';
$hasBt = is_dir($btDir);
$androidChecks[] = ['Build Tools 34.0.0', '已安装', $hasBt ? '已安装' : '未安装', $hasBt];

$pfDir = $androidHome . '/platforms/android-34';
$hasPf = is_dir($pfDir);
$androidChecks[] = ['Platform android-34', '已安装', $hasPf ? '已安装' : '未安装', $hasPf];

$ktOut = [];
@exec('which keytool 2>/dev/null', $ktOut, $ktCode);
$hasKt = (($ktCode ?? 1) === 0);
$androidChecks[] = ['keytool', '可用', $hasKt ? '可用' : '不可用', $hasKt];

$androidAllOk = $hasJava && $hasHome && $hasSdk && $hasBt && $hasPf && $hasKt;
$androidInstallStatus = get_setting($pdo, 'android_install_status', 'idle');

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
    <h3>Android 构建环境</h3>
    <p style="color:#999;font-size:0.85em;margin-top:4px;">如不需要 URL 转 APK 功能可不安装，总文件大小约 1GB，需要运行内存大于 2GB</p>
    <table class="table" style="margin-top:12px;">
        <thead><tr><th>检测项</th><th>要求</th><th>当前</th><th>状态</th></tr></thead>
        <tbody>
        <?php foreach ($androidChecks as $c): ?>
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
    <?php if ($androidAllOk): ?>
    <div style="margin-top:12px;padding:12px;background:#f0fdf4;border-radius:8px;color:#27ae60;">
        <i class="fas fa-check-circle"></i> Android 构建环境已就绪
    </div>
    <?php else: ?>
    <div id="android-install-area" style="margin-top:16px;padding:16px;background:#f8f9fa;border-radius:8px;">
        <button id="btn-install-android" class="btn btn-primary" onclick="installAndroidEnv()">
            <i class="fas fa-download"></i> 一键安装 Android 环境
        </button>
        <div id="android-install-progress" style="display:none;margin-top:12px;">
            <div style="font-weight:600;margin-bottom:8px;">安装日志：</div>
            <pre id="android-install-log" style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;max-height:400px;overflow-y:auto;font-size:0.8em;white-space:pre-wrap;line-height:1.6;"></pre>
            <div id="android-install-status" style="margin-top:8px;"></div>
        </div>
    </div>
    <?php endif; ?>
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

<?php if (!$androidAllOk): ?>
<script>
let _installPollTimer = null;

async function installAndroidEnv() {
    if (!confirm('确定要安装 Android 构建环境吗？\n\n安装过程需要下载约 1GB 文件，可能需要几分钟时间。')) return;

    const btn = document.getElementById('btn-install-android');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 安装中...';
    document.getElementById('android-install-progress').style.display = 'block';

    try {
        await API.post('/admin/api/system.php?action=install_android', {});
        startPolling();
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download"></i> 一键安装 Android 环境';
    }
}

function startPolling() {
    if (_installPollTimer) clearInterval(_installPollTimer);
    pollInstallLog();
    _installPollTimer = setInterval(pollInstallLog, 2000);
}

async function pollInstallLog() {
    try {
        const resp = await fetch('/admin/api/system.php?action=install_log', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        const logEl = document.getElementById('android-install-log');
        if (logEl) {
            logEl.textContent = data.log || '等待输出...';
            logEl.scrollTop = logEl.scrollHeight;
        }

        const statusEl = document.getElementById('android-install-status');
        if (data.status === 'done') {
            clearInterval(_installPollTimer);
            if (statusEl) statusEl.innerHTML = '<span style="color:#27ae60;font-weight:600;"><i class="fas fa-check-circle"></i> 安装完成！请刷新页面查看检测结果</span>';
            const btn = document.getElementById('btn-install-android');
            if (btn) btn.style.display = 'none';
        } else if (data.status === 'failed') {
            clearInterval(_installPollTimer);
            if (statusEl) statusEl.innerHTML = '<span style="color:#e74c3c;font-weight:600;"><i class="fas fa-times-circle"></i> 安装失败，请查看上方日志</span>';
            const btn = document.getElementById('btn-install-android');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> 重试安装';
            }
        }
    } catch (e) { /* 网络错误忽略，下次轮询重试 */ }
}

// 页面加载时检查是否有正在运行的安装
(function() {
    const status = <?= json_encode($androidInstallStatus) ?>;
    if (status === 'running') {
        const btn = document.getElementById('btn-install-android');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 安装中...';
        }
        document.getElementById('android-install-progress').style.display = 'block';
        startPolling();
    }
})();
</script>
<?php endif; ?>

<?php admin_footer(); ?>
