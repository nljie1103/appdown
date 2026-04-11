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
                <button class="btn btn-outline" onclick="uploadFor('logo_url','image')" title="上传"><i class="fas fa-upload"></i></button>
                <button class="btn btn-outline" onclick="ImagePicker.open(url => { document.getElementById('logo_url').value = url; })" title="从图片库选择"><i class="fas fa-images"></i></button>
            </div>
        </div>
        <div class="form-group">
            <label>Favicon地址</label>
            <div style="display:flex;gap:8px;">
                <input type="text" class="form-control" id="favicon_url" style="flex:1;">
                <button class="btn btn-outline" onclick="uploadFor('favicon_url','image')" title="上传"><i class="fas fa-upload"></i></button>
                <button class="btn btn-outline" onclick="ImagePicker.open(url => { document.getElementById('favicon_url').value = url; })" title="从图片库选择"><i class="fas fa-images"></i></button>
            </div>
        </div>
    </div>
    <div class="form-group"><label>版权文本</label><input type="text" class="form-control" id="copyright"></div>
</div>

<div class="card">
    <h3>网站背景</h3>
    <div class="form-group">
        <label>背景类型</label>
        <select class="form-control" id="bg_type" onchange="toggleBgOptions()">
            <option value="default">默认（透明）</option>
            <option value="color">纯色</option>
            <option value="gradient">渐变色</option>
            <option value="image">背景图片</option>
        </select>
    </div>
    <div id="bgColorOpt" style="display:none;">
        <div class="form-group">
            <label>背景颜色</label>
            <input type="color" class="form-control" id="bg_color" value="#f5f5f5" style="height:42px;">
        </div>
    </div>
    <div id="bgGradientOpt" style="display:none;">
        <div class="form-group"><label>渐变CSS</label><input type="text" class="form-control" id="bg_gradient" placeholder="如: linear-gradient(135deg, #667eea, #764ba2)"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;" id="gradientPresets"></div>
    </div>
    <div id="bgImageOpt" style="display:none;">
        <div class="form-group">
            <label>背景图片地址</label>
            <div style="display:flex;gap:8px;">
                <input type="text" class="form-control" id="bg_image" style="flex:1;" placeholder="如: https://... 或上传图片">
                <button class="btn btn-outline" onclick="uploadFor('bg_image','image')" title="上传"><i class="fas fa-upload"></i></button>
                <button class="btn btn-outline" onclick="ImagePicker.open(url => { document.getElementById('bg_image').value = url; updateBgPreview(); })" title="从图片库选择"><i class="fas fa-images"></i></button>
            </div>
        </div>
    </div>
    <div id="bgPreview" style="margin-top:12px;height:80px;border-radius:10px;border:1px solid #e5e5e5;"></div>
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

<div class="card">
    <h3>应用内浏览器跳转</h3>
    <div class="form-group">
        <label>全局开启 <label class="toggle" style="margin-left:8px;"><input type="checkbox" id="inapp_redirect"><span class="toggle-slider"></span></label></label>
        <p style="color:var(--text-secondary);font-size:0.85em;margin-top:4px;">当用户在微信、QQ、微博、抖音等应用内置浏览器打开时，自动弹出引导页提示用户在系统浏览器中打开</p>
    </div>
</div>

