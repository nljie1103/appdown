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
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group" style="margin-bottom:0;">
                <label><span style="color:#e74c3c;">*</span> 输出格式</label>
                <select class="form-control" id="imgFormat">
                    <option value="webp" selected>WebP（推荐）</option>
                    <option value="png">PNG</option>
                    <option value="jpg">JPG</option>
                    <option value="gif">GIF</option>
                    <option value="original">保持原格式</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label><span style="color:#e74c3c;">*</span> 压缩质量 <small id="imgQualityHint" style="color:var(--text-secondary);">(推荐: 80)</small></label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="range" id="imgQualityRange" min="1" max="100" value="80" style="flex:1;">
                    <input type="number" class="form-control" id="imgQuality" min="1" max="100" value="80" style="width:60px;text-align:center;padding:6px;">
                </div>
            </div>
        </div>
        <div id="imgConvertNote" style="margin:8px 0 4px;padding:8px 12px;background:var(--bg);border-radius:6px;font-size:0.8em;color:var(--text-secondary);display:none;">
            <i class="fas fa-info-circle" style="color:var(--primary);"></i> <span id="imgConvertNoteText"></span>
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

<!-- 安装包详细信息弹窗 -->
<div class="modal-overlay" id="pkgInfoModal">
    <div class="modal" style="max-width:600px;max-height:85vh;display:flex;flex-direction:column;">
        <h3><i class="fas fa-info-circle" style="color:var(--primary);"></i> 安装包详细信息</h3>
        <div id="pkgInfoBody" style="overflow-y:auto;flex:1;"></div>
        <div class="modal-actions" style="margin-top:12px;">
            <button class="btn btn-outline" onclick="Modal.hide('pkgInfoModal')">关闭</button>
        </div>
    </div>
</div>

<!-- 编辑附件版本弹窗 -->
<div class="modal-overlay" id="editFileModal">
    <div class="modal" style="max-width:420px;">
        <h3>编辑版本信息</h3>
        <input type="hidden" id="editFileId">
        <div class="form-group"><label><span style="color:#e74c3c;">*</span> 版本号</label><input type="text" class="form-control" id="editFileVersion"></div>
        <div class="form-group"><label>更新日志 <small style="color:var(--text-secondary);">(可选)</small></label><textarea class="form-control" id="editFileChangelog" rows="3"></textarea></div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('editFileModal')">取消</button>
            <button class="btn btn-primary" onclick="saveFileEdit()">保存</button>
        </div>
    </div>
</div>

<!-- 编辑图片信息弹窗 -->
<div class="modal-overlay" id="editImgModal">
    <div class="modal" style="max-width:420px;">
        <h3>编辑图片信息</h3>
        <input type="hidden" id="editImgId">
        <div class="form-group"><label>重命名 <small style="color:var(--text-secondary);">(修改后文件链接地址同步变更)</small></label><input type="text" class="form-control" id="editImgFilename"></div>
        <div class="form-group"><label>备注 <small style="color:var(--text-secondary);">(可选)</small></label><input type="text" class="form-control" id="editImgRemark"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('editImgModal')">取消</button>
            <button class="btn btn-primary" onclick="saveImgEdit()">保存</button>
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
                <button class="btn btn-outline btn-sm" onclick="editFile(${f.id},'${escapeHTML(f.version).replace(/'/g,"\\'")}','${escapeHTML(f.changelog||'').replace(/'/g,"\\'").replace(/\n/g,"\\n")}')" title="编辑"><i class="fas fa-edit"></i></button>
                ${/\.(apk|ipa)$/i.test(f.file_url) ? `<button class="btn btn-outline btn-sm" onclick="showPackageInfo('${escapeHTML(f.file_url)}')" title="详细信息"><i class="fas fa-info-circle"></i></button>` : ''}
                <button class="btn btn-outline btn-sm copy-btn" onclick="copyLink(this)" data-url="${escapeHTML(f.file_url)}" title="复制链接"><i class="fas fa-copy"></i></button>
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

function copyLink(btn) {
    const url = btn.dataset.url;
    const full = location.origin + '/' + url;
    const ta = document.createElement('textarea');
    ta.value = full;
    ta.style.cssText = 'position:fixed;left:-9999px;';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> 已复制';
    btn.style.color = '#27ae60';
    btn.style.borderColor = '#27ae60';
    setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; btn.style.borderColor = ''; }, 1500);
}

