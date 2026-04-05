<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();
admin_header('附件管理', 'attachments');
?>

<h2>附件管理</h2>
<p style="color:var(--text-secondary);margin-bottom:16px;">管理各应用的安装包文件，按平台分类，支持多版本</p>

<!-- 应用切换 -->
<div class="card" style="padding:12px 16px;">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;" id="appTabs">
        <span style="color:var(--text-secondary);font-size:0.9em;">选择应用：</span>
    </div>
</div>

<!-- 主内容区 -->
<div id="mainArea" style="display:none;">
    <div style="display:grid;grid-template-columns:240px 1fr;gap:16px;margin-top:16px;">
        <!-- 左侧：平台分类 -->
        <div class="card" style="padding:0;align-self:start;">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:0.95em;">平台分类</h3>
                <button class="btn btn-primary btn-sm" onclick="addPlatform()"><i class="fas fa-plus"></i></button>
            </div>
            <div id="platformList" style="padding:4px;"></div>
        </div>

        <!-- 右侧：版本列表 -->
        <div class="card" style="padding:0;align-self:start;">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:0.95em;" id="fileTitle">选择一个平台</h3>
                <button class="btn btn-primary btn-sm" id="uploadBtn" style="display:none;" onclick="Modal.show('uploadModal')"><i class="fas fa-upload"></i> 上传新版本</button>
            </div>
            <div id="fileList" style="padding:16px;">
                <div class="empty-state"><i class="fas fa-folder-open"></i><p>请先选择左侧平台分类</p></div>
            </div>
        </div>
    </div>
</div>

<!-- 上传弹窗 -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <h3>上传新版本</h3>
        <div class="form-group"><label>版本号</label><input type="text" class="form-control" id="uploadVersion" placeholder="如: v1.0.0"></div>
        <div class="form-group"><label>选择文件</label><input type="file" class="form-control" id="uploadFile" accept=".apk,.ipa,.exe,.dmg,.zip"></div>
        <div class="form-group"><label>更新日志 <small style="color:var(--text-secondary);">(可选)</small></label><textarea class="form-control" id="uploadChangelog" rows="3" placeholder="本次更新内容..."></textarea></div>
        <div id="uploadProgress" style="display:none;margin:12px 0;">
            <div style="background:var(--border);border-radius:4px;overflow:hidden;height:6px;">
                <div id="uploadBar" style="width:0%;height:100%;background:var(--primary);transition:width 0.3s;"></div>
            </div>
            <p style="font-size:0.8em;color:var(--text-secondary);margin-top:4px;" id="uploadStatus">上传中...</p>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('uploadModal')">取消</button>
            <button class="btn btn-primary" id="uploadSubmit" onclick="doUpload()">上传</button>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    #mainArea > div { grid-template-columns: 1fr !important; }
}
.plat-item { padding: 10px 14px; cursor: pointer; border-radius: 8px; margin: 2px 4px; font-size: 0.9em; display: flex; justify-content: space-between; align-items: center; transition: background 0.15s; }
.plat-item:hover { background: var(--bg); }
.plat-item.active { background: var(--primary); color: #fff; }
.plat-item .plat-del { opacity: 0; cursor: pointer; background: none; border: none; color: inherit; font-size: 0.85em; padding: 2px 6px; border-radius: 4px; }
.plat-item:hover .plat-del { opacity: 0.6; }
.plat-item:hover .plat-del:hover { opacity: 1; }
.file-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border); gap: 12px; }
.file-row:last-child { border: none; }
.file-info { flex: 1; }
.file-info .ver { font-weight: 600; font-size: 0.95em; }
.file-info .meta { font-size: 0.8em; color: var(--text-secondary); margin-top: 2px; }
.file-info .log { font-size: 0.8em; color: var(--text-secondary); margin-top: 4px; }
.file-actions { display: flex; gap: 6px; flex-shrink: 0; }
</style>

<script>
let apps = [];
let currentAppId = null;
let platforms = [];
let currentPlatId = null;

async function init() {
    apps = await API.get('/admin/api/apps.php');
    const tabs = document.getElementById('appTabs');
    if (!apps.length) {
        tabs.innerHTML += '<span style="color:var(--text-secondary);">暂无应用，请先在应用管理中添加</span>';
        return;
    }
    apps.forEach(app => {
        const btn = document.createElement('button');
        btn.className = 'btn btn-outline btn-sm';
        btn.textContent = app.name;
        btn.dataset.id = app.id;
        btn.onclick = () => selectApp(app.id, btn);
        tabs.appendChild(btn);
    });
}

async function selectApp(appId, btn) {
    currentAppId = appId;
    currentPlatId = null;
    document.querySelectorAll('#appTabs .btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline');
    });
    btn.classList.add('btn-primary');
    btn.classList.remove('btn-outline');
    document.getElementById('mainArea').style.display = '';
    await loadPlatforms();
    document.getElementById('fileTitle').textContent = '选择一个平台';
    document.getElementById('uploadBtn').style.display = 'none';
    document.getElementById('fileList').innerHTML = '<div class="empty-state"><i class="fas fa-folder-open"></i><p>请先选择左侧平台分类</p></div>';
}

async function loadPlatforms() {
    platforms = await API.get(`/admin/api/attachments.php?app_id=${currentAppId}`);
    renderPlatforms();
}