<div class="card">
    <h3><i class="fas fa-certificate" style="margin-right:6px;color:#e53e3e;"></i>Mobileconfig 签名</h3>
    <p style="color:var(--text-secondary);margin-bottom:16px;font-size:0.9em;">使用 SSL 证书对描述文件进行签名，签名后 iOS 设备上会显示"已验证"和组织信息。不配置则生成未签名的描述文件。</p>

    <div class="form-group">
        <label>组织名称 <small style="color:var(--text-secondary);">显示在 iOS 描述文件详情中</small></label>
        <input type="text" class="form-control" id="mc_payload_org" placeholder="如: My Company Inc.">
    </div>

    <div class="form-group">
        <label>证书输入方式</label>
        <div style="display:flex;gap:16px;margin-bottom:8px;">
            <label style="cursor:pointer;"><input type="radio" name="mcSignMode" value="text" checked onchange="toggleMcSignMode()"> 文本粘贴</label>
            <label style="cursor:pointer;"><input type="radio" name="mcSignMode" value="path" onchange="toggleMcSignMode()"> 服务器路径</label>
            <label style="cursor:pointer;"><input type="radio" name="mcSignMode" value="upload" onchange="toggleMcSignMode()"> 文件上传</label>
        </div>
        <input type="hidden" id="mc_sign_mode" value="text">
    </div>

    <!-- 文本粘贴模式 -->
    <div id="mcSignText">
        <div class="form-group">
            <label>签名证书 (PEM)</label>
            <textarea class="form-control" id="mc_sign_cert_text" rows="4" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----" style="font-family:monospace;font-size:0.85em;"></textarea>
        </div>
        <div class="form-group">
            <label>私钥 (PEM)</label>
            <textarea class="form-control" id="mc_sign_key_text" rows="4" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----" style="font-family:monospace;font-size:0.85em;"></textarea>
        </div>
        <div class="form-group">
            <label>证书链 (PEM，可选)</label>
            <textarea class="form-control" id="mc_sign_chain_text" rows="3" placeholder="中间证书，可留空" style="font-family:monospace;font-size:0.85em;"></textarea>
        </div>
    </div>

    <!-- 服务器路径模式 -->
    <div id="mcSignPath" style="display:none;">
        <div class="form-group">
            <label>证书文件路径</label>
            <input type="text" class="form-control" id="mc_sign_cert_path" placeholder="如: /etc/ssl/certs/cert.pem">
        </div>
        <div class="form-group">
            <label>私钥文件路径</label>
            <input type="text" class="form-control" id="mc_sign_key_path" placeholder="如: /etc/ssl/private/key.pem">
        </div>
        <div class="form-group">
            <label>证书链文件路径 (可选)</label>
            <input type="text" class="form-control" id="mc_sign_chain_path" placeholder="如: /etc/ssl/certs/chain.pem">
        </div>
    </div>

    <!-- 文件上传模式 -->
    <div id="mcSignUpload" style="display:none;">
        <div class="form-group">
            <label>签名证书</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-control" id="mc_sign_cert_upload" style="flex:1;" readonly placeholder="点击上传证书文件">
                <button class="btn btn-outline" onclick="uploadCertFile('mc_sign_cert_upload')"><i class="fas fa-upload"></i> 上传</button>
            </div>
        </div>
        <div class="form-group">
            <label>私钥</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-control" id="mc_sign_key_upload" style="flex:1;" readonly placeholder="点击上传私钥文件">
                <button class="btn btn-outline" onclick="uploadCertFile('mc_sign_key_upload')"><i class="fas fa-upload"></i> 上传</button>
            </div>
        </div>
        <div class="form-group">
            <label>证书链 (可选)</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-control" id="mc_sign_chain_upload" style="flex:1;" readonly placeholder="点击上传证书链文件">
                <button class="btn btn-outline" onclick="uploadCertFile('mc_sign_chain_upload')"><i class="fas fa-upload"></i> 上传</button>
            </div>
        </div>
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
                'stats_daily_active','carousel_interval','captcha_enabled',
                'bg_type','bg_color','bg_gradient','bg_image','inapp_redirect',
                'mc_sign_mode','mc_payload_org',
                'mc_sign_cert','mc_sign_key','mc_sign_chain'];

// 渐变预设
const GRADIENT_PRESETS = [
    { name: '紫蓝', css: 'linear-gradient(135deg, #667eea, #764ba2)' },
    { name: '暖橙', css: 'linear-gradient(135deg, #f093fb, #f5576c)' },
    { name: '海蓝', css: 'linear-gradient(135deg, #4facfe, #00f2fe)' },
    { name: '森绿', css: 'linear-gradient(135deg, #43e97b, #38f9d7)' },
    { name: '日落', css: 'linear-gradient(135deg, #fa709a, #fee140)' },
    { name: '深空', css: 'linear-gradient(135deg, #0c0c1d, #1a1a3e, #2d1b69)' },
    { name: '薄雾', css: 'linear-gradient(135deg, #d299c2, #fef9d7)' },
    { name: '极光', css: 'linear-gradient(135deg, #a8edea, #fed6e3)' },
];

function renderGradientPresets() {
    const el = document.getElementById('gradientPresets');
    el.innerHTML = GRADIENT_PRESETS.map(g => `
        <div style="width:60px;height:36px;border-radius:8px;cursor:pointer;background:${g.css};border:2px solid #e5e5e5;transition:border-color 0.2s;"
             title="${g.name}" onclick="document.getElementById('bg_gradient').value='${g.css}';updateBgPreview();"
             onmouseenter="this.style.borderColor='#007AFF'" onmouseleave="this.style.borderColor='#e5e5e5'"></div>
    `).join('');
}

function toggleBgOptions() {
    const type = document.getElementById('bg_type').value;
    document.getElementById('bgColorOpt').style.display = type === 'color' ? '' : 'none';
    document.getElementById('bgGradientOpt').style.display = type === 'gradient' ? '' : 'none';
    document.getElementById('bgImageOpt').style.display = type === 'image' ? '' : 'none';
    updateBgPreview();
}

