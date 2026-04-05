<?php
/**
 * 账户管理页 — 修改用户名和密码
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('账户管理', 'account');
?>

<div class="page-header"><h1>账户管理</h1></div>

<div class="card">
    <h3>账户信息</h3>
    <div class="form-row">
        <div class="form-group">
            <label>当前用户名</label>
            <input type="text" class="form-control" id="currentUsername" readonly style="background:#f5f5f5;">
        </div>
        <div class="form-group">
            <label>创建时间</label>
            <input type="text" class="form-control" id="createdAt" readonly style="background:#f5f5f5;">
        </div>
        <div class="form-group">
            <label>最后登录</label>
            <input type="text" class="form-control" id="lastLogin" readonly style="background:#f5f5f5;">
        </div>
    </div>
</div>

<div class="card">
    <h3>修改用户名</h3>
    <div class="form-row">
        <div class="form-group"><label>新用户名</label><input type="text" class="form-control" id="newUsername" placeholder="输入新的用户名"></div>
        <div class="form-group"><label>当前密码 <small style="color:var(--text-secondary);">验证身份</small></label><input type="password" class="form-control" id="usernameCurrentPwd" placeholder="输入当前密码以确认"></div>
    </div>
    <button class="btn btn-primary" onclick="changeUsername()"><i class="fas fa-user-edit"></i> 修改用户名</button>
</div>

<div class="card">
    <h3>修改密码</h3>
    <div class="form-group"><label>当前密码</label><input type="password" class="form-control" id="currentPwd" placeholder="输入当前密码"></div>
    <div class="form-row">
        <div class="form-group"><label>新密码</label><input type="password" class="form-control" id="newPwd" placeholder="至少6位"></div>
        <div class="form-group"><label>确认新密码</label><input type="password" class="form-control" id="confirmPwd" placeholder="再次输入新密码"></div>
    </div>
    <button class="btn btn-primary" onclick="changePassword()"><i class="fas fa-key"></i> 修改密码</button>
</div>

<script>
async function loadAccount() {
    const user = await API.get('/admin/api/account.php');
    document.getElementById('currentUsername').value = user.username || '';
    document.getElementById('createdAt').value = user.created_at || '';
    document.getElementById('lastLogin').value = user.last_login || '从未登录';
}

async function changeUsername() {
    const newUsername = document.getElementById('newUsername').value.trim();
    const currentPwd = document.getElementById('usernameCurrentPwd').value;
    if (!newUsername) { AlertModal.error('请填写新用户名'); return; }
    if (!currentPwd) { AlertModal.error('请输入当前密码以验证身份'); return; }
    try {
        const res = await API.put('/admin/api/account.php', {
            action: 'username',
            new_username: newUsername,
            current_password: currentPwd
        });
        AlertModal.success('用户名修改成功', '新用户名: <b>' + escapeHTML(newUsername) + '</b>');
        document.getElementById('usernameCurrentPwd').value = '';
        document.getElementById('newUsername').value = '';
        loadAccount();
    } catch(e) {}
}

async function changePassword() {
    const currentPwd = document.getElementById('currentPwd').value;
    const newPwd = document.getElementById('newPwd').value;
    const confirmPwd = document.getElementById('confirmPwd').value;
    if (!currentPwd) { AlertModal.error('请输入当前密码'); return; }
    if (!newPwd || newPwd.length < 6) { AlertModal.error('新密码长度不能少于6位'); return; }
    if (newPwd !== confirmPwd) { AlertModal.error('两次输入的新密码不一致'); return; }
    try {
        const res = await API.put('/admin/api/account.php', {
            action: 'password',
            current_password: currentPwd,
            new_password: newPwd,
            confirm_password: confirmPwd
        });
        AlertModal.success('密码修改成功', '下次登录请使用新密码');
        document.getElementById('currentPwd').value = '';
        document.getElementById('newPwd').value = '';
        document.getElementById('confirmPwd').value = '';
    } catch(e) {}
}

loadAccount();
</script>

<?php admin_footer(); ?>
