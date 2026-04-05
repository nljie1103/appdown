<?php
/**
 * 特色卡片管理页
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('特色卡片', 'features');
?>

<div class="page-header">
    <h1>特色卡片</h1>
    <button class="btn btn-primary" onclick="Modal.show('addModal')"><i class="fas fa-plus"></i> 添加卡片</button>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th></th><th>标题</th><th>描述</th><th>状态</th><th>操作</th></tr></thead>
            <tbody id="featureList"></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3 id="modalTitle">添加卡片</h3>
        <input type="hidden" id="editId">
        <div class="form-group"><label>标题</label><input type="text" class="form-control" id="fTitle"></div>
        <div class="form-group"><label>描述</label><textarea class="form-control" id="fDesc" rows="3"></textarea></div>
        <div class="form-group"><label>图标 (可选FA class)</label><input type="text" class="form-control" id="fIcon" placeholder="如: fas fa-star"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('addModal')">取消</button>
            <button class="btn btn-primary" onclick="save()">保存</button>
        </div>
    </div>
</div>

<script>
async function load() {
    const list = await API.get('/admin/api/features.php');
    const body = document.getElementById('featureList');
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="fas fa-star"></i><p>暂无卡片</p></div></td></tr>';
        return;
    }
    body.innerHTML = list.map(f => `
        <tr data-id="${f.id}" draggable="true">
            <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
            <td><strong>${f.title}</strong></td>
            <td style="color:var(--text-secondary);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${f.description}</td>
            <td><label class="toggle"><input type="checkbox" ${f.is_active?'checked':''} onchange="toggle(${f.id},this.checked)"><span class="toggle-slider"></span></label></td>
            <td>
                <button class="btn btn-outline btn-sm" onclick='edit(${JSON.stringify(f)})'><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="del(${f.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    initSortable(body, async (ids) => {
        await API.post('/admin/api/reorder.php', { table: 'feature_cards', order: ids });
        Toast.success('排序已保存');
    });
}

function edit(f) {
    document.getElementById('modalTitle').textContent = '编辑卡片';
    document.getElementById('editId').value = f.id;
    document.getElementById('fTitle').value = f.title;
    document.getElementById('fDesc').value = f.description;
    document.getElementById('fIcon').value = f.icon;
    Modal.show('addModal');
}

async function save() {
    const id = document.getElementById('editId').value;
    const body = {
        title: document.getElementById('fTitle').value.trim(),
        description: document.getElementById('fDesc').value.trim(),
        icon: document.getElementById('fIcon').value.trim(),
    };

    if (id) {
        body.id = parseInt(id);
        body.is_active = 1;
        await API.put('/admin/api/features.php', body);
    } else {
        await API.post('/admin/api/features.php', body);
    }
    Toast.success('已保存');
    Modal.hide('addModal');
    document.getElementById('editId').value = '';
    document.getElementById('modalTitle').textContent = '添加卡片';
    load();
}

async function toggle(id, active) {
    // 需要获取现有数据
    const list = await API.get('/admin/api/features.php');
    const item = list.find(f => f.id == id);
    if (item) await API.put('/admin/api/features.php', { ...item, is_active: active });
    Toast.success(active ? '已启用' : '已禁用');
}

async function del(id) {
    if (!confirmAction('确定删除此卡片？')) return;
    await API.del('/admin/api/features.php', { id });
    Toast.success('已删除');
    load();
}

load();
</script>

<?php admin_footer(); ?>
