<?php
/**
 * 友情链接管理页
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('友情链接', 'links');
?>

<div class="page-header">
    <h1>友情链接</h1>
    <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> 添加链接</button>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th></th><th>图标</th><th>名称</th><th>链接地址</th><th>显示图标</th><th>状态</th><th>操作</th></tr></thead>
            <tbody id="linkList"></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3 id="modalTitle">添加链接</h3>
        <input type="hidden" id="editId">
        <div class="form-group"><label>名称</label><input type="text" class="form-control" id="lName" placeholder="如: 合作网站"></div>
        <div class="form-group"><label>链接地址</label><input type="text" class="form-control" id="lUrl" placeholder="如: https://example.com"></div>
        <div class="form-group">
            <label>图标</label>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px;">
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="lIconType" value="fa" checked onchange="toggleIconMode()"> FA图标</label>
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="lIconType" value="image" onchange="toggleIconMode()"> 自定义图片</label>
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="lIconType" value="none" onchange="toggleIconMode()"> 无图标</label>
            </div>
            <div id="lIconFaMode">
                <div style="display:flex;gap:8px;align-items:center;">
                    <i id="lIconPreviewFa" class="fas fa-link" style="font-size:1.4em;width:32px;text-align:center;color:#666;"></i>
                    <input type="text" class="form-control" id="lIcon" placeholder="fas fa-link" oninput="document.getElementById('lIconPreviewFa').className=this.value||'fas fa-link'" style="flex:1;">
                    <button class="btn btn-outline" type="button" onclick="IconPicker.open(cls => { document.getElementById('lIcon').value=cls; document.getElementById('lIconPreviewFa').className=cls; })"><i class="fas fa-icons"></i> 选择</button>
                </div>
            </div>
            <div id="lIconImgMode" style="display:none;">
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="lIconPreviewImg" src="" style="width:32px;height:32px;border-radius:6px;object-fit:cover;border:1px solid #ddd;display:none;">
                    <button class="btn btn-outline" type="button" onclick="document.getElementById('lIconUpload').click()"><i class="fas fa-upload"></i> 上传</button>
                    <button class="btn btn-outline" type="button" onclick="ImagePicker.open(url => { document.getElementById('lIconUrl').value = url; document.getElementById('lIconPreviewImg').src = '/' + url; document.getElementById('lIconPreviewImg').style.display = ''; })"><i class="fas fa-images"></i> 图片库</button>
                    <input type="file" id="lIconUpload" accept="image/*" style="display:none;" onchange="uploadIcon(this)">
                    <input type="hidden" id="lIconUrl">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" id="lShowIcon" checked>
                <span style="font-weight:400;">在前台显示图标</span>
            </label>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('addModal')">取消</button>
            <button class="btn btn-primary" onclick="save()">保存</button>
        </div>
    </div>
</div>

<script>
async function load() {
    const list = await API.get('/admin/api/links.php');
    const body = document.getElementById('linkList');
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-link"></i><p>暂无链接</p></div></td></tr>';
        return;
    }
    body.innerHTML = list.map(l => {
        let iconHtml = '<span style="color:#ccc;"><i class="fas fa-minus"></i></span>';
        if (l.icon_url) {
            iconHtml = `<img src="/${escapeHTML(l.icon_url)}" style="width:24px;height:24px;border-radius:4px;object-fit:cover;">`;
        } else if (l.icon) {
            iconHtml = `<i class="${escapeHTML(l.icon)}" style="font-size:1.2em;"></i>`;
        }
        return `
        <tr data-id="${l.id}" draggable="true">
            <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
            <td style="text-align:center;">${iconHtml}</td>
            <td><strong>${escapeHTML(l.name)}</strong></td>
            <td style="color:var(--text-secondary);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHTML(l.url)}</td>
            <td><label class="toggle"><input type="checkbox" ${l.show_icon?'checked':''} onchange="toggleIcon(${l.id},this.checked)"><span class="toggle-slider"></span></label></td>
            <td><label class="toggle"><input type="checkbox" ${l.is_active?'checked':''} onchange="toggleActive(${l.id},this.checked)"><span class="toggle-slider"></span></label></td>
            <td>
                <button class="btn btn-outline btn-sm" onclick='edit(${JSON.stringify(l)})'><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="del(${l.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    }).join('');

    initSortable(body, async (ids) => {
        await API.post('/admin/api/reorder.php', { table: 'friend_links', order: ids });
        Toast.success('排序已保存');
    });
}

function toggleIconMode() {
    const mode = document.querySelector('input[name="lIconType"]:checked').value;
    document.getElementById('lIconFaMode').style.display = mode === 'fa' ? '' : 'none';
    document.getElementById('lIconImgMode').style.display = mode === 'image' ? '' : 'none';
}

async function uploadIcon(input) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('file', input.files[0]);
    fd.append('category', 'icon');
    const res = await API.upload('/admin/api/upload.php', fd);
    if (res.url) {
        document.getElementById('lIconUrl').value = res.url;
        document.getElementById('lIconPreviewImg').src = '/' + res.url;
        document.getElementById('lIconPreviewImg').style.display = '';
        Toast.success('图标已上传');
    }
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = '添加链接';
    document.getElementById('editId').value = '';
    document.getElementById('lName').value = '';
    document.getElementById('lUrl').value = '';
    document.getElementById('lIcon').value = '';
    document.getElementById('lIconUrl').value = '';
    document.getElementById('lIconPreviewFa').className = 'fas fa-link';
    document.getElementById('lIconPreviewImg').style.display = 'none';
    document.querySelector('input[name="lIconType"][value="fa"]').checked = true;
    document.getElementById('lShowIcon').checked = true;
    toggleIconMode();
    Modal.show('addModal');
}

function edit(l) {
    document.getElementById('modalTitle').textContent = '编辑链接';
    document.getElementById('editId').value = l.id;
    document.getElementById('lName').value = l.name;
    document.getElementById('lUrl').value = l.url;
    document.getElementById('lIcon').value = l.icon || '';
    document.getElementById('lIconUrl').value = l.icon_url || '';
    document.getElementById('lShowIcon').checked = !!l.show_icon;

    if (l.icon_url) {
        document.querySelector('input[name="lIconType"][value="image"]').checked = true;
        document.getElementById('lIconPreviewImg').src = '/' + l.icon_url;
        document.getElementById('lIconPreviewImg').style.display = '';
    } else if (l.icon) {
        document.querySelector('input[name="lIconType"][value="fa"]').checked = true;
        document.getElementById('lIconPreviewFa').className = l.icon;
    } else {
        document.querySelector('input[name="lIconType"][value="none"]').checked = true;
    }
    toggleIconMode();
    Modal.show('addModal');
}

async function save() {
    const id = document.getElementById('editId').value;
    const iconType = document.querySelector('input[name="lIconType"]:checked').value;
    const body = {
        name: document.getElementById('lName').value.trim(),
        url: document.getElementById('lUrl').value.trim() || '#',
        icon: iconType === 'fa' ? document.getElementById('lIcon').value.trim() : '',
        icon_url: iconType === 'image' ? document.getElementById('lIconUrl').value.trim() : '',
        show_icon: document.getElementById('lShowIcon').checked ? 1 : 0,
    };

    if (id) {
        body.id = parseInt(id);
        body.is_active = 1;
        await API.put('/admin/api/links.php', body);
    } else {
        await API.post('/admin/api/links.php', body);
    }
    AlertModal.success('保存成功', '链接信息已保存');
    Modal.hide('addModal');
    document.getElementById('editId').value = '';
    document.getElementById('modalTitle').textContent = '添加链接';
    load();
}

async function toggleActive(id, active) {
    const list = await API.get('/admin/api/links.php');
    const link = list.find(l => l.id === id);
    if (!link) return;
    await API.put('/admin/api/links.php', { ...link, is_active: active ? 1 : 0 });
    Toast.success(active ? '已启用' : '已禁用');
}

async function toggleIcon(id, show) {
    const list = await API.get('/admin/api/links.php');
    const link = list.find(l => l.id === id);
    if (!link) return;
    await API.put('/admin/api/links.php', { ...link, show_icon: show ? 1 : 0 });
    Toast.success(show ? '图标已开启' : '图标已关闭');
}

async function del(id) {
    if (!confirmAction('确定删除此链接？')) return;
    await API.del('/admin/api/links.php', { id });
    Toast.success('已删除');
    load();
}

load();
</script>

<?php admin_footer(); ?>
