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
    <button class="btn btn-primary" onclick="Modal.show('addModal')"><i class="fas fa-plus"></i> 添加链接</button>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th></th><th>名称</th><th>链接地址</th><th>状态</th><th>操作</th></tr></thead>
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
        body.innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="fas fa-link"></i><p>暂无链接</p></div></td></tr>';
        return;
    }
    body.innerHTML = list.map(l => `
        <tr data-id="${l.id}" draggable="true">
            <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
            <td><strong>${l.name}</strong></td>
            <td style="color:var(--text-secondary);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${l.url}</td>
            <td><label class="toggle"><input type="checkbox" ${l.is_active?'checked':''} onchange="toggle(${l.id},this.checked,'${l.name}','${l.url}')"><span class="toggle-slider"></span></label></td>
            <td>
                <button class="btn btn-outline btn-sm" onclick='edit(${JSON.stringify(l)})'><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="del(${l.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    initSortable(body, async (ids) => {
        await API.post('/admin/api/reorder.php', { table: 'friend_links', order: ids });
        Toast.success('排序已保存');
    });
}

function edit(l) {
    document.getElementById('modalTitle').textContent = '编辑链接';
    document.getElementById('editId').value = l.id;
    document.getElementById('lName').value = l.name;
    document.getElementById('lUrl').value = l.url;
    Modal.show('addModal');
}

async function save() {
    const id = document.getElementById('editId').value;
    const body = {
        name: document.getElementById('lName').value.trim(),
        url: document.getElementById('lUrl').value.trim() || '#',
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

async function toggle(id, active, name, url) {
    await API.put('/admin/api/links.php', { id, name, url, is_active: active });
    Toast.success(active ? '已启用' : '已禁用');
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
