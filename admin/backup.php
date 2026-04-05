<?php
/**
 * 导入导出页 — 数据备份与恢复
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
        将所有站点数据导出为加密备份文件，用于迁移或灾难恢复。数据使用 AES-256-GCM 加密，请牢记密码。
    </p>
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
        从加密备份文件恢复数据。<b style="color:var(--danger);">注意：导入会覆盖当前所有站点数据（不含管理员账户），此操作不可逆！</b>
    </p>
    <div class="form-group">
        <label>选择备份文件</label>
        <input type="file" class="form-control" id="importFile" accept=".enc">
    </div>
    <div class="form-group">
        <label>解密密码</label>
        <input type="password" class="form-control" id="importPwd" placeholder="输入导出时设置的密码">
    </div>
    <div class="form-group">
        <label>
            <label class="toggle" style="margin-right:8px;">
                <input type="checkbox" id="importAccounts">
                <span class="toggle-slider"></span>
            </label>
            同时恢复管理员账户
        </label>
        <p style="color:var(--text-secondary);font-size:0.85em;margin-top:4px;">开启后会覆盖当前管理员账户为备份中的账户，可能导致无法登录</p>
    </div>
    <button class="btn btn-danger" onclick="doImport()" id="importBtn"><i class="fas fa-upload"></i> 导入恢复</button>
</div>

<div class="card">
    <h3>备份说明</h3>
    <table style="width:100%;font-size:0.9em;">
        <tbody>
            <tr><td style="font-weight:600;padding:8px 0;width:120px;">加密算法</td><td>AES-256-GCM（认证加密）</td></tr>
            <tr><td style="font-weight:600;padding:8px 0;">备份内容</td><td>应用配置、下载按钮、轮播图、站点设置、特色卡片、友情链接、自定义代码、附件记录</td></tr>
            <tr><td style="font-weight:600;padding:8px 0;">不包含</td><td>上传的文件（图片、安装包等需单独备份 uploads/ 目录）</td></tr>
            <tr><td style="font-weight:600;padding:8px 0;">文件格式</td><td>.enc 加密文件（无密码无法查看内容）</td></tr>
        </tbody>
    </table>
</div>

<script>
async function doExport() {
    const pwd = document.getElementById('exportPwd').value;
    const pwd2 = document.getElementById('exportPwdConfirm').value;
    if (pwd.length < 4) { AlertModal.error('加密密码至少4位'); return; }
    if (pwd !== pwd2) { AlertModal.error('两次输入的密码不一致'); return; }

    document.getElementById('exportBtn').disabled = true;
    document.getElementById('exportBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 导出中...';
    try {
        const res = await API.post('/admin/api/backup.php', {
            action: 'export',
            password: pwd
        });
        // 触发下载
        const blob = new Blob([res.data], { type: 'application/octet-stream' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = res.filename;
        a.click();
        URL.revokeObjectURL(url);
        AlertModal.success('导出成功', '备份文件已开始下载，请妥善保管加密密码。');
        document.getElementById('exportPwd').value = '';
        document.getElementById('exportPwdConfirm').value = '';
    } catch(e) {}
    document.getElementById('exportBtn').disabled = false;
    document.getElementById('exportBtn').innerHTML = '<i class="fas fa-download"></i> 导出备份';
}

async function doImport() {
    const file = document.getElementById('importFile').files[0];
    const pwd = document.getElementById('importPwd').value;
    if (!file) { AlertModal.error('请选择备份文件'); return; }
    if (!pwd) { AlertModal.error('请输入解密密码'); return; }
    if (!confirm('确定要导入吗？此操作会覆盖当前所有站点数据，不可恢复！')) return;

    document.getElementById('importBtn').disabled = true;
    document.getElementById('importBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 导入中...';
    try {
        const text = await file.text();
        const res = await API.post('/admin/api/backup.php', {
            action: 'import',
            password: pwd,
            data: text,
            include_accounts: document.getElementById('importAccounts').checked
        });
        AlertModal.success('导入成功', res.message || '数据已恢复');
        document.getElementById('importFile').value = '';
        document.getElementById('importPwd').value = '';
    } catch(e) {}
    document.getElementById('importBtn').disabled = false;
    document.getElementById('importBtn').innerHTML = '<i class="fas fa-upload"></i> 导入恢复';
}
</script>

<?php admin_footer(); ?>
