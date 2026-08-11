<?php
/** SaaS 租户账户管理页 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();
admin_header('账户管理', 'account');
?>
<div class="page-header"><h1>账户管理</h1></div>
<div class="card">
    <h3>租户账户</h3>
    <div class="form-row">
        <div class="form-group"><label>用户名 / URL Slug</label><input type="text" class="form-control" id="currentUsername" readonly style="background:#f5f5f5;"></div>
        <div class="form-group"><label>公开分发页</label><input type="text" class="form-control" id="publicUrl" readonly style="background:#f5f5f5;"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>创建时间</label><input type="text" class="form-control" id="createdAt" readonly style="background:#f5f5f5;"></div>
        <div class="form-group"><label>最后登录</label><input type="text" class="form-control" id="lastLogin" readonly style="background:#f5f5f5;"></div>
    </div>
    <p style="color:var(--text-secondary);font-size:.88em;margin-top:8px;">用户名同时决定公开地址（例如 <code>/leon/</code>），为避免目录、链接和缓存迁移错误，只能由超级管理员管理。</p>
</div>
<div class="card">
    <h3>修改密码</h3>
    <div class="form-group"><label>当前密码</label><input type="password" class="form-control" id="currentPwd" placeholder="输入当前密码" autocomplete="current-password"></div>
    <div class="form-row">
        <div class="form-group"><label>新密码</label><input type="password" class="form-control" id="newPwd" placeholder="至少8位" autocomplete="new-password"></div>
        <div class="form-group"><label>确认新密码</label><input type="password" class="form-control" id="confirmPwd" placeholder="再次输入新密码" autocomplete="new-password"></div>
    </div>
    <button class="btn btn-primary" onclick="changePassword()"><i class="fas fa-key"></i> 修改密码</button>
</div>
<script>
async function loadAccount(){
    const user=await API.get('/admin/api/account.php');
    document.getElementById('currentUsername').value=user.username||'';
    document.getElementById('publicUrl').value=location.origin+'/'+(user.username||'')+'/';
    document.getElementById('createdAt').value=user.created_at||'';
    document.getElementById('lastLogin').value=user.last_login||'从未登录';
}
async function changePassword(){
    const currentPwd=document.getElementById('currentPwd').value;
    const newPwd=document.getElementById('newPwd').value;
    const confirmPwd=document.getElementById('confirmPwd').value;
    if(!currentPwd){AlertModal.error('请输入当前密码');return;}
    if(!newPwd||newPwd.length<8){AlertModal.error('新密码长度不能少于8位');return;}
    if(newPwd!==confirmPwd){AlertModal.error('两次输入的新密码不一致');return;}
    try{
        await API.put('/admin/api/account.php',{action:'password',current_password:currentPwd,new_password:newPwd,confirm_password:confirmPwd});
        AlertModal.success('密码修改成功','其他已登录设备已退出，当前设备可继续使用');
        document.getElementById('currentPwd').value='';document.getElementById('newPwd').value='';document.getElementById('confirmPwd').value='';
    }catch(e){}
}
loadAccount();
</script>
<?php admin_footer(); ?>