function updateBgPreview() {
    const type = document.getElementById('bg_type').value;
    const preview = document.getElementById('bgPreview');
    preview.style.background = '';
    preview.style.backgroundSize = '';
    preview.style.backgroundPosition = '';
    if (type === 'color') {
        preview.style.background = document.getElementById('bg_color').value;
    } else if (type === 'gradient') {
        preview.style.background = document.getElementById('bg_gradient').value || '#f5f5f5';
    } else if (type === 'image') {
        const url = document.getElementById('bg_image').value;
        if (url) {
            preview.style.background = `url(${url}) center/cover no-repeat`;
        } else {
            preview.style.background = '#f5f5f5';
        }
    } else {
        preview.style.background = 'transparent';
        preview.style.border = '1px dashed #ccc';
    }
}

function toggleMcSignMode() {
    const mode = document.querySelector('input[name="mcSignMode"]:checked').value;
    document.getElementById('mc_sign_mode').value = mode;
    document.getElementById('mcSignText').style.display = mode === 'text' ? '' : 'none';
    document.getElementById('mcSignPath').style.display = mode === 'path' ? '' : 'none';
    document.getElementById('mcSignUpload').style.display = mode === 'upload' ? '' : 'none';
}

let certUploadTarget = '';
function uploadCertFile(targetId) {
    certUploadTarget = targetId;
    const input = document.getElementById('certFileInput');
    if (!input) {
        const el = document.createElement('input');
        el.type = 'file';
        el.id = 'certFileInput';
        el.accept = '.pem,.crt,.key,.p12';
        el.style.display = 'none';
        el.addEventListener('change', async function() {
            if (!this.files.length || !certUploadTarget) return;
            const fd = new FormData();
            fd.append('file', this.files[0]);
            fd.append('category', 'cert');
            fd.append('_csrf', CSRF_TOKEN);
            try {
                const res = await API.upload('/admin/api/upload.php', fd);
                document.getElementById(certUploadTarget).value = res.url;
                AlertModal.success('上传成功', '证书文件已上传');
            } catch(e) { AlertModal.error('上传失败', e.message || '未知错误'); }
            this.value = '';
        });
        document.body.appendChild(el);
    }
    document.getElementById('certFileInput').click();
}

async function load() {
    const s = await API.get('/admin/api/settings.php');
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (!el) return;
        if (el.type === 'checkbox') el.checked = s[f] === '1';
        else if (el.tagName === 'SELECT') el.value = s[f] || el.options[0].value;
        else el.value = s[f] || '';
    });
    // 恢复签名模式和对应字段
    const mode = s['mc_sign_mode'] || 'text';
    const radio = document.querySelector(`input[name="mcSignMode"][value="${mode}"]`);
    if (radio) radio.checked = true;
    document.getElementById('mc_sign_mode').value = mode;
    toggleMcSignMode();
    // 将证书值填入对应模式的字段
    const suffix = mode === 'text' ? '_text' : mode === 'path' ? '_path' : '_upload';
    ['mc_sign_cert','mc_sign_key','mc_sign_chain'].forEach(k => {
        const target = document.getElementById(k + suffix);
        if (target) target.value = s[k] || '';
    });
    toggleBgOptions();
    renderGradientPresets();
}

async function saveAll() {
    const settings = {};
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (!el) return;
        settings[f] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
    });
    // 从当前模式的字段取证书值
    const mode = document.getElementById('mc_sign_mode').value || 'text';
    const suffix = mode === 'text' ? '_text' : mode === 'path' ? '_path' : '_upload';
    settings['mc_sign_cert'] = (document.getElementById('mc_sign_cert' + suffix)?.value || '').trim();
    settings['mc_sign_key'] = (document.getElementById('mc_sign_key' + suffix)?.value || '').trim();
    settings['mc_sign_chain'] = (document.getElementById('mc_sign_chain' + suffix)?.value || '').trim();
    settings['mc_sign_mode'] = mode;
    await API.post('/admin/api/settings.php', { settings });
    AlertModal.success('保存成功', '所有站点设置已保存');
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
        AlertModal.success('上传成功', '文件已上传');
        if (uploadTarget === 'bg_image') updateBgPreview();
    } catch(e) {}
    this.value = '';
});

// 颜色/渐变变化时更新预览
document.getElementById('bg_color').addEventListener('input', updateBgPreview);
document.getElementById('bg_gradient').addEventListener('input', updateBgPreview);
document.getElementById('bg_image').addEventListener('input', updateBgPreview);

load();
</script>

<?php admin_footer(); ?>
