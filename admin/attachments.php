<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();
admin_header('附件管理', 'attachments');
?>

<h2>附件管理</h2>
<p style="color:var(--text-secondary);margin-bottom:16px;">管理各应用的安装包文件，按平台分类，支持多版本</p>

<!-- 顶部切换 -->
<div class="card" style="padding:12px 16px;">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;" id="appTabs">
        <button class="btn btn-outline btn-sm" id="imgLibBtn" onclick="showImageLib()" style="border-color:var(--primary);color:var(--primary);"><i class="fas fa-images"></i> 公共图片库</button>
        <span style="color:var(--border);padding:0 4px;">|</span>
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

<!-- 公共图片库区 -->
<div id="imageLibArea" style="display:none;">
    <div style="display:grid;grid-template-columns:220px 1fr;gap:16px;margin-top:16px;">
        <!-- 左侧：图片分类 -->
        <div class="card" style="padding:0;align-self:start;">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:0.95em;">图片分类</h3>
                <button class="btn btn-primary btn-sm" onclick="addImgCategory()"><i class="fas fa-plus"></i></button>
            </div>
            <div id="imgCatList" style="padding:4px;"></div>
        </div>

        <!-- 右侧：图片列表 -->
        <div class="card" style="padding:0;align-self:start;">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:0.95em;" id="imgListTitle">选择一个分类</h3>
                <button class="btn btn-primary btn-sm" id="imgUploadBtn" style="display:none;" onclick="Modal.show('imgUploadModal')"><i class="fas fa-upload"></i> 上传图片</button>
            </div>
            <div id="imgFileList" style="padding:16px;">
                <div class="empty-state"><i class="fas fa-images"></i><p>请先选择左侧图片分类</p></div>
            </div>
        </div>
    </div>
</div>

<!-- 上传弹窗 -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <h3>上传新版本</h3>
        <div class="form-group"><label><span style="color:#e74c3c;">*</span> 版本号</label><input type="text" class="form-control" id="uploadVersion" placeholder="如: v1.0.0"></div>
        <div class="form-group">
            <label>选择文件（支持拖拽）</label>
            <div id="uploadDropZone" style="border:2px dashed var(--border);border-radius:8px;padding:24px;text-align:center;cursor:pointer;transition:border-color 0.2s,background 0.2s;" onclick="document.getElementById('uploadFile').click()">
                <i class="fas fa-cloud-upload-alt" style="font-size:1.5em;color:var(--text-secondary);"></i>
                <p style="margin:8px 0 0;color:var(--text-secondary);font-size:0.9em;" id="uploadDropText">点击选择或拖拽文件到此处</p>
                <input type="file" id="uploadFile" accept=".apk,.ipa,.exe,.dmg,.zip" style="display:none;">
            </div>
        </div>
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

