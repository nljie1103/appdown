<?php
/**
 * 导入导出页 — 数据备份与恢复（支持选择性导入导出）
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('导入导出', 'backup');
?>

<div class="page-header"><h1>导入导出</h1></div>

<div class="card">
    <h3>导出数据</h3>
    <p style="color:var(--text-secondary);margin-bottom:16px;font-size:0.9em;">
        选择需要导出的数据类别，导出为加密备份文件。数据使用 AES-256-GCM 加密，请牢记密码。
    </p>
    <div style="margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span style="font-weight:600;font-size:0.95em;">选择导出内容</span>
            <label style="font-size:0.85em;cursor:pointer;color:var(--primary);" onclick="toggleExportAll()">全选/取消</label>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;" id="exportChecks">
            <label class="check-item"><input type="checkbox" value="site_settings" checked> 站点配置</label>
            <label class="check-item"><input type="checkbox" value="apps" checked> 应用数据</label>
            <label class="check-item"><input type="checkbox" value="app_downloads" checked> 下载按钮</label>
            <label class="check-item"><input type="checkbox" value="app_images" checked> 应用轮播图</label>
            <label class="check-item"><input type="checkbox" value="feature_categories" checked> 特色卡片分类</label>
            <label class="check-item"><input type="checkbox" value="feature_cards" checked> 特色卡片</label>
            <label class="check-item"><input type="checkbox" value="friend_links" checked> 友情链接</label>
            <label class="check-item"><input type="checkbox" value="custom_code" checked> 自定义代码</label>
            <label class="check-item"><input type="checkbox" value="app_platforms" checked> 附件平台分类</label>
            <label class="check-item"><input type="checkbox" value="app_attachments" checked> 附件文件记录</label>
            <label class="check-item"><input type="checkbox" value="image_categories" checked> 图片库分类</label>
            <label class="check-item"><input type="checkbox" value="image_library" checked> 图片库数据</label>
            <label class="check-item"><input type="checkbox" value="admin_users"> 管理员账户</label>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>加密密码</label>
            <input type="password" class="form-control" id="exportPwd" placeholder="设置加密密码（至少4位）">
        </div>
        <div class="form-group">
            <label>确认密码</label>
            <input type="password" class="form-control" id="exportPwdConfirm" placeholder="再次输入密码">
        </div>
    </div>
    <button class="btn btn-primary" onclick="doExport()" id="exportBtn"><i class="fas fa-download"></i> 导出备份</button>
</div>

<div class="card">
    <h3>导入数据</h3>
    <p style="color:var(--text-secondary);margin-bottom:16px;font-size:0.9em;">
        上传备份文件并输入密码解密，然后选择要导入的数据类别。<b style="color:var(--danger);">注意：勾选的数据将覆盖对应的现有数据，此操作不可逆！</b>
    </p>
    <div class="form-row">
        <div class="form-group">
            <label>选择备份文件</label>
            <input type="file" class="form-control" id="importFile" accept=".enc">
        </div>
        <div class="form-group">
            <label>解密密码</label>
            <div style="display:flex;gap:8px;">
                <input type="password" class="form-control" id="importPwd" placeholder="输入导出时设置的密码" style="flex:1;">
                <button class="btn btn-outline" onclick="decryptPreview()" id="decryptBtn"><i class="fas fa-lock-open"></i> 解密预览</button>
            </div>
        </div>
    </div>

    <!-- 解密后的选择区域 -->
    <div id="importPreview" style="display:none;">
        <div style="background:var(--bg);border-radius:8px;padding:16px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <span style="font-weight:600;font-size:0.95em;">备份内容（选择要导入的数据）</span>
                <label style="font-size:0.85em;cursor:pointer;color:var(--primary);" onclick="toggleImportAll()">全选/取消</label>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:6px;" id="importChecks"></div>
            <p style="font-size:0.8em;color:var(--text-secondary);margin-top:10px;" id="importMeta"></p>
        </div>
        <button class="btn btn-danger" onclick="doImport()" id="importBtn"><i class="fas fa-upload"></i> 导入选中数据</button>
    </div>
</div>

<div class="card">
    <h3>备份说明</h3>
    <table style="width:100%;font-size:0.9em;">
        <tbody>
            <tr><td style="font-weight:600;padding:8px 0;width:120px;">加密算法</td><td>AES-256-GCM（认证加密）</td></tr>
            <tr><td style="font-weight:600;padding:8px 0;">可选内容</td><td>站点配置、应用数据、下载按钮、轮播图、特色卡片(含分类)、友情链接、自定义代码、附件记录、图片库、管理员账户</td></tr>
            <tr><td style="font-weight:600;padding:8px 0;">不包含</td><td>上传的文件（图片、安装包等需单独备份 uploads/ 目录）</td></tr>
            <tr><td style="font-weight:600;padding:8px 0;">文件格式</td><td>.enc 加密文件（无密码无法查看内容）</td></tr>
        </tbody>
    </table>
</div>

<style>
.check-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 12px;
    background: var(--bg); border-radius: 6px; cursor: pointer; font-size: 0.9em;
    transition: background 0.15s; user-select: none;
}
.check-item:hover { background: var(--border); }
.check-item input { margin: 0; accent-color: var(--primary); width: 16px; height: 16px; }
.check-item .count { font-size: 0.8em; color: var(--text-secondary); margin-left: auto; }
</style>

<script>
// 表名到中文标签的映射
const tableLabels = {
    site_settings: '站点配置',
    apps: '应用数据',
    app_downloads: '下载按钮',
    app_images: '应用轮播图',
    feature_categories: '特色卡片分类',
    feature_cards: '特色卡片',
    friend_links: '友情链接',
    custom_code: '自定义代码',
    app_platforms: '附件平台分类',
    app_attachments: '附件文件记录',
    image_categories: '图片库分类',
    image_library: '图片库数据',
    admin_users: '管理员账户',
};

function toggleExportAll() {
    const boxes = document.querySelectorAll('#exportChecks input[type=checkbox]');
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
}

function toggleImportAll() {
    const boxes = document.querySelectorAll('#importChecks input[type=checkbox]');
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
}

function getExportTables() {
    return [...document.querySelectorAll('#exportChecks input:checked')].map(c => c.value);
}

async function doExport() {
    const pwd = document.getElementById('exportPwd').value;
    const pwd2 = document.getElementById('exportPwdConfirm').value;
    const tables = getExportTables();

    if (!tables.length) { AlertModal.error('请选择导出内容', '至少选择一项数据'); return; }
    if (pwd.length < 4) { AlertModal.error('加密密码至少4位'); return; }
    if (pwd !== pwd2) { AlertModal.error('两次输入的密码不一致'); return; }

    document.getElementById('exportBtn').disabled = true;
    document.getElementById('exportBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 导出中...';
    try {
        const res = await API.post('/admin/api/backup.php', {
            action: 'export',
            password: pwd,
            tables: tables
        });
        const blob = new Blob([res.data], { type: 'application/octet-stream' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = res.filename;
        a.click();
        URL.revokeObjectURL(url);
        AlertModal.success('导出成功', `已导出 ${tables.length} 类数据，请妥善保管加密密码。`);
        document.getElementById('exportPwd').value = '';
        document.getElementById('exportPwdConfirm').value = '';
    } catch(e) {}
    document.getElementById('exportBtn').disabled = false;
    document.getElementById('exportBtn').innerHTML = '<i class="fas fa-download"></i> 导出备份';
}

// ===== 导入 =====
let decryptedData = null;

async function decryptPreview() {
    const file = document.getElementById('importFile').files[0];
    const pwd = document.getElementById('importPwd').value;
    if (!file) { AlertModal.error('请选择备份文件'); return; }
    if (!pwd) { AlertModal.error('请输入解密密码'); return; }

    document.getElementById('decryptBtn').disabled = true;
    document.getElementById('decryptBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 解密中...';
    try {
        const text = await file.text();
        const res = await API.post('/admin/api/backup.php', {
            action: 'decrypt_preview',
            password: pwd,
            data: text
        });
        decryptedData = res;
        renderImportPreview(res);
    } catch(e) {}
    document.getElementById('decryptBtn').disabled = false;
    document.getElementById('decryptBtn').innerHTML = '<i class="fas fa-lock-open"></i> 解密预览';
}

function renderImportPreview(res) {
    const container = document.getElementById('importChecks');
    const tables = res.tables; // { table_name: count, ... }
    container.innerHTML = '';

    for (const [table, count] of Object.entries(tables)) {
        const label = tableLabels[table] || table;
        const warn = table === 'admin_users' ? ' ⚠️' : '';
        const checked = table === 'admin_users' ? '' : 'checked';
        container.innerHTML += `<label class="check-item">
            <input type="checkbox" value="${table}" ${checked}>
            ${label}${warn} <span class="count">${count} 条</span>
        </label>`;
    }

    const meta = res.meta || {};
    document.getElementById('importMeta').textContent =
        `备份时间: ${meta.exported_at || '未知'} · 版本: ${meta.version || '未知'}`;

    document.getElementById('importPreview').style.display = '';
}

async function doImport() {
    if (!decryptedData) { AlertModal.error('请先解密预览备份文件'); return; }

    const tables = [...document.querySelectorAll('#importChecks input:checked')].map(c => c.value);
    if (!tables.length) { AlertModal.error('请选择导入内容', '至少选择一项数据'); return; }

    const hasAdmin = tables.includes('admin_users');
    const msg = hasAdmin
        ? '确定导入选中的数据吗？\n\n⚠️ 你选择了导入管理员账户，这将覆盖当前登录信息，可能导致无法登录！'
        : '确定导入选中的数据吗？选中的类别将覆盖现有数据，此操作不可逆！';
    if (!confirm(msg)) return;

    document.getElementById('importBtn').disabled = true;
    document.getElementById('importBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 导入中...';
    try {
        const text = await document.getElementById('importFile').files[0].text();
        const res = await API.post('/admin/api/backup.php', {
            action: 'import',
            password: document.getElementById('importPwd').value,
            data: text,
            tables: tables
        });
        AlertModal.success('导入成功', res.message || '数据已恢复');
        document.getElementById('importFile').value = '';
        document.getElementById('importPwd').value = '';
        document.getElementById('importPreview').style.display = 'none';
        decryptedData = null;
    } catch(e) {}
    document.getElementById('importBtn').disabled = false;
    document.getElementById('importBtn').innerHTML = '<i class="fas fa-upload"></i> 导入选中数据';
}
</script>

<?php admin_footer(); ?>
