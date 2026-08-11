<?php
/**
 * 分发首页模板
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();
admin_header('页面模板', 'templates');
?>

<div class="page-header">
    <div>
        <h1>分发首页模板</h1>
        <p style="color:var(--text-secondary);margin-top:6px;">模板 2.0 会真实改变 Hero、应用选择、下载区、截图、统计与特色卡片布局，不再只是换颜色。</p>
    </div>
</div>

<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px;">
        <div><h3 style="margin:0 0 5px;">选择布局</h3><div style="color:var(--text-secondary);font-size:.9em;">切换后前台配置缓存立即刷新；应用数据和下载链路保持不变。</div></div>
        <a class="btn btn-outline" href="/" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> 打开前台预览</a>
    </div>
    <div id="templateGrid" class="template-grid"><div style="color:var(--text-secondary);padding:30px 0;">正在加载模板...</div></div>
</div>

<style>
.template-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}
.template-card{position:relative;border:1px solid var(--border,#e5e7eb);border-radius:16px;padding:12px;background:var(--card-bg,#fff);cursor:pointer;transition:.18s ease;overflow:hidden}
.template-card:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(0,0,0,.08);border-color:#b9c2d0}.template-card.active{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.10)}
.template-preview{height:154px;border-radius:12px;overflow:hidden;margin-bottom:13px;position:relative;border:1px solid rgba(0,0,0,.06);padding:9px;display:grid;gap:6px}
.tp{border-radius:5px;background:rgba(255,255,255,.88);border:1px solid rgba(0,0,0,.05)}.tp.dark{background:#182033;border-color:#27334a}.tp.accent{background:#4f46e5;border:0}
.template-preview.classic{background:linear-gradient(135deg,#eaf5ff,#fff0f6);grid-template-rows:30px 20px 1fr}.classic .hero{width:45%;justify-self:center}.classic .nav{width:72%;justify-self:center}.classic .main{width:45%;justify-self:center}
.template-preview.glass{background:linear-gradient(135deg,#dff5ff,#f4e6ff,#ffeeda);grid-template-rows:52px 20px 1fr}.glass .hero{width:75%;justify-self:center;backdrop-filter:blur(4px);background:rgba(255,255,255,.55)}.glass .nav{width:64%;justify-self:center;border-radius:999px}.glass .main{width:48%;justify-self:center}
.template-preview.minimal{background:#fff;grid-template-columns:1fr 30%;grid-template-rows:52px 18px 1fr}.minimal .hero{grid-column:1;background:#111}.minimal .aside{grid-column:2}.minimal .nav{grid-column:1/-1;border-radius:0;border-width:0 0 1px}.minimal .main{grid-column:1/-1;background:#fafafa}
.template-preview.midnight{background:#070a12;grid-template-columns:31% 1fr}.midnight .rail{grid-row:1/5;background:#101827;border-color:#25314a}.midnight .hero{grid-column:2;background:#152038}.midnight .nav{grid-column:2;background:#111827}.midnight .main{grid-column:2;grid-row:3/5;background:#0d1321}
.template-preview.aurora{background:linear-gradient(135deg,#c9f5ff,#e0d1ff,#ffd6e8,#ffe7bc);grid-template-rows:58px 19px 1fr}.aurora .hero{width:86%;justify-self:center;background:rgba(255,255,255,.55)}.aurora .nav{width:55%;justify-self:center;border-radius:999px}.aurora .main{width:72%;justify-self:center;background:rgba(255,255,255,.62)}
.template-preview.store{background:#f5f5f7;grid-template-columns:27% 1fr;grid-template-rows:43px 1fr}.store .hero{grid-column:1/-1;background:#fff}.store .rail{background:#fff}.store .main{background:#fff}
.template-preview.bento{background:#eef0f4;grid-template-columns:1.3fr .7fr;grid-template-rows:48px 1fr 1fr}.bento .hero{background:#fff}.bento .stats{background:#111827}.bento .main{grid-column:1/-1;background:#fff}.bento .aside{background:#fff}
.template-preview.split{background:#fff;grid-template-columns:37% 1fr}.split .rail{grid-row:1/5;background:#0f172a;border:0}.split .hero{grid-column:2;background:#f8fafc}.split .main{grid-column:2;grid-row:2/5;background:#fff}
.template-preview.mobile{background:#e9ebef;place-items:center}.mobile .phone{width:54%;height:100%;background:#fff;border-radius:15px;display:grid;grid-template-rows:34px 18px 1fr;gap:5px;padding:7px}.mobile .phone>*{background:#f4f5f7;border-radius:4px}.mobile .phone .main{background:#fff;border:1px solid #e6e8ec}
.template-name{font-weight:700;font-size:1.02em;margin-bottom:5px}.template-desc{color:var(--text-secondary);font-size:.86em;line-height:1.55;min-height:42px}.template-meta{font-size:.75em;color:#7c8492;margin-top:8px}.template-badge{display:none;position:absolute;top:19px;right:19px;background:#4f46e5;color:white;border-radius:999px;padding:4px 9px;font-size:.72em;font-weight:700;z-index:3}.template-card.active .template-badge{display:block}
</style>

<script>
let currentTemplate='classic';
function previewMarkup(key){
 const map={
  classic:'<i class="tp hero"></i><i class="tp nav"></i><i class="tp main"></i>',
  glass:'<i class="tp hero"></i><i class="tp nav"></i><i class="tp main"></i>',
  minimal:'<i class="tp hero"></i><i class="tp aside"></i><i class="tp nav"></i><i class="tp main"></i>',
  midnight:'<i class="tp rail dark"></i><i class="tp hero dark"></i><i class="tp nav dark"></i><i class="tp main dark"></i>',
  aurora:'<i class="tp hero"></i><i class="tp nav"></i><i class="tp main"></i>',
  store:'<i class="tp hero"></i><i class="tp rail"></i><i class="tp main"></i>',
  bento:'<i class="tp hero"></i><i class="tp stats dark"></i><i class="tp main"></i><i class="tp aside"></i>',
  split:'<i class="tp rail dark"></i><i class="tp hero"></i><i class="tp main"></i>',
  mobile:'<div class="phone"><i></i><i></i><i class="main"></i></div>'
 };
 return `<div class="template-preview ${key}">${map[key]||map.classic}</div>`;
}
async function loadTemplates(){
 const data=await API.get('/admin/api/templates.php');currentTemplate=data.current||'classic';const grid=document.getElementById('templateGrid');
 grid.innerHTML=Object.entries(data.templates||{}).map(([key,item])=>`<div class="template-card ${key===currentTemplate?'active':''}" data-template="${key}" onclick="selectTemplate('${key}')"><div class="template-badge"><i class="fas fa-check"></i> 当前</div>${previewMarkup(key)}<div class="template-name">${escapeHtml(item.name||key)}</div><div class="template-desc">${escapeHtml(item.description||'')}</div><div class="template-meta">${escapeHtml(item.preview||item.layout||'')}</div></div>`).join('');
}
async function selectTemplate(key){if(key===currentTemplate)return;await API.post('/admin/api/templates.php',{template:key});currentTemplate=key;document.querySelectorAll('.template-card').forEach(el=>el.classList.toggle('active',el.dataset.template===key));AlertModal.success('模板已切换','前台刷新后即可看到新的结构与组件布局');}
function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML}loadTemplates();
</script>
<?php admin_footer(); ?>
