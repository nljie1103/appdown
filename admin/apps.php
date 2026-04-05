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
    <button class="btn btn-primary" onclick="Modal.show('addModal')"><i class="fas fa-plus"></i> 添加应用</button>
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
            <label>图标 (FontAwesome class)</label>
            <input type="text" class="form-control" id="addIcon" value="fas fa-tv" placeholder="fas fa-tv">
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
async function loadApps() {
    const apps = await API.get('/admin/api/apps.php');
    const body = document.getElementById('appList');

    if (apps.length === 0) {
        body.innerHTML = '<tr><td colspan="8"><div class="empty-state"><i class="fas fa-mobile-alt"></i><p>暂无应用，点击右上角添加</p></div></td></tr>';
        return;
    }

    body.innerHTML = apps.map(app => `
        <tr data-id="${app.id}" draggable="true">
            <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
            <td><i class="${app.icon}" style="color:${app.theme_color};margin-right:6px;"></i> <strong>${app.name}</strong></td>
            <td style="color:var(--text-secondary);">${app.slug}</td>
            <td><span class="color-swatch" style="background:${app.theme_color};"></span> ${app.theme_color}</td>
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
                <button class="btn btn-danger btn-sm" onclick="deleteApp(${app.id}, '${app.name}')"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

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

    if (!slug || !name) { Toast.error('标识和名称不能为空'); return; }

    await API.post('/admin/api/apps.php', { slug, name, icon, theme_color: color });
    Toast.success('添加成功');
    Modal.hide('addModal');
    loadApps();
}

async function toggleApp(id, active) {
    await API.put('/admin/api/apps.php', { id, is_active: active });
    Toast.success(active ? '已启用' : '已禁用');
}

async function deleteApp(id, name) {
    if (!confirmAction(`确定删除「${name}」？该应用下的所有下载按钮和轮播图也会被删除。`)) return;
    await API.del('/admin/api/apps.php', { id });
    Toast.success('已删除');
    loadApps();
}

loadApps();
</script>

<?php admin_footer(); ?>
