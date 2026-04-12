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

// Android 构建环境检测（使用共享检测函数兜底非标准路径）
$androidChecks = [];
$androidHome = detect_android_home() ?: '/opt/android-sdk';

$javaOut = [];
@exec('java -version 2>&1', $javaOut);
$javaVerLine = $javaOut[0] ?? '';
$hasJava = (bool)preg_match('/version\s+"?17/', $javaVerLine);
$javaVer = '';
if (preg_match('/version\s+"([^"]+)"/', $javaVerLine, $m)) $javaVer = $m[1];
$androidChecks[] = ['Java 17 (JDK)', '已安装', $hasJava ? $javaVer : '未安装', $hasJava];

@exec('test -d ' . escapeshellarg($androidHome) . ' && echo 1', $homeOut);
$hasHome = (trim($homeOut[0] ?? '') === '1');
$androidChecks[] = ['Android SDK 目录', '存在', $hasHome ? $androidHome : '不存在', $hasHome];

$sdkMgr = $androidHome . '/cmdline-tools/latest/bin/sdkmanager';
@exec('test -f ' . escapeshellarg($sdkMgr) . ' && echo 1', $sdkOut);
$hasSdk = (trim($sdkOut[0] ?? '') === '1');
$androidChecks[] = ['SDK Manager', '已安装', $hasSdk ? '已安装' : '未安装', $hasSdk];

$btDir = $androidHome . '/build-tools/34.0.0';
@exec('test -d ' . escapeshellarg($btDir) . ' && echo 1', $btOut);
$hasBt = (trim($btOut[0] ?? '') === '1');
$androidChecks[] = ['Build Tools 34.0.0', '已安装', $hasBt ? '已安装' : '未安装', $hasBt];

$pfDir = $androidHome . '/platforms/android-34';
@exec('test -d ' . escapeshellarg($pfDir) . ' && echo 1', $pfOut);
$hasPf = (trim($pfOut[0] ?? '') === '1');
$androidChecks[] = ['Platform android-34', '已安装', $hasPf ? '已安装' : '未安装', $hasPf];

$ktOut = [];
@exec('which keytool 2>/dev/null', $ktOut, $ktCode);
$hasKt = (($ktCode ?? 1) === 0);
$androidChecks[] = ['keytool', '可用', $hasKt ? '可用' : '不可用', $hasKt];

$androidAllOk = $hasJava && $hasHome && $hasSdk && $hasBt && $hasPf && $hasKt;
$androidInstallStatus = get_setting($pdo, 'android_install_status', 'idle');

// iOS 构建环境检测
$iosChecks = [];

$dockerOut = [];
@exec('docker --version 2>/dev/null', $dockerOut);
$iosHasDocker = !empty($dockerOut[0]) && str_contains($dockerOut[0], 'Docker');
$dockerVer = $iosHasDocker ? trim($dockerOut[0]) : '未安装';
$iosChecks[] = ['Docker', '已安装', $iosHasDocker ? $dockerVer : '未安装', $iosHasDocker];

$iosDockerRunning = false;
if ($iosHasDocker) {
    @exec('docker info > /dev/null 2>&1', $drOut, $drCode);
    $iosDockerRunning = (($drCode ?? 1) === 0);
}
$iosChecks[] = ['Docker 运行中', '是', $iosDockerRunning ? '是' : '否', $iosDockerRunning];

$kvmOut = [];
@exec('test -e /dev/kvm && echo 1', $kvmOut);
$iosHasKvm = (trim($kvmOut[0] ?? '') === '1');
$iosChecks[] = ['KVM 虚拟化', '可用', $iosHasKvm ? '可用' : '不可用', $iosHasKvm];

$iosContainerExists = false;
$iosContainerRunning = false;
if ($iosDockerRunning) {
    $ceOut = [];
    @exec('docker ps -a --format "{{.Names}}" 2>/dev/null', $ceOut);
    $iosContainerExists = in_array('ysapp-ios-builder', $ceOut);
    $crOut = [];
    @exec('docker ps --format "{{.Names}}" 2>/dev/null', $crOut);
    $iosContainerRunning = in_array('ysapp-ios-builder', $crOut);
}
$iosChecks[] = ['macOS 容器', '已创建', $iosContainerExists ? '已创建' : '未创建', $iosContainerExists];
$iosChecks[] = ['容器运行中', '是', $iosContainerRunning ? '是' : '否', $iosContainerRunning];

