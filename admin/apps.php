<?php
/**
 * 应用管理列表页
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('应用管理', 'apps');
?>

<div class="page-header">
    <h1>应用管理</h1>
    <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> 添加应用</button>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>应用</th>
                    <th>标识</th>
                    <th>主题色</th>
                    <th>下载按钮</th>
                    <th>轮播图</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="appList">
                <tr><td colspan="8" style="text-align:center;color:var(--text-secondary);">加载中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 添加应用模态框 -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3>添加应用</h3>
        <div class="form-group">
            <label>应用标识 (slug)</label>
            <input type="text" class="form-control" id="addSlug" placeholder="如: myapp (小写字母和数字)">
        </div>
        <div class="form-group">
            <label>应用名称</label>
            <input type="text" class="form-control" id="addName" placeholder="如: 我的影视">
        </div>
        <div class="form-group">
            <label>图标</label>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px;">
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="addIconType" value="fa" checked onchange="toggleAddIconMode()"> FA图标</label>
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="addIconType" value="image" onchange="toggleAddIconMode()"> 自定义图片</label>
            </div>
            <div id="addIconFaMode">
                <div style="display:flex;gap:8px;align-items:center;">
                    <i id="addIconPreviewFa" class="fas fa-tv" style="font-size:1.4em;width:32px;text-align:center;color:#666;"></i>
                    <input type="text" class="form-control" id="addIcon" value="fas fa-tv" placeholder="fas fa-tv" oninput="document.getElementById('addIconPreviewFa').className=this.value||'fas fa-tv'" style="flex:1;">
                    <button class="btn btn-outline" type="button" onclick="IconPicker.open(cls => { document.getElementById('addIcon').value=cls; document.getElementById('addIconPreviewFa').className=cls; })"><i class="fas fa-icons"></i> 选择</button>
                </div>
            </div>
            <div id="addIconImgMode" style="display:none;">
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="addIconPreviewImg" src="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;border:1px solid #ddd;display:none;">
                    <button class="btn btn-outline" type="button" onclick="document.getElementById('addIconUpload').click()"><i class="fas fa-upload"></i> 上传图标</button>
                    <button class="btn btn-outline" type="button" onclick="ImagePicker.open(url => { document.getElementById('addIconUrl').value = url; document.getElementById('addIconPreviewImg').src = '/' + url; document.getElementById('addIconPreviewImg').style.display = ''; })"><i class="fas fa-images"></i> 图片库</button>
                    <input type="file" id="addIconUpload" accept="image/*" style="display:none;" onchange="uploadAddIcon(this)">
                    <input type="hidden" id="addIconUrl">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>主题色</label>
            <input type="color" class="form-control" id="addColor" value="#007AFF" style="height:42px;">
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('addModal')">取消</button>
            <button class="btn btn-primary" onclick="addApp()">添加</button>
        </div>
    </div>
</div>

<script>
// 预定义一组好看的颜色池
const COLOR_POOL = [
    '#007AFF','#FF3B30','#FF9500','#FFCC00','#34C759','#5AC8FA','#AF52DE',
    '#FF2D55','#5856D6','#00C7BE','#30B0C7','#A2845E','#FF6482','#32ADE6',
    '#7DC832','#F44336','#E91E63','#9C27B0','#3F51B5','#009688','#FF5722',
    '#795548','#607D8B','#4CAF50','#CDDC39','#03A9F4','#673AB7','#8BC34A'
];

function getRandomColor() {
    // 收集已有应用的颜色
    const usedColors = new Set();
    document.querySelectorAll('.color-swatch').forEach(el => {
        usedColors.add(el.style.background.toUpperCase ? el.closest('td')?.textContent.trim().match(/#[0-9A-Fa-f]{6}/)?.[0]?.toUpperCase() : '');
    });
    // 从池中过滤掉已用的
    const available = COLOR_POOL.filter(c => !usedColors.has(c.toUpperCase()));
    if (available.length > 0) return available[Math.floor(Math.random() * available.length)];
    // 池用完了就随机生成一个鲜艳颜色
    const hue = Math.floor(Math.random() * 360);
    return `hsl(${hue}, 70%, 50%)`;
}

function hslToHex(hslStr) {
    const el = document.createElement('div');
    el.style.color = hslStr;
    document.body.appendChild(el);
    const rgb = getComputedStyle(el).color;
    el.remove();
    const m = rgb.match(/\d+/g);
    if (!m) return '#007AFF';
    return '#' + m.slice(0,3).map(x => (+x).toString(16).padStart(2,'0')).join('');
}

function openAddModal() {
    // 重置表单
    document.getElementById('addSlug').value = '';
    document.getElementById('addName').value = '';
    document.getElementById('addIcon').value = 'fas fa-tv';
    document.getElementById('addIconPreviewFa').className = 'fas fa-tv';
    document.getElementById('addIconUrl').value = '';
    document.getElementById('addIconPreviewImg').style.display = 'none';
    document.querySelector('input[name="addIconType"][value="fa"]').checked = true;
    toggleAddIconMode();
    // 随机主题色
    let color = getRandomColor();
    if (color.startsWith('hsl')) color = hslToHex(color);
    document.getElementById('addColor').value = color;
    Modal.show('addModal');
}

function toggleAddIconMode() {
    const mode = document.querySelector('input[name="addIconType"]:checked').value;
    document.getElementById('addIconFaMode').style.display = mode === 'fa' ? '' : 'none';
    document.getElementById('addIconImgMode').style.display = mode === 'image' ? '' : 'none';
}

async function uploadAddIcon(input) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('file', input.files[0]);
    fd.append('category', 'image');
    const res = await API.upload('/admin/api/upload.php', fd);
    if (res.ok) {
        document.getElementById('addIconUrl').value = res.url;
        document.getElementById('addIconPreviewImg').src = '/' + res.url;
        document.getElementById('addIconPreviewImg').style.display = '';
        Toast.success('图标已上传');
    }
}

async function loadApps() {
    const apps = await API.get('/admin/api/apps.php');
    const body = document.getElementById('appList');

    if (apps.length === 0) {
        body.innerHTML = '<tr><td colspan="8"><div class="empty-state"><i class="fas fa-mobile-alt"></i><p>暂无应用，点击右上角添加</p></div></td></tr>';
        return;
    }

    body.innerHTML = apps.map(app => {
        const iconHtml = app.icon_url
            ? `<img src="/${escapeHTML(app.icon_url)}" style="width:20px;height:20px;border-radius:4px;object-fit:cover;vertical-align:middle;margin-right:6px;">`
            : `<i class="${escapeHTML(app.icon)}" style="color:${escapeHTML(app.theme_color)};margin-right:6px;"></i>`;
        return `
        <tr data-id="${app.id}" draggable="true">
            <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
            <td>${iconHtml} <strong>${escapeHTML(app.name)}</strong></td>
            <td style="color:var(--text-secondary);">${escapeHTML(app.slug)}</td>
            <td><span class="color-swatch" style="background:${escapeHTML(app.theme_color)};"></span> ${escapeHTML(app.theme_color)}</td>
            <td>${app.dl_count} 个</td>
            <td>${app.img_count} 张</td>
            <td>
                <label class="toggle">
                    <input type="checkbox" ${app.is_active ? 'checked' : ''} onchange="toggleApp(${app.id}, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </td>
            <td>
                <a href="/admin/app-edit.php?id=${app.id}" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> 编辑</a>
                <button class="btn btn-danger btn-sm" onclick="deleteApp(${app.id}, '${escapeHTML(app.name)}')"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        `;
    }).join('');

    initSortable(body, async (ids) => {
        await API.post('/admin/api/reorder.php', { table: 'apps', order: ids });
        Toast.success('排序已保存');
    });
}

async function addApp() {
    const slug = document.getElementById('addSlug').value.trim();
    const name = document.getElementById('addName').value.trim();
    const icon = document.getElementById('addIcon').value.trim();
    const color = document.getElementById('addColor').value;
    const iconType = document.querySelector('input[name="addIconType"]:checked').value;
    const iconUrl = iconType === 'image' ? document.getElementById('addIconUrl').value.trim() : '';

    if (!slug || !name) { Toast.error('标识和名称不能为空'); return; }

    await API.post('/admin/api/apps.php', { slug, name, icon, icon_url: iconUrl, theme_color: color });
    AlertModal.success('添加成功', `应用「${escapeHTML(name)}」已创建`);
    Modal.hide('addModal');
    loadApps();
}

async function toggleApp(id, active) {
    await API.put('/admin/api/apps.php', { id, is_active: active });
    Toast.success(active ? '已启用' : '已禁用');
}

async function deleteApp(id, name) {
    if (!await confirmAction(`确定删除「${name}」？该应用下的所有下载按钮和轮播图也会被删除。`)) return;
    await API.del('/admin/api/apps.php', { id });
    Toast.success('已删除');
    loadApps();
}

loadApps();
</script>

<?php admin_footer(); ?>
