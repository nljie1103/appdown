<?php
/**
 * 分发首页模板
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();
$tenant = require_tenant_context();
$tenantPublicPath = tenant_public_path($tenant['slug']);

admin_header('页面模板', 'templates');
?>

<div class="page-header">
    <h1>分发首页模板</h1>
    <p style="color:var(--text-secondary);margin-top:6px;">只改变公开下载页的视觉布局，不影响 iOS / Android 安装页模板、应用数据、下载链接和轮播内容。</p>
</div>

<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px;">
        <div>
            <h3 style="margin:0 0 5px;">选择模板</h3>
            <div style="color:var(--text-secondary);font-size:.9em;">切换后前台配置缓存会立即刷新。</div>
        </div>
        <a class="btn btn-outline" href="<?= htmlspecialchars($tenantPublicPath) ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> 打开前台预览</a>
    </div>
    <div id="templateGrid" class="template-grid">
        <div style="color:var(--text-secondary);padding:30px 0;">正在加载模板...</div>
    </div>
</div>

<style>
.template-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
.template-card{position:relative;border:2px solid var(--border,#e5e7eb);border-radius:16px;padding:14px;background:var(--card-bg,#fff);cursor:pointer;transition:.2s ease;overflow:hidden}
.template-card:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(0,0,0,.08)}
.template-card.active{border-color:#007AFF;box-shadow:0 0 0 3px rgba(0,122,255,.1)}
.template-preview{height:130px;border-radius:12px;overflow:hidden;margin-bottom:13px;position:relative;border:1px solid rgba(0,0,0,.06)}
.template-preview .mini-head{width:34%;height:10px;border-radius:999px;position:absolute;left:12%;top:18%}
.template-preview .mini-tabs{width:68%;height:13px;border-radius:999px;position:absolute;left:16%;top:34%}
.template-preview .mini-btn{width:32%;height:18px;border-radius:999px;position:absolute;left:34%;top:54%}
.template-preview .mini-shot{width:31%;height:40%;border-radius:8px;position:absolute;left:34.5%;top:70%;transform:translateY(-50%)}
.template-preview.classic{background:linear-gradient(135deg,#eaf5ff,#fff0f6)}
.template-preview.classic span{background:rgba(255,255,255,.92);box-shadow:0 2px 8px rgba(0,0,0,.08)}
.template-preview.classic .mini-btn{background:#2188ff}
.template-preview.glass{background:linear-gradient(135deg,#dff5ff,#f4e6ff,#ffeeda)}
.template-preview.glass span{background:rgba(255,255,255,.58);backdrop-filter:blur(5px);box-shadow:0 5px 15px rgba(50,70,110,.12)}
.template-preview.glass .mini-btn{background:linear-gradient(135deg,#2d8cff,#8057ff)}
.template-preview.minimal{background:#fff}
.template-preview.minimal span{background:#f6f6f6;border:1px solid #e7e7e7;box-shadow:none}
.template-preview.minimal .mini-head{background:#111}.template-preview.minimal .mini-btn{background:#111}
.template-preview.midnight{background:radial-gradient(circle at 25% 20%,#17325c,#090d17 55%)}
.template-preview.midnight span{background:#172033;border:1px solid #28344c}.template-preview.midnight .mini-head{background:#eaf0ff}.template-preview.midnight .mini-btn{background:#3b82f6}
.template-preview.aurora{background:linear-gradient(135deg,#c9f5ff,#e0d1ff,#ffd6e8,#ffe7bc)}
.template-preview.aurora span{background:rgba(255,255,255,.67);box-shadow:0 5px 14px rgba(83,57,130,.12)}
.template-preview.aurora .mini-btn{background:linear-gradient(90deg,#00a7f5,#8758ff,#ff4e91)}
.template-name{font-weight:700;font-size:1.02em;margin-bottom:5px}
.template-desc{color:var(--text-secondary);font-size:.87em;line-height:1.55;min-height:42px}
.template-badge{display:none;position:absolute;top:22px;right:22px;background:#007AFF;color:white;border-radius:999px;padding:4px 9px;font-size:.72em;font-weight:700}
.template-card.active .template-badge{display:block}
</style>

<script>
let currentTemplate = 'classic';

function previewMarkup(key) {
    return `<div class="template-preview ${key}">
        <span class="mini-head"></span><span class="mini-tabs"></span><span class="mini-btn"></span><span class="mini-shot"></span>
    </div>`;
}

async function loadTemplates() {
    const data = await API.get('/admin/api/templates.php');
    currentTemplate = data.current || 'classic';
    const grid = document.getElementById('templateGrid');
    grid.innerHTML = Object.entries(data.templates || {}).map(([key, item]) => `
        <div class="template-card ${key === currentTemplate ? 'active' : ''}" data-template="${key}" onclick="selectTemplate('${key}')">
            <div class="template-badge"><i class="fas fa-check"></i> 当前</div>
            ${previewMarkup(key)}
            <div class="template-name">${escapeHtml(item.name || key)}</div>
            <div class="template-desc">${escapeHtml(item.description || '')}</div>
        </div>
    `).join('');
}

async function selectTemplate(key) {
    if (key === currentTemplate) return;
    await API.post('/admin/api/templates.php', { template: key });
    currentTemplate = key;
    document.querySelectorAll('.template-card').forEach(el => el.classList.toggle('active', el.dataset.template === key));
    AlertModal.success('模板已切换', '刷新前台页面即可看到新的分发首页样式');
}

function escapeHtml(value) {
    const d = document.createElement('div');
    d.textContent = String(value ?? '');
    return d.innerHTML;
}

loadTemplates();
</script>

<?php admin_footer(); ?>