<!-- 图片上传弹窗 -->
<div class="modal-overlay" id="imgUploadModal">
    <div class="modal" style="max-width:420px;">
        <h3>上传图片</h3>
        <div class="form-group"><label>重命名 <small style="color:var(--text-secondary);">(可选，不含后缀)</small></label><input type="text" class="form-control" id="imgRename" placeholder="留空则使用原文件名"></div>
        <div class="form-group">
            <label>选择图片（支持拖拽）</label>
            <div id="imgDropZone" style="border:2px dashed var(--border);border-radius:8px;padding:24px;text-align:center;cursor:pointer;transition:border-color 0.2s,background 0.2s;" onclick="document.getElementById('imgFileInput2').click()">
                <i class="fas fa-cloud-upload-alt" style="font-size:1.5em;color:var(--text-secondary);"></i>
                <p style="margin:8px 0 0;color:var(--text-secondary);font-size:0.9em;" id="imgDropText">点击选择或拖拽图片到此处</p>
                <input type="file" id="imgFileInput2" accept="image/*" style="display:none;">
            </div>
        </div>
        <div class="form-group"><label>备注 <small style="color:var(--text-secondary);">(可选)</small></label><input type="text" class="form-control" id="imgRemark" placeholder="图片用途说明"></div>
        <div id="imgUploadProgress" style="display:none;margin:8px 0;">
            <div style="background:var(--border);border-radius:4px;overflow:hidden;height:6px;">
                <div id="imgUploadBar" style="width:0%;height:100%;background:var(--primary);transition:width 0.3s;"></div>
            </div>
            <p style="font-size:0.8em;color:var(--text-secondary);margin-top:4px;" id="imgUploadStatus">上传中...</p>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('imgUploadModal')">取消</button>
            <button class="btn btn-primary" id="imgUploadSubmit" onclick="doImgUpload()">上传</button>
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
.img-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.img-row:last-child { border: none; }
.img-row img { width: 32px; height: 32px; border-radius: 4px; object-fit: cover; border: 1px solid var(--border); flex-shrink: 0; }
.img-row .img-name { font-size: 0.9em; font-weight: 500; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.img-row .img-meta { font-size: 0.8em; color: var(--text-secondary); white-space: nowrap; }
.img-row .img-actions { display: flex; gap: 6px; flex-shrink: 0; }
.drop-active { border-color: var(--primary) !important; background: rgba(52,152,219,0.05) !important; }
.img-row .img-remark { font-size: 0.78em; color: var(--text-secondary); font-style: italic; white-space: nowrap; }
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
    document.getElementById('imageLibArea').style.display = 'none';
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
    const name = await PromptModal.open('添加平台分类', '', '分类名称', '如: Android, iOS, PC, TV');
    if (!name) return;
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
    const versionEl = document.getElementById('uploadVersion');
    const version = versionEl.value.trim();
    const file = document.getElementById('uploadFile').files[0];
    const changelog = document.getElementById('uploadChangelog').value.trim();

    // 版本号必填验证
    if (!version) {
        versionEl.style.borderColor = '#e74c3c';
        versionEl.focus();
        AlertModal.warning('请填写版本号', '版本号为必填项');
        return;
    }
    versionEl.style.borderColor = '';

    if (!file) { AlertModal.warning('请选择文件', '拖拽文件到虚线框或点击选择'); return; }

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
            AlertModal.success('上传成功', `版本 <b>${escapeHTML(version)}</b> 已成功上传`);
            Modal.hide('uploadModal');
            document.getElementById('uploadVersion').value = '';
            document.getElementById('uploadFile').value = '';
            document.getElementById('uploadChangelog').value = '';
            await loadPlatforms();
            selectPlatform(currentPlatId);
        } else {
            const detail = explainUploadError(res.error || '上传失败');
            AlertModal.error('上传失败', detail);
        }
    } catch (e) {
        // 超出PHP限制时request body为空会进入这里
        let detail = e.message || '未知错误';
        if (detail === '解析失败' || detail.includes('JSON')) {
            detail = '服务器返回了无法解析的响应，可能原因：<br>1. 文件超出PHP上传限制<br>2. 服务器内存不足<br><b>建议：</b>到「系统信息」页面查看当前最大上传限制，减小文件体积或修改 php.ini';
        } else {
            detail = explainUploadError(detail);
        }
        AlertModal.error('上传失败', detail);
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

// ===== 公共图片库 =====
let imgCategories = [];
let currentImgCatId = null;

function showImageLib() {
    // 高亮图片库按钮，取消应用Tab高亮
    document.querySelectorAll('#appTabs .btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline');
    });
    document.getElementById('imgLibBtn').classList.add('btn-primary');
    document.getElementById('imgLibBtn').classList.remove('btn-outline');
    document.getElementById('mainArea').style.display = 'none';
    document.getElementById('imageLibArea').style.display = '';
    currentAppId = null;
    loadImgCategories();
}

async function loadImgCategories() {
    imgCategories = await API.get('/admin/api/image-library.php?action=categories');
    renderImgCategories();
}

function renderImgCategories() {
    const el = document.getElementById('imgCatList');
    if (!imgCategories.length) {
        el.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-secondary);font-size:0.85em;">暂无分类，点击 + 添加</div>';
        return;
    }
    el.innerHTML = imgCategories.map(c => `
        <div class="plat-item ${c.id == currentImgCatId ? 'active' : ''}" onclick="selectImgCategory(${c.id})">
            <span>${escapeHTML(c.name)} <small style="opacity:0.6;">(${c.image_count})</small></span>
            <span style="display:flex;gap:2px;">
                <button class="plat-del" onclick="event.stopPropagation();renameImgCategory(${c.id},'${escapeHTML(c.name)}')" title="重命名">✎</button>
                <button class="plat-del" onclick="event.stopPropagation();deleteImgCategory(${c.id},'${escapeHTML(c.name)}')" title="删除">✕</button>
            </span>
        </div>
    `).join('');
}

async function selectImgCategory(catId) {
    currentImgCatId = catId;
    renderImgCategories();
    const cat = imgCategories.find(c => c.id == catId);
    document.getElementById('imgListTitle').textContent = (cat ? cat.name : '') + ' 图片列表';
    document.getElementById('imgUploadBtn').style.display = '';
    await loadImgFiles();
}

async function loadImgFiles() {
    const images = await API.get(`/admin/api/image-library.php?action=images&category_id=${currentImgCatId}`);
    const el = document.getElementById('imgFileList');
    if (!images.length) {
        el.innerHTML = '<div class="empty-state"><i class="fas fa-images"></i><p>暂无图片，点击上传</p></div>';
        return;
    }
    el.innerHTML = images.map(img => `
        <div class="img-row">
            <img src="/${escapeHTML(img.file_url)}" alt="" loading="lazy">
            <span class="img-name" title="${escapeHTML(img.filename)}">${escapeHTML(img.filename || img.file_url.split('/').pop())}</span>
            ${img.remark ? `<span class="img-remark" title="${escapeHTML(img.remark)}">${escapeHTML(img.remark)}</span>` : ''}
            <span class="img-meta">${img.width && img.height ? img.width + '×' + img.height : ''}</span>
            <span class="img-meta">${escapeHTML(img.file_size)}</span>
            <span class="img-actions">
                <button class="btn btn-outline btn-sm" onclick="copyImgLink(this.dataset.url)" data-url="${escapeHTML(img.file_url)}" title="复制链接"><i class="fas fa-copy"></i></button>
                <button class="btn btn-outline btn-sm" style="color:#e74c3c;border-color:#e74c3c;" onclick="deleteImgFile(${img.id})" title="删除"><i class="fas fa-trash"></i></button>
            </span>
        </div>
    `).join('');
}