// ===== 图片预览灯箱 =====
function previewImg(src) {
    let overlay = document.getElementById('imgLightbox');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'imgLightbox';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;cursor:zoom-out;opacity:0;transition:opacity .2s;';
        overlay.innerHTML = '<img style="max-width:90%;max-height:90%;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.4);object-fit:contain;transition:transform .2s;" id="imgLightboxImg">';
        overlay.addEventListener('click', () => {
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 200);
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && overlay.style.display === 'flex') {
                overlay.style.opacity = '0';
                setTimeout(() => overlay.style.display = 'none', 200);
            }
        });
        document.body.appendChild(overlay);
    }
    document.getElementById('imgLightboxImg').src = src;
    overlay.style.display = 'flex';
    requestAnimationFrame(() => overlay.style.opacity = '1');
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
                <button class="btn btn-outline btn-sm" onclick="editImgFile(${img.id},'${escapeHTML(img.filename||'').replace(/'/g,"\\'")}','${escapeHTML(img.remark||'').replace(/'/g,"\\'")}')" title="编辑"><i class="fas fa-edit"></i></button>
                <button class="btn btn-outline btn-sm" onclick="previewImg('/${escapeHTML(img.file_url)}')" title="预览"><i class="fas fa-eye"></i></button>
                <button class="btn btn-outline btn-sm copy-btn" onclick="copyLink(this)" data-url="${escapeHTML(img.file_url)}" title="复制链接"><i class="fas fa-copy"></i></button>
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
    const format = document.getElementById('imgFormat').value;
    const quality = parseInt(document.getElementById('imgQuality').value) || 80;

    const fd = new FormData();
    fd.append('file', file);
    fd.append('category_id', currentImgCatId);
    fd.append('rename', rename);
    fd.append('remark', remark);
    fd.append('format', format);
    fd.append('quality', quality);
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
            document.getElementById('imgFormat').value = 'webp';
            document.getElementById('imgQualityRange').value = 80;
            document.getElementById('imgQuality').value = 80;
            document.getElementById('imgQualityHint').textContent = '(推荐: 80)';
            document.getElementById('imgConvertNote').style.display = 'none';
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

// ===== 编辑附件版本 =====
function editFile(id, version, changelog) {
    document.getElementById('editFileId').value = id;
    document.getElementById('editFileVersion').value = version;
    document.getElementById('editFileChangelog').value = changelog;
    Modal.show('editFileModal');
}

async function saveFileEdit() {
    const id = parseInt(document.getElementById('editFileId').value);
    const version = document.getElementById('editFileVersion').value.trim();
    const changelog = document.getElementById('editFileChangelog').value.trim();
    if (!version) { AlertModal.warning('请填写版本号', '版本号为必填项'); return; }
    try {
        await API.put('/admin/api/attachment-files.php', { id, version, changelog });
        Toast.success('已保存');
        Modal.hide('editFileModal');
        await loadPlatforms();
        selectPlatform(currentPlatId);
    } catch(e) { /* error already toasted */ }
}

// ===== 编辑图片信息 =====
function editImgFile(id, filename, remark) {
    document.getElementById('editImgId').value = id;
    document.getElementById('editImgFilename').value = filename;
    document.getElementById('editImgRemark').value = remark;
    Modal.show('editImgModal');
}

async function saveImgEdit() {
    const id = parseInt(document.getElementById('editImgId').value);
    const filename = document.getElementById('editImgFilename').value.trim();
    const remark = document.getElementById('editImgRemark').value.trim();
    try {
        await API.put('/admin/api/image-library.php?action=images', { id, filename, remark });
        Toast.success('已保存');
        Modal.hide('editImgModal');
        await loadImgCategories();
        await loadImgFiles();
    } catch(e) { /* error already toasted */ }
}

// ===== 安装包详细信息 =====
async function showPackageInfo(fileUrl) {
    const modal = document.getElementById('pkgInfoModal');
    const body = document.getElementById('pkgInfoBody');
    body.innerHTML = '<div style="text-align:center;padding:32px;"><i class="fas fa-spinner fa-spin" style="font-size:1.5em;color:var(--primary);"></i><p style="margin-top:12px;color:var(--text-secondary);font-size:0.9em;">正在解析安装包，请稍候...</p></div>';
    Modal.show('pkgInfoModal');

    try {
        const res = await API.get('/admin/api/package-info.php?file=' + encodeURIComponent(fileUrl));
        if (!res.ok) {
            body.innerHTML = `<div style="text-align:center;padding:24px;color:#e74c3c;"><i class="fas fa-exclamation-circle" style="font-size:1.5em;"></i><p style="margin-top:8px;">${escapeHTML(res.error || '解析失败')}</p></div>`;
            return;
        }
        renderPackageInfo(res.info);
    } catch (e) {
        body.innerHTML = `<div style="text-align:center;padding:24px;color:#e74c3c;"><i class="fas fa-exclamation-circle" style="font-size:1.5em;"></i><p style="margin-top:8px;">请求失败: ${escapeHTML(e.message || '未知错误')}</p></div>`;
    }
}

