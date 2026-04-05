<?php
/**
 * 站点设置页
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('站点设置', 'settings');
?>

<div class="page-header"><h1>站点设置</h1></div>

<div class="card">
    <h3>基本信息</h3>
    <div class="form-row">
        <div class="form-group"><label>站点标题</label><input type="text" class="form-control" id="site_title"></div>
        <div class="form-group"><label>主标题 (h1)</label><input type="text" class="form-control" id="site_heading"></div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Logo地址</label>
            <div style="display:flex;gap:8px;">
                <input type="text" class="form-control" id="logo_url" style="flex:1;">
                <button class="btn btn-outline" onclick="uploadFor('logo_url','image')"><i class="fas fa-upload"></i></button>
            </div>
        </div>
        <div class="form-group">
            <label>Favicon地址</label>
            <div style="display:flex;gap:8px;">
                <input type="text" class="form-control" id="favicon_url" style="flex:1;">
                <button class="btn btn-outline" onclick="uploadFor('favicon_url','image')"><i class="fas fa-upload"></i></button>
            </div>
        </div>
    </div>
    <div class="form-group"><label>版权文本</label><input type="text" class="form-control" id="copyright"></div>
</div>

<div class="card">
    <h3>公告通知</h3>
    <div class="form-group">
        <label>启用公告 <label class="toggle" style="margin-left:8px;"><input type="checkbox" id="notice_enabled"><span class="toggle-slider"></span></label></label>
    </div>
    <div class="form-group"><label>公告内容</label><textarea class="form-control" id="notice_text" rows="3"></textarea></div>
</div>

<div class="card">
    <h3>展示数据</h3>
    <div class="form-row">
        <div class="form-group"><label>累计下载数</label><input type="number" class="form-control" id="stats_downloads"></div>
        <div class="form-group"><label>用户评分</label><input type="number" step="0.1" class="form-control" id="stats_rating"></div>
        <div class="form-group"><label>日活用户</label><input type="number" class="form-control" id="stats_daily_active"></div>
    </div>
</div>

<div class="card">
    <h3>轮播设置</h3>
    <div class="form-group"><label>轮播间隔 (毫秒)</label><input type="number" class="form-control" id="carousel_interval" step="500" min="1000"></div>
</div>

<div class="card">
    <h3>安全设置</h3>
    <div class="form-group">
        <label>后台登录验证码 <label class="toggle" style="margin-left:8px;"><input type="checkbox" id="captcha_enabled"><span class="toggle-slider"></span></label></label>
        <p style="color:var(--text-secondary);font-size:0.85em;margin-top:4px;">开启后登录需输入算术验证码，防止暴力破解</p>
    </div>
</div>

<div style="margin-top:20px;">
    <button class="btn btn-primary" onclick="saveAll()" style="padding:12px 32px;font-size:1em;"><i class="fas fa-save"></i> 保存所有设置</button>
</div>

<input type="file" id="hiddenUpload" accept="image/*" style="display:none;">

<script>
let uploadTarget = '';

const fields = ['site_title','site_heading','logo_url','favicon_url','copyright',
                'notice_text','notice_enabled','stats_downloads','stats_rating',
                'stats_daily_active','carousel_interval','captcha_enabled'];

async function load() {
    const s = await API.get('/admin/api/settings.php');
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (!el) return;
        if (el.type === 'checkbox') el.checked = s[f] === '1';
        else el.value = s[f] || '';
    });
}

async function saveAll() {
    const settings = {};
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (!el) return;
        settings[f] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
    });
    await API.post('/admin/api/settings.php', { settings });
    Toast.success('设置已保存');
}

function uploadFor(field, category) {
    uploadTarget = field;
    document.getElementById('hiddenUpload').click();
}

document.getElementById('hiddenUpload').addEventListener('change', async function() {
    if (!this.files.length || !uploadTarget) return;
    const fd = new FormData();
    fd.append('file', this.files[0]);
    fd.append('category', 'image');
    fd.append('_csrf', CSRF_TOKEN);
    try {
        const res = await API.upload('/admin/api/upload.php', fd);
        document.getElementById(uploadTarget).value = res.url;
        Toast.success('上传成功');
    } catch(e) {}
    this.value = '';
});

load();
</script>

<?php admin_footer(); ?>