async function addImgCategory() {
    const name = await PromptModal.open('添加图片分类', '', '分类名称', '如: 图标, 头像, 背景图');
    if (!name) return;
    await API.post('/admin/api/image-library.php?action=categories', { name: name.trim() });
    Toast.success('分类已添加');
    await loadImgCategories();
}

async function renameImgCategory(id, oldName) {
    const name = await PromptModal.open('重命名分类', oldName, '分类名称', '输入新的分类名称');
    if (!name || name === oldName) return;
    await API.put('/admin/api/image-library.php?action=categories', { id, name: name.trim() });
    Toast.success('已重命名');
    await loadImgCategories();
}

async function deleteImgCategory(id, name) {
    if (!confirmAction(`确定删除「${name}」？该分类下所有图片将被永久删除。`)) return;
    await API.del('/admin/api/image-library.php?action=categories', { id });
    if (currentImgCatId == id) {
        currentImgCatId = null;
        document.getElementById('imgListTitle').textContent = '选择一个分类';
        document.getElementById('imgUploadBtn').style.display = 'none';
        document.getElementById('imgFileList').innerHTML = '<div class="empty-state"><i class="fas fa-images"></i><p>请先选择左侧图片分类</p></div>';
    }
    Toast.success('已删除');
    await loadImgCategories();
}

async function doImgUpload() {
    const file = document.getElementById('imgFileInput2').files[0];
    if (!file || !currentImgCatId) {
        AlertModal.warning('请选择图片', '拖拽图片到虚线框或点击选择');
        return;
    }
    const rename = document.getElementById('imgRename').value.trim();
    const remark = document.getElementById('imgRemark').value.trim();

    const fd = new FormData();
    fd.append('file', file);
    fd.append('category_id', currentImgCatId);
    fd.append('rename', rename);
    fd.append('remark', remark);
    fd.append('_csrf', CSRF_TOKEN);

    document.getElementById('imgUploadProgress').style.display = '';
    document.getElementById('imgUploadSubmit').disabled = true;

    try {
        const res = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const pct = Math.round(e.loaded / e.total * 100);
                    document.getElementById('imgUploadBar').style.width = pct + '%';
                    document.getElementById('imgUploadStatus').textContent = `上传中... ${pct}%`;
                }
            };
            xhr.onload = function() {
                try { resolve(JSON.parse(xhr.responseText)); }
                catch(e) { reject(new Error('服务器返回了无法解析的响应')); }
            };
            xhr.onerror = function() { reject(new Error('网络错误')); };
            xhr.open('POST', '/admin/api/image-library.php?action=images');
            xhr.setRequestHeader('X-CSRF-Token', CSRF_TOKEN);
            xhr.send(fd);
        });

        if (res.ok) {
            AlertModal.success('上传成功', '图片已上传到图片库');
            Modal.hide('imgUploadModal');
            document.getElementById('imgRename').value = '';
            document.getElementById('imgRemark').value = '';
            document.getElementById('imgFileInput2').value = '';
            document.getElementById('imgDropText').textContent = '点击选择或拖拽图片到此处';
            await loadImgCategories();
            await loadImgFiles();
        } else {
            AlertModal.error('上传失败', res.error || '未知错误');
        }
    } catch(e) {
        AlertModal.error('上传失败', e.message);
    }
    document.getElementById('imgUploadProgress').style.display = 'none';
    document.getElementById('imgUploadBar').style.width = '0%';
    document.getElementById('imgUploadSubmit').disabled = false;
}

async function deleteImgFile(id) {
    if (!confirmAction('确定删除此图片？')) return;
    await API.del('/admin/api/image-library.php?action=images', { id });
    Toast.success('已删除');
    await loadImgCategories();
    await loadImgFiles();
}

function copyImgLink(url) {
    const full = location.origin + '/' + url;
    navigator.clipboard.writeText(full).then(() => Toast.success('链接已复制'));
}

// ===== 拖拽上传初始化 =====
function setupDropZone(zoneId, fileInputId, textId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(fileInputId);
    if (!zone || !input) return;

    ['dragenter','dragover'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); zone.classList.add('drop-active'); });
    });
    ['dragleave','drop'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); zone.classList.remove('drop-active'); });
    });
    zone.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        if (files.length) {
            input.files = files;
            document.getElementById(textId).textContent = files[0].name;
        }
    });
    input.addEventListener('change', function() {
        if (this.files.length) {
            document.getElementById(textId).textContent = this.files[0].name;
        }
    });
}

// 版本号输入框恢复边框色
document.getElementById('uploadVersion').addEventListener('input', function() {
    this.style.borderColor = '';
});

setupDropZone('uploadDropZone', 'uploadFile', 'uploadDropText');
setupDropZone('imgDropZone', 'imgFileInput2', 'imgDropText');

init();
</script>

<?php admin_footer(); ?>