function renderPackageInfo(info) {
    const body = document.getElementById('pkgInfoBody');
    let html = '';

    const isAndroid = info.platform === 'Android';
    const icon = isAndroid ? 'fa-android' : 'fa-apple';
    const color = isAndroid ? '#3DDC84' : '#007AFF';

    html += `<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border);">
        <i class="fab ${icon}" style="font-size:1.8em;color:${color};"></i>
        <div>
            <div style="font-weight:700;font-size:1.05em;">${escapeHTML(isAndroid ? (info.package_name || '') : (info.display_name || info.bundle_name || ''))}</div>
            <div style="font-size:0.82em;color:var(--text-secondary);">${escapeHTML(info.file_name)} · ${escapeHTML(info.file_size)}</div>
        </div>
    </div>`;

    // 基本信息
    const basic = [];
    if (isAndroid) {
        if (info.package_name) basic.push(['包名', info.package_name]);
        if (info.version_name) basic.push(['版本', info.version_name]);
        if (info.version_code) basic.push(['版本代码', info.version_code]);
        if (info.min_sdk) basic.push(['最低 SDK', `API ${info.min_sdk}` + (info.min_android_version ? ` (${info.min_android_version})` : '')]);
        if (info.target_sdk) basic.push(['目标 SDK', `API ${info.target_sdk}` + (info.target_android_version ? ` (${info.target_android_version})` : '')]);
        if (info.compile_sdk) basic.push(['编译 SDK', `API ${info.compile_sdk}`]);
        if (info.main_activity) basic.push(['主 Activity', info.main_activity]);
        if (info.application_class) basic.push(['Application 类', info.application_class]);
        basic.push(['Multidex', info.multidex ? `是 (${info.dex_count} 个 DEX)` : '否']);
        if (info.native_architectures) basic.push(['原生架构', info.native_architectures.join(', ')]);
        basic.push(['使用 Kotlin', info.uses_kotlin ? '是' : '否']);
        basic.push(['包内文件数', info.total_files_in_package]);
    } else {
        if (info.bundle_id) basic.push(['Bundle ID', info.bundle_id]);
        if (info.display_name) basic.push(['显示名称', info.display_name]);
        if (info.bundle_name) basic.push(['Bundle 名称', info.bundle_name]);
        if (info.version) basic.push(['版本', info.version]);
        if (info.build_version) basic.push(['构建版本', info.build_version]);
        if (info.min_ios_version) basic.push(['最低 iOS 版本', info.min_ios_version]);
        if (info.bundle_executable) basic.push(['可执行文件', info.bundle_executable]);
        if (info.sdk_name) basic.push(['SDK', info.sdk_name]);
        if (info.platform_name) basic.push(['平台', info.platform_name + (info.platform_version ? ' ' + info.platform_version : '')]);
        if (info.xcode_version) basic.push(['Xcode 版本', info.xcode_version + (info.xcode_build ? ` (${info.xcode_build})` : '')]);
        if (info.compiler) basic.push(['编译器', info.compiler]);
        if (info.supported_devices) basic.push(['支持设备', info.supported_devices.join(', ')]);
        if (info.supported_orientations) basic.push(['支持方向', info.supported_orientations.join(', ')]);
        if (info.url_schemes) basic.push(['URL Schemes', info.url_schemes.join(', ')]);
        if (info.allows_arbitrary_loads !== undefined) basic.push(['允许 HTTP 加载', info.allows_arbitrary_loads ? '是' : '否']);
        if (info.embedded_frameworks) basic.push(['内嵌 Frameworks', `${info.frameworks_count} 个`]);
        basic.push(['包内文件数', info.total_files_in_package]);
    }

    html += renderInfoSection('基本信息', 'fa-cube', basic);

    // 签名信息
    if (isAndroid && info.signature) {
        const sig = info.signature;
        const sigRows = [];
        sigRows.push(['证书文件', sig.cert_file || '—']);
        sigRows.push(['证书版本', sig.cert_version || '—']);
        sigRows.push(['签名算法', sig.signature_algorithm || '—']);
        sigRows.push(['签发者', sig.issuer || '—']);
        sigRows.push(['使用者', sig.subject || '—']);
        sigRows.push(['序列号', sig.serial_number || '—']);
        sigRows.push(['生效日期', sig.valid_from || '—']);
        sigRows.push(['到期日期', sig.valid_to || '—']);
        const statusColor = sig.is_valid ? '#27ae60' : '#e74c3c';
        const statusIcon = sig.is_valid ? 'fa-check-circle' : 'fa-times-circle';
        let statusText = sig.validity_status || '—';
        if (sig.is_valid && sig.days_remaining) statusText += ` (剩余 ${sig.days_remaining} 天)`;
        sigRows.push(['有效性', `<span style="color:${statusColor};"><i class="fas ${statusIcon}"></i> ${statusText}</span>`]);
        sigRows.push(['V1 签名', sig.v1_signature ? '<span style="color:#27ae60;">✓</span>' : '<span style="color:#e74c3c;">✗</span>']);
        sigRows.push(['V2 签名', sig.v2_signature ? '<span style="color:#27ae60;">✓</span>' : '<span style="color:#e74c3c;">✗</span>']);
        if (sig.fingerprint_md5) sigRows.push(['指纹 MD5', `<code style="font-size:0.78em;word-break:break-all;">${escapeHTML(sig.fingerprint_md5)}</code>`]);
        if (sig.fingerprint_sha1) sigRows.push(['指纹 SHA-1', `<code style="font-size:0.78em;word-break:break-all;">${escapeHTML(sig.fingerprint_sha1)}</code>`]);
        if (sig.fingerprint_sha256) sigRows.push(['指纹 SHA-256', `<code style="font-size:0.78em;word-break:break-all;">${escapeHTML(sig.fingerprint_sha256)}</code>`]);
        html += renderInfoSection('签名信息', 'fa-shield-alt', sigRows);
    }

    // iOS 描述文件
    if (!isAndroid && info.provisioning) {
        const prov = info.provisioning;
        const provRows = [];
        provRows.push(['描述文件名称', prov.name || '—']);
        provRows.push(['签名类型', prov.provision_type || '—']);
        if (prov.team_name) provRows.push(['团队名称', prov.team_name]);
        if (prov.team_id) provRows.push(['团队 ID', prov.team_id]);
        if (prov.app_id_name) provRows.push(['App ID 名称', prov.app_id_name]);
        if (prov.creation_date) provRows.push(['创建日期', prov.creation_date]);
        if (prov.expiration_date) provRows.push(['过期日期', prov.expiration_date]);
        if (prov.expiry_status) {
            const ec = prov.is_expired ? '#e74c3c' : '#27ae60';
            const ei = prov.is_expired ? 'fa-times-circle' : 'fa-check-circle';
            let et = prov.expiry_status;
            if (!prov.is_expired && prov.days_remaining) et += ` (剩余 ${prov.days_remaining} 天)`;
            provRows.push(['有效性', `<span style="color:${ec};"><i class="fas ${ei}"></i> ${et}</span>`]);
        }
        if (prov.provisioned_devices_count) provRows.push(['注册设备数', prov.provisioned_devices_count]);
        html += renderInfoSection('描述文件 (Provisioning)', 'fa-file-signature', provRows);

        // 开发者证书
        if (prov.certificates && prov.certificates.length) {
            prov.certificates.forEach((cert, idx) => {
                const certRows = [];
                certRows.push(['使用者', cert.subject || '—']);
                certRows.push(['签发者', cert.issuer || '—']);
                certRows.push(['序列号', cert.serial || '—']);
                certRows.push(['生效日期', cert.valid_from || '—']);
                certRows.push(['到期日期', cert.valid_to || '—']);
                if (cert.days_remaining && cert.is_valid) {
                    certRows.push(['剩余天数', cert.days_remaining + ' 天']);
                }

                // 有效性（综合时间 + OCSP 吊销状态）
                let validLabel = '有效';
                let validColor = '#27ae60';
                let validIcon = 'fa-check-circle';
                if (cert.is_revoked === true) {
                    validLabel = '已被吊销（掉签）';
                    validColor = '#e74c3c';
                    validIcon = 'fa-ban';
                } else if (!cert.is_valid) {
                    validLabel = '已过期';
                    validColor = '#e74c3c';
                    validIcon = 'fa-times-circle';
                }
                certRows.push(['有效性', `<span style="color:${validColor};font-weight:600;"><i class="fas ${validIcon}"></i> ${validLabel}</span>`]);

                // OCSP 实时状态
                if (cert.ocsp_status) {
                    let ocspColor = '#95a5a6';
                    let ocspIcon = 'fa-question-circle';
                    if (cert.ocsp_status === 'good') {
                        ocspColor = '#27ae60'; ocspIcon = 'fa-shield-alt';
                    } else if (cert.ocsp_status === 'revoked') {
                        ocspColor = '#e74c3c'; ocspIcon = 'fa-shield-alt';
                    } else if (cert.ocsp_status === 'error' || cert.ocsp_status === 'unknown') {
                        ocspColor = '#f39c12'; ocspIcon = 'fa-exclamation-triangle';
                    }
                    certRows.push(['OCSP 状态', `<span style="color:${ocspColor};"><i class="fas ${ocspIcon}"></i> ${cert.ocsp_detail || cert.ocsp_status}</span>`]);
                }
                if (cert.revocation_time) {
                    certRows.push(['吊销时间', `<span style="color:#e74c3c;">${cert.revocation_time}</span>`]);
                }
                if (cert.revocation_reason) {
                    certRows.push(['吊销原因', `<span style="color:#e74c3c;">${cert.revocation_reason}</span>`]);
                }
                if (cert.ocsp_produced_at) {
                    certRows.push(['OCSP 响应时间', cert.ocsp_produced_at]);
                }
                if (cert.ocsp_this_update) {
                    certRows.push(['状态确认时间', cert.ocsp_this_update]);
                }
                if (cert.ocsp_next_update) {
                    certRows.push(['下次更新时间', cert.ocsp_next_update]);
                }

                if (cert.fingerprint_sha1) certRows.push(['指纹 SHA-1', `<code style="font-size:0.78em;word-break:break-all;">${escapeHTML(cert.fingerprint_sha1)}</code>`]);
                html += renderInfoSection(`开发者证书 #${idx + 1}`, 'fa-certificate', certRows);
            });
        }

        // Entitlements
        if (prov.entitlements) {
            const entRows = [];
            for (const [k, v] of Object.entries(prov.entitlements)) {
                const label = k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                const val = typeof v === 'boolean' ? (v ? '是' : '否') : (Array.isArray(v) ? v.join(', ') : String(v));
                entRows.push([label, val]);
            }
            html += renderInfoSection('权限 (Entitlements)', 'fa-key', entRows);
        }

        // 注册设备列表
        if (prov.provisioned_devices && prov.provisioned_devices.length) {
            const devHtml = prov.provisioned_devices.map(d => `<code style="font-size:0.8em;">${escapeHTML(d)}</code>`).join('<br>');
            html += `<details style="margin-top:12px;"><summary style="cursor:pointer;font-size:0.88em;font-weight:600;color:var(--text-secondary);"><i class="fas fa-mobile-alt"></i> 注册设备 UDID (${prov.provisioned_devices.length})</summary><div style="margin-top:8px;padding:8px 12px;background:var(--bg);border-radius:6px;max-height:200px;overflow-y:auto;">${devHtml}</div></details>`;
        }
    }

    // 权限列表 (Android)
    if (isAndroid && info.permissions && info.permissions.length) {
        let permHtml = info.permissions.map(p => {
            const short = p.replace('android.permission.', '');
            return `<span style="display:inline-block;background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:2px 8px;margin:2px;font-size:0.78em;" title="${escapeHTML(p)}">${escapeHTML(short)}</span>`;
        }).join('');
        html += `<details style="margin-top:12px;"><summary style="cursor:pointer;font-size:0.88em;font-weight:600;color:var(--text-secondary);"><i class="fas fa-lock"></i> 权限列表 (${info.permissions_count})</summary><div style="margin-top:8px;padding:8px 12px;">${permHtml}</div></details>`;
    }

    // features (Android)
    if (isAndroid && info.features && info.features.length) {
        let featHtml = info.features.map(f => {
            const short = f.replace('android.hardware.', '').replace('android.software.', '');
            return `<span style="display:inline-block;background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:2px 8px;margin:2px;font-size:0.78em;" title="${escapeHTML(f)}">${escapeHTML(short)}</span>`;
        }).join('');
        html += `<details style="margin-top:12px;"><summary style="cursor:pointer;font-size:0.88em;font-weight:600;color:var(--text-secondary);"><i class="fas fa-microchip"></i> 硬件/软件特性 (${info.features.length})</summary><div style="margin-top:8px;padding:8px 12px;">${featHtml}</div></details>`;
    }

    // iOS 内嵌 Frameworks
    if (!isAndroid && info.embedded_frameworks && info.embedded_frameworks.length) {
        let fwHtml = info.embedded_frameworks.map(f => `<span style="display:inline-block;background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:2px 8px;margin:2px;font-size:0.78em;">${escapeHTML(f)}</span>`).join('');
        html += `<details style="margin-top:12px;"><summary style="cursor:pointer;font-size:0.88em;font-weight:600;color:var(--text-secondary);"><i class="fas fa-layer-group"></i> 内嵌 Frameworks (${info.frameworks_count})</summary><div style="margin-top:8px;padding:8px 12px;">${fwHtml}</div></details>`;
    }

    // 文件哈希
    const hashRows = [];
    if (info.file_md5) hashRows.push(['MD5', info.file_md5]);
    if (info.file_sha1) hashRows.push(['SHA-1', info.file_sha1]);
    if (info.file_sha256) hashRows.push(['SHA-256', info.file_sha256]);
    if (info.file_size_bytes) hashRows.push(['精确大小', info.file_size_bytes.toLocaleString() + ' 字节']);
    html += renderInfoSection('文件校验', 'fa-fingerprint', hashRows, true);

    body.innerHTML = html;
}

