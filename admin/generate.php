<?php
/**
 * 生成应用 — APK管理 / 生成新APK / 签名密钥管理
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('生成应用', 'generate');
?>

<div class="page-header">
    <h1>生成应用</h1>
</div>

<!-- 选项卡 -->
<div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--border);">
    <button class="tab-btn active" onclick="switchTab('apks')" id="tabBtn_apks" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-box"></i> APK管理
    </button>
    <button class="tab-btn" onclick="switchTab('build')" id="tabBtn_build" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-hammer"></i> 生成新APK
    </button>
    <button class="tab-btn" onclick="switchTab('keys')" id="tabBtn_keys" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-key"></i> 签名密钥
    </button>
</div>

<!-- ===== Tab 1: APK管理 ===== -->
<div id="tab_apks">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;">已生成的APK</h3>
            <button class="btn btn-outline btn-sm" onclick="loadApks()"><i class="fas fa-sync-alt"></i> 刷新</button>
        </div>
        <div id="apkList" style="overflow-x:auto;">
            <p style="color:var(--text-secondary);text-align:center;padding:20px;">加载中...</p>
        </div>
    </div>
</div>

<!-- ===== Tab 2: 生成新APK ===== -->
<div id="tab_build" style="display:none;">
    <div class="card">
        <h3>基本信息</h3>
        <div class="form-row">
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 目标URL</label>
                <input type="url" class="form-control" id="buildUrl" placeholder="https://example.com">
            </div>
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 应用名称</label>
                <input type="text" class="form-control" id="buildAppName" placeholder="我的应用">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 包名</label>
                <input type="text" class="form-control" id="buildPackage" placeholder="com.example.myapp">
                <small style="color:var(--text-secondary);">格式: com.xxx.xxx，只能包含小写字母、数字和下划线</small>
            </div>
            <div class="form-group">
                <label>版本号</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" class="form-control" id="buildVersionName" value="1.0.0" placeholder="1.0.0" style="flex:2;">
                    <input type="number" class="form-control" id="buildVersionCode" value="1" min="1" placeholder="版本代码" style="flex:1;">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>图标与启动图</h3>
        <div class="form-row">
            <div class="form-group">
                <label>应用图标</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="buildIconPreview" src="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;border:1px solid var(--border);display:none;">
                    <button class="btn btn-outline" onclick="uploadBuildFile('icon')"><i class="fas fa-upload"></i> 上传图标</button>
                    <button class="btn btn-outline" onclick="ImagePicker.open(url => setBuildImage('icon', url))"><i class="fas fa-images"></i> 图片库</button>
                    <input type="file" id="buildIconFile" accept="image/*" style="display:none;" onchange="handleBuildUpload(this, 'icon')">
                    <input type="hidden" id="buildIconUrl">
                </div>
                <small style="color:var(--text-secondary);">推荐 512x512 PNG</small>
            </div>
            <div class="form-group">
                <label>启动图 (可选)</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="buildSplashPreview" src="" style="width:48px;height:80px;object-fit:cover;border:1px solid var(--border);border-radius:6px;display:none;">
                    <button class="btn btn-outline" onclick="uploadBuildFile('splash')"><i class="fas fa-upload"></i> 上传</button>
                    <button class="btn btn-outline" onclick="ImagePicker.open(url => setBuildImage('splash', url))"><i class="fas fa-images"></i> 图片库</button>
                    <input type="file" id="buildSplashFile" accept="image/*" style="display:none;" onchange="handleBuildUpload(this, 'splash')">
                    <input type="hidden" id="buildSplashUrl">
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>启动背景色</label>
                <input type="color" class="form-control" id="buildSplashColor" value="#FFFFFF" style="height:42px;">
            </div>
            <div class="form-group">
                <label>状态栏颜色</label>
                <input type="color" class="form-control" id="buildStatusBarColor" value="#000000" style="height:42px;">
            </div>
        </div>
    </div>

    <div class="card">
        <h3>签名密钥</h3>
        <div class="form-group">
            <label><span style="color:#e74c3c;">*</span> 选择签名密钥</label>
            <div style="display:flex;gap:8px;">
                <select class="form-control" id="buildKeystoreId" style="flex:1;">
                    <option value="">-- 请先创建或导入密钥 --</option>
                </select>
                <button class="btn btn-outline" onclick="switchTab('keys')" title="管理密钥"><i class="fas fa-cog"></i></button>
            </div>
        </div>
    </div>

    <div style="text-align:center;margin:20px 0;">
        <button class="btn btn-primary" id="buildStartBtn" onclick="startBuild()" style="padding:12px 48px;font-size:1.05em;">
            <i class="fas fa-hammer"></i> 开始生成APK
        </button>
    </div>

    <!-- 构建进度 -->
    <div class="card" id="buildProgressCard" style="display:none;">
        <h3>构建进度</h3>
        <div style="position:relative;background:var(--border);border-radius:8px;height:24px;overflow:hidden;margin-bottom:12px;">
            <div id="buildProgressBar" style="height:100%;background:var(--primary);transition:width 0.5s;width:0%;border-radius:8px;"></div>
            <span id="buildProgressPercent" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-size:0.8em;font-weight:600;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,0.3);">0%</span>
        </div>
        <p id="buildProgressMsg" style="text-align:center;color:var(--text-secondary);margin:0;">等待中...</p>
        <div id="buildResult" style="display:none;margin-top:16px;padding:16px;border-radius:8px;text-align:center;"></div>
    </div>
</div>

<!-- ===== Tab 3: 签名密钥管理 ===== -->
<div id="tab_keys" style="display:none;">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;">签名密钥列表</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="showGenerateKeyModal()"><i class="fas fa-plus"></i> 生成新密钥</button>
                <button class="btn btn-outline btn-sm" onclick="showUploadKeyModal()"><i class="fas fa-upload"></i> 导入密钥</button>
            </div>
        </div>
        <div id="keyList" style="overflow-x:auto;">
            <p style="color:var(--text-secondary);text-align:center;padding:20px;">加载中...</p>
        </div>
    </div>
</div>

<!-- 生成密钥弹窗 -->
<div class="modal-overlay" id="generateKeyModal">
    <div class="modal" style="max-width:520px;">
        <h3>生成新签名密钥</h3>
        <div class="form-row">
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥名称</label><input type="text" class="form-control" id="gkName" placeholder="如: 正式签名"></div>
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 别名 (alias)</label><input type="text" class="form-control" id="gkAlias" placeholder="如: mykey"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥库密码</label><input type="password" class="form-control" id="gkStorePwd" placeholder="至少6位"></div>
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥密码</label><input type="password" class="form-control" id="gkKeyPwd" placeholder="至少6位"></div>
        </div>
        <div class="form-group"><label>有效期 (年)</label><input type="number" class="form-control" id="gkValidity" value="25" min="1" max="100"></div>
        <details style="margin-bottom:12px;">
            <summary style="cursor:pointer;font-size:0.9em;color:var(--text-secondary);">DN 信息（可选）</summary>
            <div style="margin-top:8px;">
                <div class="form-row">
                    <div class="form-group"><label>通用名称 (CN)</label><input type="text" class="form-control" id="gkCN" placeholder="如: My Company"></div>
                    <div class="form-group"><label>组织 (O)</label><input type="text" class="form-control" id="gkOrg" placeholder="如: My Corp"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>部门 (OU)</label><input type="text" class="form-control" id="gkOU"></div>
                    <div class="form-group"><label>国家代码 (C)</label><input type="text" class="form-control" id="gkCountry" placeholder="如: CN" maxlength="2"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>省份 (ST)</label><input type="text" class="form-control" id="gkState"></div>
                    <div class="form-group"><label>城市 (L)</label><input type="text" class="form-control" id="gkCity"></div>
                </div>
            </div>
        </details>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('generateKeyModal')">取消</button>
            <button class="btn btn-primary" id="gkSubmitBtn" onclick="doGenerateKey()">生成</button>
        </div>
    </div>
</div>

<!-- 导入密钥弹窗 -->
<div class="modal-overlay" id="uploadKeyModal">
    <div class="modal" style="max-width:480px;">
        <h3>导入签名密钥</h3>
        <div class="form-group">
            <label><span style="color:#e74c3c;">*</span> Keystore文件</label>
            <div style="display:flex;gap:8px;">
                <input type="text" class="form-control" id="ukFileName" readonly placeholder="选择 .jks / .keystore / .p12 文件" style="flex:1;">
                <button class="btn btn-outline" onclick="document.getElementById('ukFileInput').click()"><i class="fas fa-folder-open"></i></button>
                <input type="file" id="ukFileInput" accept=".jks,.keystore,.p12,.pfx,.bks" style="display:none;" onchange="document.getElementById('ukFileName').value=this.files[0]?.name||''">
            </div>
        </div>
        <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥名称</label><input type="text" class="form-control" id="ukName" placeholder="如: 正式签名"></div>
        <div class="form-group"><label><span style="color:#e74c3c;">*</span> 别名 (alias)</label><input type="text" class="form-control" id="ukAlias"></div>
        <div class="form-row">
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥库密码</label><input type="password" class="form-control" id="ukStorePwd"></div>
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥密码</label><input type="password" class="form-control" id="ukKeyPwd"></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('uploadKeyModal')">取消</button>
            <button class="btn btn-primary" id="ukSubmitBtn" onclick="doUploadKey()">导入</button>
        </div>
    </div>
</div>

<!-- 关联应用弹窗 -->
<div class="modal-overlay" id="associateModal">
    <div class="modal" style="max-width:400px;">
        <h3>关联到应用</h3>
        <div class="form-group">
            <label>选择应用</label>
            <select class="form-control" id="assocAppId">
                <option value="">-- 不关联 --</option>
            </select>
        </div>
        <input type="hidden" id="assocApkId">
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('associateModal')">取消</button>
            <button class="btn btn-primary" onclick="doAssociate()">保存</button>
        </div>
    </div>
</div>

<script>
// ===== 选项卡切换 =====
let currentTab = 'apks';
function switchTab(tab) {
    ['apks', 'build', 'keys'].forEach(t => {
        document.getElementById('tab_' + t).style.display = t === tab ? '' : 'none';
        const btn = document.getElementById('tabBtn_' + t);
        btn.style.color = t === tab ? 'var(--primary)' : 'var(--text-secondary)';
        btn.style.borderBottomColor = t === tab ? 'var(--primary)' : 'transparent';
    });
    currentTab = tab;
    if (tab === 'apks') loadApks();
    if (tab === 'keys') { loadKeys(); loadKeystoreSelect(); }
    if (tab === 'build') loadKeystoreSelect();
}

// ===== APK列表 =====
let allApps = [];
async function loadApps() {
    try { allApps = await API.get('/admin/api/apps.php'); } catch(e) { allApps = []; }
}

async function loadApks() {
    const el = document.getElementById('apkList');
    try {
        const rows = await API.get('/admin/api/generate.php?action=list_apks');
        if (!rows.length) {
            el.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px;">暂无生成记录</p>';
            return;
        }
        let html = '<table class="data-table"><thead><tr>' +
            '<th>应用名</th><th>包名</th><th>版本</th><th>URL</th><th>大小</th><th>签名密钥</th><th>关联应用</th><th>创建时间</th><th>操作</th>' +
            '</tr></thead><tbody>';
        for (const r of rows) {
            html += `<tr>
                <td>${escapeHTML(r.app_name)}</td>
                <td><code style="font-size:0.8em;">${escapeHTML(r.package_name)}</code></td>
                <td>${escapeHTML(r.version_name)} (${r.version_code})</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHTML(r.url)}">${escapeHTML(r.url)}</td>
                <td>${escapeHTML(r.apk_size)}</td>
                <td>${escapeHTML(r.keystore_name || '-')}</td>
                <td>${r.linked_app_name ? escapeHTML(r.linked_app_name) : '<span style="color:var(--text-secondary);">未关联</span>'}</td>
                <td style="white-space:nowrap;">${r.created_at || ''}</td>
                <td style="white-space:nowrap;">
                    ${r.apk_url ? `<a href="/${escapeHTML(r.apk_url)}" class="btn btn-outline btn-sm" download><i class="fas fa-download"></i></a>` : ''}
                    <button class="btn btn-outline btn-sm" onclick="showAssociate(${r.id})" title="关联应用"><i class="fas fa-link"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="deleteApk(${r.id})" title="删除" style="color:#e74c3c;"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        }
        html += '</tbody></table>';
        el.innerHTML = html;
    } catch(e) {
        el.innerHTML = '<p style="color:#e74c3c;text-align:center;padding:20px;">加载失败</p>';
    }
}

async function deleteApk(id) {
    if (!confirmAction('确定要删除此APK及其文件？')) return;
    try {
        await API.del('/admin/api/generate.php', { id });
        Toast.success('已删除');
        loadApks();
    } catch(e) {}
}

async function showAssociate(apkId) {
    document.getElementById('assocApkId').value = apkId;
    if (!allApps.length) await loadApps();
    const sel = document.getElementById('assocAppId');
    sel.innerHTML = '<option value="">-- 不关联 --</option>';
    for (const a of allApps) {
        sel.innerHTML += `<option value="${a.id}">${escapeHTML(a.name)}</option>`;
    }
    Modal.show('associateModal');
}

async function doAssociate() {
    const apkId = parseInt(document.getElementById('assocApkId').value);
    const appId = document.getElementById('assocAppId').value;
    try {
        await API.put('/admin/api/generate.php', {
            action: 'associate',
            apk_id: apkId,
            app_id: appId ? parseInt(appId) : null,
        });
        Toast.success('关联已更新');
        Modal.hide('associateModal');
        loadApks();
    } catch(e) {}
}

// ===== 构建 =====
let buildPolling = null;

function uploadBuildFile(type) {
    document.getElementById(type === 'icon' ? 'buildIconFile' : 'buildSplashFile').click();
}

async function handleBuildUpload(input, type) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('file', input.files[0]);
    fd.append('category', 'image');
    try {
        const res = await API.upload('/admin/api/upload.php', fd);
        setBuildImage(type, res.url);
    } catch(e) {}
}

function setBuildImage(type, url) {
    if (type === 'icon') {
        document.getElementById('buildIconUrl').value = url;
        const prev = document.getElementById('buildIconPreview');
        prev.src = '/' + url;
        prev.style.display = '';
    } else {
        document.getElementById('buildSplashUrl').value = url;
        const prev = document.getElementById('buildSplashPreview');
        prev.src = '/' + url;
        prev.style.display = '';
    }
}

async function loadKeystoreSelect() {
    try {
        const rows = await API.get('/admin/api/keystores.php');
        const sel = document.getElementById('buildKeystoreId');
        const curVal = sel.value;
        sel.innerHTML = '<option value="">-- 请选择签名密钥 --</option>';
        for (const k of rows) {
            sel.innerHTML += `<option value="${k.id}">${escapeHTML(k.name)} (${escapeHTML(k.alias)})</option>`;
        }
        if (curVal) sel.value = curVal;
    } catch(e) {}
}

async function startBuild() {
    const url = document.getElementById('buildUrl').value.trim();
    const appName = document.getElementById('buildAppName').value.trim();
    const packageName = document.getElementById('buildPackage').value.trim();
    const versionName = document.getElementById('buildVersionName').value.trim() || '1.0.0';
    const versionCode = parseInt(document.getElementById('buildVersionCode').value) || 1;
    const keystoreId = parseInt(document.getElementById('buildKeystoreId').value) || 0;
    const iconUrl = document.getElementById('buildIconUrl').value;
    const splashUrl = document.getElementById('buildSplashUrl').value;
    const splashColor = document.getElementById('buildSplashColor').value;
    const statusBarColor = document.getElementById('buildStatusBarColor').value;

    if (!url) { Toast.error('请输入目标URL'); return; }
    if (!appName) { Toast.error('请输入应用名称'); return; }
    if (!packageName) { Toast.error('请输入包名'); return; }
    if (!/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){1,}$/.test(packageName)) {
        Toast.error('包名格式不正确，例: com.example.app');
        return;
    }
    if (!keystoreId) { Toast.error('请选择签名密钥'); return; }

    document.getElementById('buildStartBtn').disabled = true;
    document.getElementById('buildProgressCard').style.display = '';
    document.getElementById('buildResult').style.display = 'none';
    updateProgress(0, '提交构建任务...');

    try {
        const res = await API.post('/admin/api/generate.php', {
            action: 'build',
            url, app_name: appName, package_name: packageName,
            version_name: versionName, version_code: versionCode,
            keystore_id: keystoreId,
            icon_url: iconUrl, splash_url: splashUrl,
            splash_color: splashColor, status_bar_color: statusBarColor,
        });
        pollBuildStatus(res.task_id);
    } catch(e) {
        document.getElementById('buildStartBtn').disabled = false;
        updateProgress(0, '提交失败');
    }
}

function pollBuildStatus(taskId) {
    if (buildPolling) clearInterval(buildPolling);
    buildPolling = setInterval(async () => {
        try {
            const t = await API.get(`/admin/api/generate.php?action=task_status&id=${taskId}`);
            updateProgress(t.progress, t.progress_msg);

            if (t.status === 'done') {
                clearInterval(buildPolling);
                buildPolling = null;
                document.getElementById('buildStartBtn').disabled = false;
                const rd = document.getElementById('buildResult');
                rd.style.display = '';
                rd.style.background = '#ecfdf5';
                rd.innerHTML = `<div style="color:#10b981;font-size:1.2em;font-weight:600;margin-bottom:8px;"><i class="fas fa-check-circle"></i> 构建成功!</div>` +
                    `<p>文件大小: ${escapeHTML(t.result_size)}</p>` +
                    `<a href="/${escapeHTML(t.result_url)}" class="btn btn-primary" download><i class="fas fa-download"></i> 下载APK</a>`;
            }

            if (t.status === 'failed') {
                clearInterval(buildPolling);
                buildPolling = null;
                document.getElementById('buildStartBtn').disabled = false;
                const rd = document.getElementById('buildResult');
                rd.style.display = '';
                rd.style.background = '#fef2f2';
                rd.innerHTML = `<div style="color:#ef4444;font-size:1.2em;font-weight:600;margin-bottom:8px;"><i class="fas fa-times-circle"></i> 构建失败</div>` +
                    `<pre style="text-align:left;font-size:0.8em;max-height:200px;overflow:auto;background:#1a1a2e;color:#eee;padding:12px;border-radius:6px;margin-top:8px;">${escapeHTML(t.error_msg || '未知错误')}</pre>`;
            }
        } catch(e) {
            // 网络失败继续轮询
        }
    }, 2500);
}

function updateProgress(pct, msg) {
    document.getElementById('buildProgressBar').style.width = pct + '%';
    document.getElementById('buildProgressPercent').textContent = pct + '%';
    document.getElementById('buildProgressMsg').textContent = msg || '';
}

// ===== 签名密钥 =====
async function loadKeys() {
    const el = document.getElementById('keyList');
    try {
        const rows = await API.get('/admin/api/keystores.php');
        if (!rows.length) {
            el.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px;">暂无密钥，请生成或导入</p>';
            return;
        }
        let html = '<table class="data-table"><thead><tr>' +
            '<th>名称</th><th>别名</th><th>组织</th><th>有效期</th><th>创建时间</th><th>操作</th>' +
            '</tr></thead><tbody>';
        for (const k of rows) {
            const org = [k.common_name, k.org_name].filter(Boolean).join(' / ') || '-';
            html += `<tr>
                <td>${escapeHTML(k.name)}</td>
                <td><code>${escapeHTML(k.alias)}</code></td>
                <td>${escapeHTML(org)}</td>
                <td>${k.validity_years}年</td>
                <td style="white-space:nowrap;">${k.created_at || ''}</td>
                <td style="white-space:nowrap;">
                    <button class="btn btn-outline btn-sm" onclick="deleteKey(${k.id})" title="删除" style="color:#e74c3c;"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        }
        html += '</tbody></table>';
        el.innerHTML = html;
    } catch(e) {
        el.innerHTML = '<p style="color:#e74c3c;text-align:center;padding:20px;">加载失败</p>';
    }
}

async function deleteKey(id) {
    if (!confirmAction('确定要删除此签名密钥？')) return;
    try {
        await API.del('/admin/api/keystores.php', { id });
        Toast.success('已删除');
        loadKeys();
        loadKeystoreSelect();
    } catch(e) {}
}

function showGenerateKeyModal() {
    ['gkName','gkAlias','gkStorePwd','gkKeyPwd','gkCN','gkOrg','gkOU','gkCountry','gkState','gkCity'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('gkValidity').value = 25;
    Modal.show('generateKeyModal');
}

async function doGenerateKey() {
    const name = document.getElementById('gkName').value.trim();
    const alias = document.getElementById('gkAlias').value.trim();
    const storePwd = document.getElementById('gkStorePwd').value;
    const keyPwd = document.getElementById('gkKeyPwd').value;
    if (!name || !alias) { Toast.error('名称和别名为必填项'); return; }
    if (storePwd.length < 6 || keyPwd.length < 6) { Toast.error('密码至少6位'); return; }

    document.getElementById('gkSubmitBtn').disabled = true;
    try {
        await API.post('/admin/api/keystores.php?action=generate', {
            name, alias,
            store_password: storePwd,
            key_password: keyPwd,
            validity_years: parseInt(document.getElementById('gkValidity').value) || 25,
            common_name: document.getElementById('gkCN').value.trim(),
            org_name: document.getElementById('gkOrg').value.trim(),
            org_unit: document.getElementById('gkOU').value.trim(),
            country: document.getElementById('gkCountry').value.trim(),
            state_name: document.getElementById('gkState').value.trim(),
            locality: document.getElementById('gkCity').value.trim(),
        });
        Toast.success('密钥已生成');
        Modal.hide('generateKeyModal');
        loadKeys();
        loadKeystoreSelect();
    } catch(e) {}
    document.getElementById('gkSubmitBtn').disabled = false;
}

function showUploadKeyModal() {
    ['ukName','ukAlias','ukStorePwd','ukKeyPwd','ukFileName'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('ukFileInput').value = '';
    Modal.show('uploadKeyModal');
}

async function doUploadKey() {
    const fileInput = document.getElementById('ukFileInput');
    if (!fileInput.files[0]) { Toast.error('请选择keystore文件'); return; }
    const name = document.getElementById('ukName').value.trim();
    const alias = document.getElementById('ukAlias').value.trim();
    const storePwd = document.getElementById('ukStorePwd').value;
    const keyPwd = document.getElementById('ukKeyPwd').value;
    if (!name || !alias || !storePwd || !keyPwd) { Toast.error('所有字段为必填项'); return; }

    const fd = new FormData();
    fd.append('file', fileInput.files[0]);
    fd.append('action', 'upload');
    fd.append('name', name);
    fd.append('alias', alias);
    fd.append('store_password', storePwd);
    fd.append('key_password', keyPwd);

    document.getElementById('ukSubmitBtn').disabled = true;
    try {
        await API.upload('/admin/api/keystores.php', fd);
        Toast.success('密钥已导入');
        Modal.hide('uploadKeyModal');
        loadKeys();
        loadKeystoreSelect();
    } catch(e) {}
    document.getElementById('ukSubmitBtn').disabled = false;
}

// ===== 初始化 =====
loadApps();
loadApks();
loadKeystoreSelect();
</script>

<?php admin_footer(); ?>