$iosSshOk = false;
if ($iosContainerRunning) {
    $sshOut = [];
    @exec('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -o BatchMode=yes -p 50922 user@localhost "echo ok" 2>/dev/null', $sshOut);
    $iosSshOk = (trim($sshOut[0] ?? '') === 'ok');
}
$iosChecks[] = ['SSH 连接', '可达', $iosSshOk ? '可达' : '不可达', $iosSshOk];

$iosHasXcode = false;
$xcodeVer = '';
if ($iosSshOk) {
    $xcOut = [];
    @exec('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -o BatchMode=yes -p 50922 user@localhost "xcodebuild -version 2>/dev/null | head -1" 2>/dev/null', $xcOut);
    $xcLine = trim($xcOut[0] ?? '');
    if (str_contains($xcLine, 'Xcode')) {
        $iosHasXcode = true;
        $xcodeVer = $xcLine;
    }
}
$iosChecks[] = ['Xcode', '已安装', $iosHasXcode ? $xcodeVer : '未安装', $iosHasXcode];

$iosAllOk = $iosHasDocker && $iosDockerRunning && $iosHasKvm && $iosContainerExists && $iosContainerRunning && $iosSshOk && $iosHasXcode;
$iosInstallStatus = get_setting($pdo, 'ios_install_status', 'idle');

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
    <div style="margin-top:12px;padding:12px;background:#f0fdf4;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
        <span style="color:#27ae60;"><i class="fas fa-check-circle"></i> Android 构建环境已就绪</span>
        <button class="btn btn-outline btn-sm" onclick="uninstallAndroidEnv()" style="color:#e74c3c;border-color:#e74c3c;">
            <i class="fas fa-trash-alt"></i> 一键卸载
        </button>
    </div>
    <div id="android-install-progress" style="display:none;margin-top:12px;">
        <div style="font-weight:600;margin-bottom:8px;">操作日志：</div>
        <pre id="android-install-log" style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;max-height:400px;overflow-y:auto;font-size:0.8em;white-space:pre-wrap;line-height:1.6;"></pre>
        <div id="android-install-status" style="margin-top:8px;"></div>
    </div>
    <?php else: ?>
    <div id="android-install-area" style="margin-top:16px;padding:16px;background:#f8f9fa;border-radius:8px;">
        <div style="display:flex;gap:8px;align-items:center;">
            <button id="btn-install-android" class="btn btn-primary" onclick="installAndroidEnv()">
                <i class="fas fa-download"></i> 一键安装 Android 环境
            </button>
            <button id="btn-uninstall-android" class="btn btn-outline btn-sm" onclick="uninstallAndroidEnv()" style="color:#e74c3c;border-color:#e74c3c;">
                <i class="fas fa-trash-alt"></i> 一键卸载
            </button>
        </div>
        <div id="android-install-progress" style="display:none;margin-top:12px;">
            <div style="font-weight:600;margin-bottom:8px;">安装日志：</div>
            <pre id="android-install-log" style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;max-height:400px;overflow-y:auto;font-size:0.8em;white-space:pre-wrap;line-height:1.6;"></pre>
            <div id="android-install-status" style="margin-top:8px;"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>iOS 构建环境 (Docker-OSX)</h3>
    <p style="color:#999;font-size:0.85em;margin-top:4px;">通过 Docker 运行 macOS + Xcode 实现 URL 转 IPA。需要 Linux 宿主机 + KVM 虚拟化支持，镜像约 20GB</p>
    <table class="table" style="margin-top:12px;">
        <thead><tr><th>检测项</th><th>要求</th><th>当前</th><th>状态</th></tr></thead>
        <tbody>
        <?php foreach ($iosChecks as $c): ?>
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
    <?php if ($iosAllOk): ?>
    <div style="margin-top:12px;padding:12px;background:#f0fdf4;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
        <span style="color:#27ae60;"><i class="fas fa-check-circle"></i> iOS 构建环境已就绪</span>
        <button class="btn btn-outline btn-sm" onclick="uninstallIosEnv()" style="color:#e74c3c;border-color:#e74c3c;">
            <i class="fas fa-trash-alt"></i> 一键卸载
        </button>
    </div>
    <div id="ios-install-progress" style="display:none;margin-top:12px;">
        <div style="font-weight:600;margin-bottom:8px;">操作日志：</div>
        <pre id="ios-install-log" style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;max-height:400px;overflow-y:auto;font-size:0.8em;white-space:pre-wrap;line-height:1.6;"></pre>
        <div id="ios-install-status" style="margin-top:8px;"></div>
    </div>
    <?php else: ?>
    <div id="ios-install-area" style="margin-top:16px;padding:16px;background:#f8f9fa;border-radius:8px;">
        <div style="margin-bottom:12px;font-size:0.9em;color:#666;">
            <strong>安装分 3 个阶段：</strong>
            <ol style="margin:8px 0 0 16px;line-height:1.8;">
                <li><strong>Phase 1</strong>（自动）: 安装 Docker + 拉取 macOS 镜像 + 创建容器</li>
                <li><strong>Phase 2</strong>（手动）: 在终端中 SSH 进入容器安装 Xcode（需要 Apple ID）</li>
                <li><strong>Phase 3</strong>（自动）: 点击按钮验证 Xcode 是否安装成功</li>
            </ol>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button id="btn-install-ios" class="btn btn-primary" onclick="installIosEnv()">
                <i class="fas fa-download"></i> Phase 1: 安装 Docker + macOS 容器
            </button>
            <button class="btn btn-outline" onclick="verifyIosXcode()">
                <i class="fas fa-check"></i> Phase 3: 验证 Xcode
            </button>
            <button id="btn-uninstall-ios" class="btn btn-outline btn-sm" onclick="uninstallIosEnv()" style="color:#e74c3c;border-color:#e74c3c;">
                <i class="fas fa-trash-alt"></i> 一键卸载
            </button>
        </div>
        <div style="margin-top:12px;padding:10px;background:#fff8e1;border-radius:6px;font-size:0.85em;color:#856404;">
            <i class="fas fa-terminal"></i> <strong>Phase 2 命令</strong>（在服务器终端执行）：
            <code style="display:block;margin-top:4px;padding:8px;background:#fefce8;border-radius:4px;user-select:all;">sudo bash tools/setup-ios-xcode.sh</code>
        </div>
        <div id="ios-install-progress" style="display:none;margin-top:12px;">
            <div style="font-weight:600;margin-bottom:8px;">安装日志：</div>
            <pre id="ios-install-log" style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;max-height:400px;overflow-y:auto;font-size:0.8em;white-space:pre-wrap;line-height:1.6;"></pre>
            <div id="ios-install-status" style="margin-top:8px;"></div>
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