function renderInfoSection(title, icon, rows, monospace) {
    if (!rows.length) return '';
    let html = `<div style="margin-top:14px;">
        <div style="font-size:0.88em;font-weight:600;color:var(--text-secondary);margin-bottom:8px;"><i class="fas ${icon}" style="margin-right:4px;"></i> ${title}</div>
        <div style="background:var(--bg);border-radius:8px;overflow:hidden;">`;
    rows.forEach(([label, value], i) => {
        const bg = i % 2 === 0 ? '' : 'background:rgba(0,0,0,0.02);';
        const valStyle = monospace ? 'font-family:monospace;font-size:0.8em;word-break:break-all;' : '';
        html += `<div style="display:flex;padding:7px 12px;gap:12px;font-size:0.85em;${bg}">
            <span style="min-width:110px;color:var(--text-secondary);flex-shrink:0;">${label}</span>
            <span style="flex:1;word-break:break-word;${valStyle}">${value}</span>
        </div>`;
    });
    html += `</div></div>`;
    return html;
}

setupDropZone('uploadDropZone', 'uploadFile', 'uploadDropText');
setupDropZone('imgDropZone', 'imgFileInput2', 'imgDropText');

// ===== 图片格式/压缩联动 =====
(function() {
    const range = document.getElementById('imgQualityRange');
    const num = document.getElementById('imgQuality');
    const fmt = document.getElementById('imgFormat');
    const note = document.getElementById('imgConvertNote');
    const noteText = document.getElementById('imgConvertNoteText');
    const hint = document.getElementById('imgQualityHint');

    range.oninput = () => { num.value = range.value; };
    num.oninput = () => {
        let v = parseInt(num.value) || 1;
        if (v < 1) v = 1; if (v > 100) v = 100;
        range.value = v; num.value = v;
    };

    function updateNote() {
        const f = fmt.value;
        if (f === 'png') {
            note.style.display = '';
            noteText.textContent = 'PNG 为无损格式，压缩质量值影响压缩级别（值越低文件越小，不影响画质）';
        } else if (f === 'gif') {
            note.style.display = '';
            noteText.textContent = 'GIF 最多支持 256 色，转换后可能丢失色彩细节';
        } else {
            note.style.display = 'none';
        }
    }
    fmt.onchange = updateNote;

    // 选择文件后自动推荐压缩质量
    document.getElementById('imgFileInput2').addEventListener('change', function() {
        if (!this.files.length) return;
        const file = this.files[0];
        const sizeMB = file.size / 1048576;
        let recommended = 80;
        if (sizeMB > 5) recommended = 60;
        else if (sizeMB > 2) recommended = 70;
        else if (sizeMB < 0.1) recommended = 90;
        range.value = recommended;
        num.value = recommended;
        hint.textContent = `(推荐: ${recommended})`;
    });
})();

init();
</script>

<?php admin_footer(); ?>
