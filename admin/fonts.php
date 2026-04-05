<?php
/**
 * 字体管理页
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('字体管理', 'fonts');
?>

<div class="page-header"><h1>字体管理</h1></div>

<div class="card">
    <h3>当前字体</h3>
    <div class="form-row">
        <div class="form-group">
            <label>字体名称</label>
            <input type="text" class="form-control" id="fontFamily" placeholder="如: CustomFont">
        </div>
        <div class="form-group">
            <label>字体文件地址</label>
            <div style="display:flex;gap:8px;">
                <input type="text" class="form-control" id="fontUrl" style="flex:1;">
                <button class="btn btn-outline" onclick="document.getElementById('fontUpload').click()"><i class="fas fa-upload"></i> 上传</button>
                <input type="file" id="fontUpload" accept=".ttf,.woff,.woff2,.otf" style="display:none;">
            </div>
        </div>
    </div>
    <div id="fontPreview" style="margin:16px 0;padding:20px;background:#f9f9f9;border-radius:8px;font-size:1.5em;">
        字体预览: 杰哩杰哩影视APP ABCDEFG 1234567890
    </div>
    <button class="btn btn-primary" onclick="save()"><i class="fas fa-save"></i> 保存</button>
</div>

<div class="card">
    <h3>内置字体选择</h3>
    <p style="color:var(--text-secondary);margin-bottom:12px;">选择内置字体将使用系统字体，无需上传文件</p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;" id="builtinFonts"></div>
</div>

<script>
const builtinFonts = [
    { name: 'system-ui', label: '系统默认' },
    { name: 'Arial, sans-serif', label: 'Arial' },
    { name: '"Segoe UI", sans-serif', label: 'Segoe UI' },
    { name: '"PingFang SC", sans-serif', label: '苹方' },
    { name: '"Microsoft YaHei", sans-serif', label: '微软雅黑' },
    { name: '"Noto Sans SC", sans-serif', label: 'Noto Sans SC' },
    { name: 'serif', label: '衬线体' },
    { name: 'monospace', label: '等宽体' },
];

async function load() {
    const s = await API.get('/admin/api/settings.php');
    document.getElementById('fontFamily').value = s.font_family || 'CustomFont';
    document.getElementById('fontUrl').value = s.font_url || '';
    updatePreview();

    const container = document.getElementById('builtinFonts');
    container.innerHTML = builtinFonts.map(f =>
        `<button class="btn btn-outline" onclick="selectBuiltin('${f.name}')" style="font-family:${f.name};">${f.label}</button>`
    ).join('');
}

function selectBuiltin(family) {
    document.getElementById('fontFamily').value = family;
    document.getElementById('fontUrl').value = '';
    updatePreview();
}

function updatePreview() {
    const family = document.getElementById('fontFamily').value;
    document.getElementById('fontPreview').style.fontFamily = family;
}

document.getElementById('fontFamily').addEventListener('input', updatePreview);

document.getElementById('fontUpload').addEventListener('change', async function() {
    if (!this.files.length) return;
    const fd = new FormData();
    fd.append('file', this.files[0]);
    fd.append('category', 'font');
    fd.append('_csrf', CSRF_TOKEN);
    try {
        const res = await API.upload('/admin/api/upload.php', fd);
        document.getElementById('fontUrl').value = res.url;
        Toast.success('字体上传成功');
    } catch(e) {}
    this.value = '';
});

async function save() {
    await API.post('/admin/api/settings.php', {
        settings: {
            font_family: document.getElementById('fontFamily').value.trim(),
            font_url: document.getElementById('fontUrl').value.trim(),
        }
    });
    Toast.success('字体设置已保存');
}

load();
</script>

<?php admin_footer(); ?>