<?php /* Android 环境安装/卸载 JS */ ?>
<script>
let _installPollTimer = null;

async function uninstallAndroidEnv() {
    if (!await AlertModal.confirm('确定要卸载 Android 构建环境吗？', '将删除 Android SDK 和 OpenJDK 17，此操作不可逆。', { icon: 'danger', okText: '确定卸载', okClass: 'btn-danger' })) return;

    // 显示日志区域
    const progress = document.getElementById('android-install-progress');
    if (progress) progress.style.display = 'block';

    try {
        await API.post('/admin/api/system.php?action=uninstall_android', {});
        startPolling();
    } catch (e) {}
}

async function installAndroidEnv() {
    if (!await AlertModal.confirm('确定要安装 Android 构建环境吗？', '安装过程需要下载约 1GB 文件，可能需要几分钟时间。')) return;

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
            headers: { 'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' }
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
            if (statusEl) statusEl.innerHTML = '<span style="color:#27ae60;font-weight:600;"><i class="fas fa-check-circle"></i> 操作完成！请刷新页面查看结果</span>';
            const btn = document.getElementById('btn-install-android');
            if (btn) btn.style.display = 'none';
        } else if (data.status === 'failed') {
            clearInterval(_installPollTimer);
            if (statusEl) statusEl.innerHTML = '<span style="color:#e74c3c;font-weight:600;"><i class="fas fa-times-circle"></i> 操作失败，请查看上方日志</span>';
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

// ========== iOS 环境安装/卸载 JS ==========
let _iosInstallPollTimer = null;

async function installIosEnv() {
    if (!await AlertModal.confirm('确定要安装 iOS 构建环境吗？', '将安装 Docker + 拉取 macOS 镜像（约 20GB），整个过程可能需要较长时间。')) return;

    const btn = document.getElementById('btn-install-ios');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 安装中...';
    document.getElementById('ios-install-progress').style.display = 'block';

    try {
        await API.post('/admin/api/system.php?action=install_ios', {});
        startIosPolling();
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download"></i> Phase 1: 安装 Docker + macOS 容器';
    }
}

async function uninstallIosEnv() {
    if (!await AlertModal.confirm('确定要卸载 iOS 构建环境吗？', '将删除 macOS 容器、Docker-OSX 镜像和构建目录，此操作不可逆。', { icon: 'danger', okText: '确定卸载', okClass: 'btn-danger' })) return;

    const progress = document.getElementById('ios-install-progress');
    if (progress) progress.style.display = 'block';

    try {
        await API.post('/admin/api/system.php?action=uninstall_ios', {});
        startIosPolling();
    } catch (e) {}
}

async function verifyIosXcode() {
    try {
        const res = await API.post('/admin/api/system.php?action=verify_ios_xcode', {});
        if (res.ok) {
            await AlertModal.success('Xcode 验证成功', res.info || 'Xcode 已就绪，刷新页面查看最新状态。');
            location.reload();
        } else {
            AlertModal.error('Xcode 未就绪', res.info || '请先在终端执行 Phase 2 安装 Xcode。');
        }
    } catch (e) {
        AlertModal.error('验证失败', '无法连接到 macOS 容器，请确认容器正在运行。');
    }
}

function startIosPolling() {
    if (_iosInstallPollTimer) clearInterval(_iosInstallPollTimer);
    pollIosInstallLog();
    _iosInstallPollTimer = setInterval(pollIosInstallLog, 2000);
}

async function pollIosInstallLog() {
    try {
        const resp = await fetch('/admin/api/system.php?action=ios_install_log', {
            headers: { 'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        const logEl = document.getElementById('ios-install-log');
        if (logEl) {
            logEl.textContent = data.log || '等待输出...';
            logEl.scrollTop = logEl.scrollHeight;
        }

        const statusEl = document.getElementById('ios-install-status');
        if (data.status === 'done') {
            clearInterval(_iosInstallPollTimer);
            if (statusEl) statusEl.innerHTML = '<span style="color:#27ae60;font-weight:600;"><i class="fas fa-check-circle"></i> 操作完成！请刷新页面查看结果</span>';
            const btn = document.getElementById('btn-install-ios');
            if (btn) btn.style.display = 'none';
        } else if (data.status === 'failed') {
            clearInterval(_iosInstallPollTimer);
            if (statusEl) statusEl.innerHTML = '<span style="color:#e74c3c;font-weight:600;"><i class="fas fa-times-circle"></i> 操作失败，请查看上方日志</span>';
            const btn = document.getElementById('btn-install-ios');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> 重试安装';
            }
        }
    } catch (e) { /* 网络错误忽略 */ }
}

// iOS 页面加载时检查正在运行的安装
(function() {
    const iosStatus = <?= json_encode($iosInstallStatus) ?>;
    if (iosStatus === 'running') {
        const btn = document.getElementById('btn-install-ios');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 安装中...';
        }
        const progress = document.getElementById('ios-install-progress');
        if (progress) progress.style.display = 'block';
        startIosPolling();
    }
})();
</script>

<?php admin_footer(); ?>
