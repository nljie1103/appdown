<?php
/**
 * 导入导出页 — 数据备份与恢复（支持选择性导入导出 + uploads 文件打包）
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
        选择需要导出的数据类别，导出为加密备份文件（ZIP打包 + AES-256-GCM加密）。请牢记密码。
    </p>
    <div style="margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span style="font-weight:600;font-size:0.95em;">选择导出内容</span>
            <label style="font-size:0.85em;cursor:pointer;color:var(--primary);" onclick="toggleAll('exportChecks')">全选/取消</label>
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
        <div style="margin-top:10px;padding:10px 14px;background:var(--bg);border-radius:8px;display:flex;align-items:center;gap:10px;">
            <label class="check-item" style="background:none;padding:0;flex:1;">
                <input type="checkbox" id="exportUploads" checked>
                <span><b>上传文件目录</b> <small style="color:var(--text-secondary);">（安装包、图片等 uploads/ 目录下所有文件）</small></span>
            </label>
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
    <div id="exportProgress" style="display:none;margin-bottom:12px;">
        <div style="background:var(--border);border-radius:4px;overflow:hidden;height:6px;">
            <div id="exportBar" style="width:0%;height:100%;background:var(--primary);transition:width 0.3s;"></div>
        </div>
        <p style="font-size:0.8em;color:var(--text-secondary);margin-top:4px;" id="exportStatus">正在打包...</p>
    </div>
    <button class="btn btn-primary" onclick="doExport()" id="exportBtn"><i class="fas fa-download"></i> 导出备份</button>
</div>

<div class="card">
    <h3>导入数据</h3>
    <p style="color:var(--text-secondary);margin-bottom:16px;font-size:0.9em;">
        上传备份文件并输入密码解密，然后选择要导入的数据类别。<b style="color:var(--danger);">勾选的数据将覆盖对应的现有数据，此操作不可逆！</b>
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

    <div id="importPreview" style="display:none;">
        <div style="background:var(--bg);border-radius:8px;padding:16px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <span style="font-weight:600;font-size:0.95em;">备份内容（选择要导入的数据）</span>
                <label style="font-size:0.85em;cursor:pointer;color:var(--primary);" onclick="toggleAll('importChecks')">全选/取消</label>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:6px;" id="importChecks"></div>
            <div id="importUploadsWrap" style="display:none;margin-top:8px;padding:8px 12px;border:1px dashed var(--border);border-radius:6px;">
                <label class="check-item" style="background:none;padding:0;">
                    <input type="checkbox" id="importUploadsCheck" checked>
                    <span><b>恢复上传文件</b></span>
                    <span class="count" id="importUploadsInfo"></span>
                </label>
                <p style="font-size:0.78em;color:var(--text-secondary);margin:4px 0 0 24px;">将覆盖 uploads/ 目录下的同名文件</p>
            </div>
            <p style="font-size:0.8em;color:var(--text-secondary);margin-top:10px;" id="importMeta"></p>
        </div>
        <div id="importProgress" style="display:none;margin-bottom:12px;">
            <div style="background:var(--border);border-radius:4px;overflow:hidden;height:6px;">
                <div id="importBar" style="width:0%;height:100%;background:var(--primary);transition:width 0.3s;"></div>
            </div>
            <p style="font-size:0.8em;color:var(--text-secondary);margin-top:4px;" id="importStatus">导入中...</p>
        </div>
        <button class="btn btn-danger" onclick="doImport()" id="importBtn"><i class="fas fa-upload"></i> 导入选中数据</button>
    </div>
</div>

<div class="card">
    <h3>备份说明</h3>
    <table style="width:100%;font-size:0.9em;">
        <tbody>
            <tr><td style="font-weight:600;padding:8px 0;width:120px;">加密算法</td><td>AES-256-GCM（认证加密）</td></tr>
            <tr><td style="font-weight:600;padding:8px 0;">备份格式</td><td>ZIP打包 + AES-256-GCM加密（.enc文件）</td></tr>
            <tr><td style="font-weight:600;padding:8px 0;">可选内容</td><td>数据库记录（站点配置、应用、特色卡片等）+ uploads/ 上传文件目录</td></tr>
            <tr><td style="font-weight:600;padding:8px 0;">注意事项</td><td>导入大文件时需确保PHP的 upload_max_filesize 和 post_max_size 足够大</td></tr>
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
const tableLabels = {
    site_settings: '站点配置', apps: '应用数据', app_downloads: '下载按钮',
    app_images: '应用轮播图', feature_categories: '特色卡片分类', feature_cards: '特色卡片',
    friend_links: '友情链接', custom_code: '自定义代码', app_platforms: '附件平台分类',
    app_attachments: '附件文件记录', image_categories: '图片库分类', image_library: '图片库数据',
    admin_users: '管理员账户',
};

function toggleAll(containerId) {
    const boxes = document.querySelectorAll(`#${containerId} input[type=checkbox]`);
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
}

// ===== 导出 =====
async function doExport() {
    const pwd = document.getElementById('exportPwd').value;
    const pwd2 = document.getElementById('exportPwdConfirm').value;
    const tables = [...document.querySelectorAll('#exportChecks input:checked')].map(c => c.value);
    const includeUploads = document.getElementById('exportUploads').checked;

    if (!tables.length && !includeUploads) { AlertModal.error('请选择导出内容', '至少选择一项数据或上传文件目录'); return; }
    if (pwd.length < 4) { AlertModal.error('加密密码至少4位'); return; }
    if (pwd !== pwd2) { AlertModal.error('两次输入的密码不一致'); return; }

    const btn = document.getElementById('exportBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 打包加密中...';
    document.getElementById('exportProgress').style.display = '';
    document.getElementById('exportBar').style.width = '30%';
    document.getElementById('exportStatus').textContent = '正在打包数据...';

    try {
        const res = await fetch('/admin/api/backup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ action: 'export', password: pwd, tables, include_uploads: includeUploads })
        });

        document.getElementById('exportBar').style.width = '80%';
        document.getElementById('exportStatus').textContent = '正在下载...';

        if (res.headers.get('Content-Type')?.includes('application/json')) {
            const err = await res.json();
            AlertModal.error('导出失败', err.error || '未知错误');
            return;
        }

        const blob = await res.blob();
        const filename = res.headers.get('X-Filename') || 'appdown_backup.enc';
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = filename; a.click();
        URL.revokeObjectURL(url);

        document.getElementById('exportBar').style.width = '100%';
        document.getElementById('exportStatus').textContent = '导出完成';
        AlertModal.success('导出成功', `备份文件已下载（${(blob.size/1048576).toFixed(1)} MB），请妥善保管密码。`);
        document.getElementById('exportPwd').value = '';
        document.getElementById('exportPwdConfirm').value = '';
    } catch(e) {
        AlertModal.error('导出失败', e.message || '网络错误');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-download"></i> 导出备份';
    setTimeout(() => { document.getElementById('exportProgress').style.display = 'none'; }, 2000);
}

// ===== 导入 =====
async function decryptPreview() {
    const file = document.getElementById('importFile').files[0];
    const pwd = document.getElementById('importPwd').value;
    if (!file) { AlertModal.error('请选择备份文件'); return; }
    if (!pwd) { AlertModal.error('请输入解密密码'); return; }

    const btn = document.getElementById('decryptBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const fd = new FormData();
    fd.append('action', 'decrypt_preview');
    fd.append('file', file);
    fd.append('password', pwd);

    try {
        const res = await fetch('/admin/api/backup.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            body: fd
        });
        const data = await res.json();
        if (data.error) { AlertModal.error('解密失败', data.error); return; }
        renderImportPreview(data);
    } catch(e) {
        AlertModal.error('解密失败', e.message || '网络错误或文件过大，请检查PHP上传限制');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-lock-open"></i> 解密预览';
}

function renderImportPreview(res) {
    const container = document.getElementById('importChecks');
    container.innerHTML = '';

    for (const [table, count] of Object.entries(res.tables || {})) {
        const label = tableLabels[table] || table;
        const warn = table === 'admin_users' ? ' ⚠️' : '';
        const checked = table === 'admin_users' ? '' : 'checked';
        container.innerHTML += `<label class="check-item">
            <input type="checkbox" value="${table}" ${checked}>
            ${label}${warn} <span class="count">${count} 条</span>
        </label>`;
    }

    // 上传文件信息
    const uploadsWrap = document.getElementById('importUploadsWrap');
    if (res.has_uploads) {
        uploadsWrap.style.display = '';
        document.getElementById('importUploadsCheck').checked = true;
        document.getElementById('importUploadsInfo').textContent =
            `${res.uploads_count} 个文件 · ${res.uploads_size}`;
    } else {
        uploadsWrap.style.display = 'none';
    }

    const meta = res.meta || {};
    document.getElementById('importMeta').textContent =
        `备份时间: ${meta.exported_at || '未知'} · 版本: ${meta.version || '未知'}`;
    document.getElementById('importPreview').style.display = '';
}

async function doImport() {
    const file = document.getElementById('importFile').files[0];
    const pwd = document.getElementById('importPwd').value;
    const tables = [...document.querySelectorAll('#importChecks input:checked')].map(c => c.value);
    const includeUploads = document.getElementById('importUploadsCheck')?.checked || false;

    if (!tables.length && !includeUploads) { AlertModal.error('请选择导入内容'); return; }

    const hasAdmin = tables.includes('admin_users');
    const msg = hasAdmin
        ? '确定导入选中的数据吗？\n\n⚠️ 你选择了导入管理员账户，这将覆盖当前登录信息，可能导致无法登录！'
        : '确定导入选中的数据吗？选中的类别将覆盖现有数据，此操作不可逆！';
    if (!confirm(msg)) return;

    const btn = document.getElementById('importBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 导入中...';
    document.getElementById('importProgress').style.display = '';
    document.getElementById('importBar').style.width = '50%';
    document.getElementById('importStatus').textContent = '正在解密并恢复数据...';

    const fd = new FormData();
    fd.append('action', 'import');
    fd.append('file', file);
    fd.append('password', pwd);
    fd.append('tables', JSON.stringify(tables));
    fd.append('include_uploads', includeUploads ? '1' : '0');

    try {
        const res = await fetch('/admin/api/backup.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            body: fd
        });
        const data = await res.json();
        if (data.error) {
            AlertModal.error('导入失败', data.error);
        } else {
            document.getElementById('importBar').style.width = '100%';
            document.getElementById('importStatus').textContent = '导入完成';
            AlertModal.success('导入成功', data.message || '数据已恢复');
            document.getElementById('importFile').value = '';
            document.getElementById('importPwd').value = '';
            document.getElementById('importPreview').style.display = 'none';
        }
    } catch(e) {
        AlertModal.error('导入失败', e.message || '网络错误或文件过大');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-upload"></i> 导入选中数据';
    setTimeout(() => { document.getElementById('importProgress').style.display = 'none'; }, 2000);
}
</script>

<?php admin_footer(); ?>
