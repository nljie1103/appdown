<?php
/**
 * 单个应用编辑页 - 下载按钮 + 轮播图管理
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/apps.php'); exit; }

admin_header('编辑应用', 'apps');
?>

<div class="page-header">
    <h1 id="pageTitle">编辑应用</h1>
    <a href="/admin/apps.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> 返回列表</a>
</div>

<!-- 基本信息 -->
<div class="card" id="appInfo">
    <h3>基本信息</h3>
    <div class="form-row">
        <div class="form-group">
            <label>应用标识</label>
            <input type="text" class="form-control" id="appSlug" readonly style="background:#f5f5f5;">
        </div>
        <div class="form-group">
            <label>应用名称</label>
            <input type="text" class="form-control" id="appName">
        </div>
        <div class="form-group">
            <label>图标</label>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px;">
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="iconType" value="fa" checked onchange="toggleIconMode()"> FA图标</label>
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="iconType" value="image" onchange="toggleIconMode()"> 自定义图片</label>
            </div>
            <div id="iconFaMode">
                <input type="text" class="form-control" id="appIcon" placeholder="fas fa-tv">
            </div>
            <div id="iconImgMode" style="display:none;">
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="iconPreview" src="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;border:1px solid #ddd;display:none;">
                    <button class="btn btn-outline" onclick="document.getElementById('iconUpload').click()"><i class="fas fa-upload"></i> 上传图标</button>
                    <input type="file" id="iconUpload" accept="image/*" style="display:none;" onchange="uploadIcon(this)">
                    <input type="hidden" id="appIconUrl">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>主题色</label>
            <input type="color" class="form-control" id="appColor" style="height:42px;">
        </div>
    </div>
    <button class="btn btn-primary" onclick="saveApp()"><i class="fas fa-save"></i> 保存基本信息</button>
</div>

<!-- iOS安装页配置 -->
<div class="card">
    <h3>iOS安装页配置</h3>
    <p style="color:var(--text-secondary);margin-bottom:12px;font-size:0.9em;">配置后用户可通过 <code>/ios/?app=应用标识</code> 访问iOS安装引导页</p>
    <div class="form-row">
        <div class="form-group"><label>plist安装链接</label><input type="text" class="form-control" id="iosPlist" placeholder="itms-services://?action=download-manifest&url=https://..."></div>
        <div class="form-group"><label>证书名称</label><input type="text" class="form-control" id="iosCert" placeholder="如: Etisalat - Emirates..."></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>应用版本</label><input type="text" class="form-control" id="iosVersion" placeholder="如: 7.2.3"></div>
        <div class="form-group"><label>应用大小</label><input type="text" class="form-control" id="iosSize" placeholder="如: 4.5 MB"></div>
    </div>
    <div class="form-group"><label>应用简介</label><textarea class="form-control" id="iosDesc" rows="3" placeholder="iOS安装页展示的应用描述"></textarea></div>
    <div class="form-group">
        <label>安装页模板</label>
        <select class="form-control" id="iosTemplate">
            <option value="modern">现代风格（毛玻璃）</option>
            <option value="classic">经典风格（仿App Store）</option>
        </select>
    </div>
    <button class="btn btn-primary" onclick="saveApp()"><i class="fas fa-save"></i> 保存iOS配置</button>
</div>

<!-- 下载按钮 -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;">下载按钮</h3>
        <button class="btn btn-primary btn-sm" onclick="Modal.show('addDlModal')"><i class="fas fa-plus"></i> 添加</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th></th><th>类型</th><th>按钮文本</th><th>副标题</th><th>链接</th><th>操作</th></tr></thead>
            <tbody id="dlList"></tbody>
        </table>
    </div>
</div>

<!-- 轮播图 -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;">轮播图</h3>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-outline btn-sm" onclick="Modal.show('addImgUrlModal')"><i class="fas fa-link"></i> 添加URL</button>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('imgUpload').click()"><i class="fas fa-upload"></i> 上传图片</button>
            <input type="file" id="imgUpload" accept="image/*" multiple style="display:none;" onchange="uploadImages(this.files)">
        </div>
    </div>
    <div class="image-grid" id="imgGrid"></div>
</div>

<!-- 添加下载按钮模态框 -->
<div class="modal-overlay" id="addDlModal">
    <div class="modal">
        <h3>添加下载按钮</h3>
        <div class="form-group">
            <label>平台类型</label>
            <select class="form-control" id="dlType">
                <option value="android">Android</option>
                <option value="ios">iOS</option>
                <option value="windows">Windows</option>
                <option value="web">Web</option>
                <option value="tv">TV</option>
            </select>
        </div>
        <div class="form-group"><label>按钮文本</label><input type="text" class="form-control" id="dlText" placeholder="如: Android"></div>
        <div class="form-group"><label>副标题</label><input type="text" class="form-control" id="dlSubtext" placeholder="如: 点击下载"></div>
        <div class="form-group">
            <label>下载链接</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-control" id="dlHref" placeholder="如: android/app.apk 或 https://..." style="flex:1;">
                <button class="btn btn-outline btn-sm" type="button" onclick="showAttPicker('dlHref')" title="从附件选择"><i class="fas fa-paperclip"></i> 选择附件</button>
            </div>
            <select class="form-control att-picker" id="dlHrefPicker" style="display:none;margin-top:6px;" onchange="pickAttachment(this,'dlHref')">
                <option value="">-- 选择一个版本 --</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('addDlModal')">取消</button>
            <button class="btn btn-primary" onclick="addDownload()">添加</button>
        </div>
    </div>
</div>

<!-- 添加图片URL模态框 -->
<div class="modal-overlay" id="addImgUrlModal">
    <div class="modal">
        <h3>添加图片 (URL)</h3>
        <div class="form-group"><label>图片地址</label><input type="text" class="form-control" id="imgUrl" placeholder="如: img/app/1.webp 或 https://..."></div>
        <div class="form-group"><label>描述文本</label><input type="text" class="form-control" id="imgAlt" placeholder="如: 首页界面"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('addImgUrlModal')">取消</button>
            <button class="btn btn-primary" onclick="addImageUrl()">添加</button>
        </div>
    </div>
</div>

<!-- 编辑下载按钮模态框 -->
<div class="modal-overlay" id="editDlModal">
    <div class="modal">
        <h3>编辑下载按钮</h3>
        <input type="hidden" id="editDlId">
        <div class="form-group">
            <label>平台类型</label>
            <select class="form-control" id="editDlType">
                <option value="android">Android</option>
                <option value="ios">iOS</option>
                <option value="windows">Windows</option>
                <option value="web">Web</option>
                <option value="tv">TV</option>
            </select>
        </div>
        <div class="form-group"><label>按钮文本</label><input type="text" class="form-control" id="editDlText"></div>
        <div class="form-group"><label>副标题</label><input type="text" class="form-control" id="editDlSubtext"></div>
        <div class="form-group">
            <label>下载链接</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-control" id="editDlHref" style="flex:1;">
                <button class="btn btn-outline btn-sm" type="button" onclick="showAttPicker('editDlHref')" title="从附件选择"><i class="fas fa-paperclip"></i> 选择附件</button>
            </div>
            <select class="form-control att-picker" id="editDlHrefPicker" style="display:none;margin-top:6px;" onchange="pickAttachment(this,'editDlHref')">
                <option value="">-- 选择一个版本 --</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('editDlModal')">取消</button>
            <button class="btn btn-primary" onclick="saveEditDownload()">保存</button>
        </div>
    </div>
</div>

<script>
const APP_ID = <?= $id ?>;
let attachments = []; // 附件数据缓存

async function loadAttachments() {
    try {
        attachments = await API.get(`/admin/api/attachments.php?app_id=${APP_ID}`);
    } catch(e) { attachments = []; }
}

function buildAttOptions() {
    let html = '<option value="">-- 选择一个版本 --</option>';
    attachments.forEach(plat => {
        if (!plat.files || !plat.files.length) return;
        html += `<optgroup label="${escapeHTML(plat.name)}">`;
        plat.files.forEach(f => {
            html += `<option value="${escapeHTML(f.file_url)}">${escapeHTML(f.version)} (${escapeHTML(f.file_size)})</option>`;
        });
        html += '</optgroup>';
    });
    return html;
}

function showAttPicker(targetId) {
    const picker = document.getElementById(targetId + 'Picker');
    if (picker.style.display === 'none') {
        picker.innerHTML = buildAttOptions();
        picker.style.display = '';
        if (!attachments.length || !attachments.some(p => p.files && p.files.length)) {
            picker.innerHTML = '<option value="">暂无附件，请先在附件管理中上传</option>';
        }
    } else {
        picker.style.display = 'none';
    }
}

function pickAttachment(sel, targetId) {
    if (sel.value) {
        document.getElementById(targetId).value = sel.value;
    }
    sel.style.display = 'none';
}

async function loadApp() {
    const app = await API.get(`/admin/api/apps.php?id=${APP_ID}`);
    document.getElementById('pageTitle').textContent = `编辑: ${app.name}`;
    document.getElementById('appSlug').value = app.slug;
    document.getElementById('appName').value = app.name;
    document.getElementById('appIcon').value = app.icon;
    document.getElementById('appColor').value = app.theme_color;

    // 图标模式
    const iconUrl = app.icon_url || '';
    document.getElementById('appIconUrl').value = iconUrl;
    if (iconUrl) {
        document.querySelector('input[name="iconType"][value="image"]').checked = true;
        toggleIconMode();
        document.getElementById('iconPreview').src = '/' + iconUrl;
        document.getElementById('iconPreview').style.display = '';
    }

    document.getElementById('iosPlist').value = app.ios_plist_url || '';
    document.getElementById('iosCert').value = app.ios_cert_name || '';
    document.getElementById('iosVersion').value = app.ios_version || '';
    document.getElementById('iosSize').value = app.ios_size || '';
    document.getElementById('iosDesc').value = app.ios_description || '';
    document.getElementById('iosTemplate').value = app.ios_template || 'modern';

    renderDownloads(app.downloads);
    renderImages(app.images);
}

async function saveApp() {
    const iconType = document.querySelector('input[name="iconType"]:checked').value;
    await API.put('/admin/api/apps.php', {
        id: APP_ID,
        name: document.getElementById('appName').value.trim(),
        icon: document.getElementById('appIcon').value.trim(),
        icon_url: iconType === 'image' ? document.getElementById('appIconUrl').value.trim() : '',
        theme_color: document.getElementById('appColor').value,
        ios_plist_url: document.getElementById('iosPlist').value.trim(),
        ios_cert_name: document.getElementById('iosCert').value.trim(),
        ios_version: document.getElementById('iosVersion').value.trim(),
        ios_size: document.getElementById('iosSize').value.trim(),
        ios_description: document.getElementById('iosDesc').value.trim(),
        ios_template: document.getElementById('iosTemplate').value,
    });
    Toast.success('已保存');
}

function toggleIconMode() {
    const mode = document.querySelector('input[name="iconType"]:checked').value;
    document.getElementById('iconFaMode').style.display = mode === 'fa' ? '' : 'none';
    document.getElementById('iconImgMode').style.display = mode === 'image' ? '' : 'none';
}

async function uploadIcon(input) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('file', input.files[0]);
    fd.append('category', 'image');
    const res = await API.upload('/admin/api/upload.php', fd);
    if (res.ok) {
        document.getElementById('appIconUrl').value = res.url;
        document.getElementById('iconPreview').src = '/' + res.url;
        document.getElementById('iconPreview').style.display = '';
        Toast.success('图标已上传');
    }
}

// === 下载按钮 ===
function renderDownloads(list) {
    const body = document.getElementById('dlList');
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-secondary);">暂无下载按钮</td></tr>';
        return;
    }
    body.innerHTML = list.map(d => `
        <tr data-id="${d.id}" draggable="true">
            <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
            <td>${d.btn_type}</td>
            <td>${d.btn_text}</td>
            <td>${d.btn_subtext}</td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${d.href}</td>
            <td>
                <button class="btn btn-outline btn-sm" onclick='editDownload(${JSON.stringify(d)})'><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="deleteDownload(${d.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    initSortable(body, async (ids) => {
        await API.post('/admin/api/reorder.php', { table: 'app_downloads', order: ids });
        Toast.success('排序已保存');
    });
}

async function addDownload() {
    await API.post('/admin/api/downloads.php', {
        app_id: APP_ID,
        btn_type: document.getElementById('dlType').value,
        btn_text: document.getElementById('dlText').value.trim(),
        btn_subtext: document.getElementById('dlSubtext').value.trim(),
        href: document.getElementById('dlHref').value.trim() || '#',
    });
    Toast.success('添加成功');
    Modal.hide('addDlModal');
    loadApp();
}

function editDownload(d) {
    document.getElementById('editDlId').value = d.id;
    document.getElementById('editDlType').value = d.btn_type;
    document.getElementById('editDlText').value = d.btn_text;
    document.getElementById('editDlSubtext').value = d.btn_subtext;
    document.getElementById('editDlHref').value = d.href;
    document.getElementById('editDlHrefPicker').style.display = 'none';
    Modal.show('editDlModal');
}

async function saveEditDownload() {
    await API.put('/admin/api/downloads.php', {
        id: parseInt(document.getElementById('editDlId').value),
        btn_type: document.getElementById('editDlType').value,
        btn_text: document.getElementById('editDlText').value.trim(),
        btn_subtext: document.getElementById('editDlSubtext').value.trim(),
        href: document.getElementById('editDlHref').value.trim(),
        is_active: 1,
    });
    Toast.success('已更新');
    Modal.hide('editDlModal');
    loadApp();
}

async function deleteDownload(id) {
    if (!confirmAction('确定删除此下载按钮？')) return;
    await API.del('/admin/api/downloads.php', { id });
    Toast.success('已删除');
    loadApp();
}

// === 轮播图 ===
function renderImages(list) {
    const grid = document.getElementById('imgGrid');
    if (list.length === 0) {
        grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;"><i class="fas fa-images"></i><p>暂无轮播图</p></div>';
        return;
    }
    grid.innerHTML = list.map(img => `
        <div class="image-item" data-id="${img.id}">
            <img src="/${img.image_url}" alt="${img.alt_text}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjM1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PC9zdmc+'">
            <div class="actions">
                <button onclick="deleteImage(${img.id})" title="删除"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `).join('');
}

async function uploadImages(files) {
    for (const file of files) {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('category', 'image');
        fd.append('_csrf', CSRF_TOKEN);

        try {
            const res = await API.upload('/admin/api/upload.php', fd);
            await API.post('/admin/api/images.php', {
                app_id: APP_ID,
                image_url: res.url,
                alt_text: file.name.replace(/\.[^.]+$/, ''),
            });
        } catch (e) { /* error already toasted */ }
    }
    Toast.success('上传完成');
    loadApp();
}

async function addImageUrl() {
    const url = document.getElementById('imgUrl').value.trim();
    const alt = document.getElementById('imgAlt').value.trim();
    if (!url) { Toast.error('图片地址不能为空'); return; }

    await API.post('/admin/api/images.php', { app_id: APP_ID, image_url: url, alt_text: alt });
    Toast.success('添加成功');
    Modal.hide('addImgUrlModal');
    loadApp();
}

async function deleteImage(id) {
    if (!confirmAction('确定删除此图片？')) return;
    await API.del('/admin/api/images.php', { id });
    Toast.success('已删除');
    loadApp();
}

loadApp();
loadAttachments();
</script>

<?php admin_footer(); ?>