function renderPlatforms() {
    const el = document.getElementById('platformList');
    if (!platforms.length) {
        el.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-secondary);font-size:0.85em;">暂无分类，点击 + 添加</div>';
        return;
    }
    el.innerHTML = platforms.map(p => `
        <div class="plat-item ${p.id == currentPlatId ? 'active' : ''}" onclick="selectPlatform(${p.id})">
            <span>${escapeHTML(p.name)} <small style="opacity:0.6;">(${(p.files||[]).length})</small></span>
            <button class="plat-del" onclick="event.stopPropagation();deletePlatform(${p.id},'${escapeHTML(p.name)}')" title="删除">✕</button>
        </div>
    `).join('');
}

function selectPlatform(platId) {
    currentPlatId = platId;
    renderPlatforms();
    const plat = platforms.find(p => p.id == platId);
    if (!plat) return;
    document.getElementById('fileTitle').textContent = plat.name + ' 版本列表';
    document.getElementById('uploadBtn').style.display = '';
    renderFiles(plat.files || []);
}

function renderFiles(files) {
    const el = document.getElementById('fileList');
    if (!files.length) {
        el.innerHTML = '<div class="empty-state"><i class="fas fa-box-open"></i><p>暂无版本，点击上传</p></div>';
        return;
    }
    el.innerHTML = files.map((f, i) => `
        <div class="file-row">
            <div class="file-info">
                <div class="ver">${escapeHTML(f.version)} ${i === 0 ? '<span style="background:var(--primary);color:#fff;font-size:0.7em;padding:2px 6px;border-radius:4px;margin-left:6px;">最新</span>' : ''}</div>
                <div class="meta">${escapeHTML(f.file_size)} · ${f.created_at}</div>
                ${f.changelog ? `<div class="log">${escapeHTML(f.changelog)}</div>` : ''}
            </div>
            <div class="file-actions">
                <button class="btn btn-outline btn-sm" onclick="copyLink('${escapeHTML(f.file_url)}')" title="复制链接"><i class="fas fa-copy"></i></button>
                <button class="btn btn-outline btn-sm" style="color:#e74c3c;border-color:#e74c3c;" onclick="deleteFile(${f.id})" title="删除"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `).join('');
}

async function addPlatform() {
    if (!currentAppId) { Toast.error('请先选择一个应用'); return; }
    const name = prompt('平台名称（如: Android, iOS, PC, TV）');
    if (!name || !name.trim()) return;
    try {
        await API.post('/admin/api/attachments.php', { app_id: currentAppId, name: name.trim() });
        Toast.success('分类已添加');
        await loadPlatforms();
    } catch(e) { /* error already toasted by API */ }
}

async function deletePlatform(id, name) {
    if (!await confirmAction(`确定删除「${name}」及其所有附件？`)) return;
    await API.del('/admin/api/attachments.php', { id });
    currentPlatId = null;
    await loadPlatforms();
    document.getElementById('fileTitle').textContent = '选择一个平台';
    document.getElementById('uploadBtn').style.display = 'none';
    document.getElementById('fileList').innerHTML = '<div class="empty-state"><i class="fas fa-folder-open"></i><p>请先选择左侧平台分类</p></div>';
    Toast.success('已删除');
}

async function doUpload() {
    const version = document.getElementById('uploadVersion').value.trim();
    const file = document.getElementById('uploadFile').files[0];
    const changelog = document.getElementById('uploadChangelog').value.trim();
    if (!version) { Toast.error('请填写版本号'); return; }
    if (!file) { Toast.error('请选择文件'); return; }

    const fd = new FormData();
    fd.append('app_id', currentAppId);
    fd.append('platform_id', currentPlatId);
    fd.append('version', version);
    fd.append('changelog', changelog);
    fd.append('file', file);
    fd.append('category', 'app');

    document.getElementById('uploadProgress').style.display = '';
    document.getElementById('uploadSubmit').disabled = true;

    try {
        // 使用 XMLHttpRequest 显示进度
        const res = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const pct = Math.round(e.loaded / e.total * 100);
                    document.getElementById('uploadBar').style.width = pct + '%';
                    document.getElementById('uploadStatus').textContent = `上传中... ${pct}%`;
                }
            };
            xhr.onload = function() {
                try { resolve(JSON.parse(xhr.responseText)); }
                catch(e) { reject(new Error('解析失败')); }
            };
            xhr.onerror = function() { reject(new Error('网络错误')); };
            xhr.open('POST', '/admin/api/attachment-files.php');
            xhr.setRequestHeader('X-CSRF-Token', CSRF_TOKEN);
            xhr.send(fd);
        });

        if (res.ok) {
            Toast.success('上传成功');
            Modal.hide('uploadModal');
            document.getElementById('uploadVersion').value = '';
            document.getElementById('uploadFile').value = '';
            document.getElementById('uploadChangelog').value = '';
            await loadPlatforms();
            selectPlatform(currentPlatId);
        } else {
            Toast.error(res.error || '上传失败');
        }
    } catch (e) {
        Toast.error(e.message);
    }
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadBar').style.width = '0%';
    document.getElementById('uploadSubmit').disabled = false;
}

async function deleteFile(id) {
    if (!await confirmAction('确定删除此版本？文件将被永久删除')) return;
    await API.del('/admin/api/attachment-files.php', { id });
    await loadPlatforms();
    selectPlatform(currentPlatId);
    Toast.success('已删除');
}

function copyLink(url) {
    const full = location.origin + '/' + url;
    navigator.clipboard.writeText(full).then(() => Toast.success('链接已复制'));
}

init();
</script>

<?php admin_footer(); ?>
