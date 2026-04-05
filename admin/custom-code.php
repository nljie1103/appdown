<?php
/**
 * 自定义代码注入页 — 含内置特效管理
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('自定义代码', 'code');
?>

<div class="page-header"><h1>自定义代码</h1></div>

<div style="background:#fff3cd;color:#856404;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.9em;">
    <i class="fas fa-exclamation-triangle"></i> 错误的代码可能导致网站无法正常显示，请谨慎修改。适合添加统计代码（如百度统计、Google Analytics）等。
</div>

<!-- 内置特效预设 -->
<div class="card">
    <h3><i class="fas fa-magic"></i> 内置特效</h3>
    <p style="color:var(--text-secondary);font-size:0.85em;margin-bottom:14px;">点击卡片启用/禁用，启用后可调节参数。特效独立于下方代码区域保存。</p>
    <div id="presetGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;"></div>
</div>

<!-- 特效参数面板 -->
<div class="card" id="effectParams" style="display:none;">
    <h3 id="effectParamsTitle">特效参数</h3>
    <div id="effectParamsBody"></div>
    <button class="btn btn-primary btn-sm" onclick="saveEffects()" style="margin-top:12px;"><i class="fas fa-save"></i> 保存特效设置</button>
</div>

<div class="card">
    <h3>Head CSS (在 &lt;/head&gt; 前注入)</h3>
    <textarea class="form-control" id="head_css" rows="6" style="font-family:monospace;" placeholder="/* 自定义CSS */"></textarea>
    <button class="btn btn-primary btn-sm" onclick="save('head_css')" style="margin-top:10px;"><i class="fas fa-save"></i> 保存</button>
</div>

<div class="card">
    <h3>Head JS (在 &lt;/head&gt; 前注入)</h3>
    <textarea class="form-control" id="head_js" rows="6" style="font-family:monospace;" placeholder="// 自定义JavaScript"></textarea>
    <button class="btn btn-primary btn-sm" onclick="save('head_js')" style="margin-top:10px;"><i class="fas fa-save"></i> 保存</button>
</div>

<div class="card">
    <h3>Footer CSS (在 &lt;/body&gt; 前注入)</h3>
    <textarea class="form-control" id="footer_css" rows="6" style="font-family:monospace;" placeholder="/* 自定义CSS */"></textarea>
    <button class="btn btn-primary btn-sm" onclick="save('footer_css')" style="margin-top:10px;"><i class="fas fa-save"></i> 保存</button>
</div>

<div class="card">
    <h3>Footer JS (在 &lt;/body&gt; 前注入)</h3>
    <textarea class="form-control" id="footer_js" rows="6" style="font-family:monospace;" placeholder="// 自定义JavaScript (如统计代码)"></textarea>
    <button class="btn btn-primary btn-sm" onclick="save('footer_js')" style="margin-top:10px;"><i class="fas fa-save"></i> 保存</button>
</div>

<style>
.slider-row { display: flex; align-items: center; gap: 12px; margin: 8px 0; }
.slider-row label { width: 80px; font-size: 0.9em; font-weight: 500; flex-shrink: 0; }
.slider-row input[type=range] { flex: 1; accent-color: var(--primary); }
.slider-row .slider-val { width: 36px; text-align: center; font-size: 0.85em; color: var(--text-secondary); font-weight: 600; }
.festival-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(220px,1fr)); gap: 6px; margin: 10px 0; }
.festival-item { display: flex; align-items: center; gap: 8px; padding: 6px 10px; background: var(--bg); border-radius: 6px; font-size: 0.88em; cursor: pointer; user-select: none; }
.festival-item input { accent-color: var(--primary); }
.festival-item:hover { background: var(--border); }
</style>

<script>
// ================== 特效定义 ==================
const EFFECTS = {
    sakura: { name: '全屏樱花', icon: '🌸', color: '#FFB7C5', desc: '飘落的樱花瓣特效',
        params: [
            { key: 'count', label: '数量', min: 5, max: 100, default: 35 },
            { key: 'size', label: '大小', min: 2, max: 20, default: 8 },
            { key: 'speed', label: '速度', min: 10, max: 100, default: 50 },
        ]
    },
    snow: { name: '全屏雪花', icon: '❄️', color: '#87CEEB', desc: '飘落的雪花特效',
        params: [
            { key: 'count', label: '数量', min: 10, max: 200, default: 60 },
            { key: 'size', label: '大小', min: 1, max: 10, default: 4 },
            { key: 'speed', label: '速度', min: 10, max: 100, default: 50 },
        ]
    },
    lantern: { name: '节日灯笼', icon: '🏮', color: '#FF4500', desc: '页面顶部悬挂灯笼',
        params: [
            { key: 'size', label: '大小', min: 20, max: 100, default: 50 },
        ]
    },
    particles: { name: '粒子背景', icon: '✨', color: '#3498DB', desc: '动态粒子连线效果',
        params: [
            { key: 'count', label: '数量', min: 10, max: 150, default: 50 },
            { key: 'speed', label: '速度', min: 10, max: 100, default: 40 },
            { key: 'opacity', label: '透明度', min: 10, max: 100, default: 40 },
        ]
    },
    cursor: { name: '鼠标跟随', icon: '🌟', color: '#F39C12', desc: '鼠标移动时星星拖尾',
        params: [
            { key: 'size', label: '大小', min: 2, max: 15, default: 6 },
        ]
    },
    ribbon: { name: '彩带背景', icon: '🎀', color: '#E91E63', desc: '点击刷新彩带背景',
        params: [
            { key: 'opacity', label: '透明度', min: 10, max: 100, default: 60 },
        ]
    },
    grayscale: { name: '全站灰色', icon: '🕯️', color: '#888', desc: '纪念/悼念模式',
        params: []
    },
    contextmenu: { name: '右键美化', icon: '🖱️', color: '#2ECC71', desc: '自定义右键菜单',
        params: []
    },
    nosource: { name: '禁止查看源码', icon: '🔒', color: '#E74C3C', desc: '禁用F12/右键查看源码',
        params: []
    },
    bgmusic: { name: '背景音乐', icon: '🎵', color: '#9B59B6', desc: '网站背景音乐播放',
        params: [
            { key: 'volume', label: '音量', min: 5, max: 100, default: 30 },
        ],
        extra: 'music_url'  // 需要额外输入框
    },
    welcome: { name: '节日欢迎弹窗', icon: '🎉', color: '#FF6B6B', desc: '节日自动弹窗祝福',
        params: [],
        extra: 'festival'   // 需要节日选择面板
    },
};

// 中国节日列表（公历 + 农历标注）
const FESTIVALS = [
    { id: 'newyear', name: '元旦', date: '01-01', greeting: '新年快乐！愿新的一年万事如意，阖家幸福！🎊' },
    { id: 'valentine', name: '情人节', date: '02-14', greeting: '情人节快乐！愿有情人终成眷属！💕' },
    { id: 'women', name: '妇女节', date: '03-08', greeting: '妇女节快乐！致敬每一位伟大的女性！🌷' },
    { id: 'arbor', name: '植树节', date: '03-12', greeting: '植树节快乐！让我们一起守护绿色家园！🌳' },
    { id: 'fool', name: '愚人节', date: '04-01', greeting: '愚人节快乐！今天的玩笑要适可而止哦！😄' },
    { id: 'qingming', name: '清明节', date: '04-05', greeting: '清明时节，缅怀先人，珍惜当下。🕊️' },
    { id: 'labor', name: '劳动节', date: '05-01', greeting: '劳动节快乐！向每一位劳动者致敬！💪' },
    { id: 'youth', name: '青年节', date: '05-04', greeting: '五四青年节快乐！青春正当时，奋斗不止步！🔥' },
    { id: 'mother', name: '母亲节', date: '05-second-sun', greeting: '母亲节快乐！感恩母亲的无私奉献！❤️', dynamic: true },
    { id: 'children', name: '儿童节', date: '06-01', greeting: '六一儿童节快乐！愿每个人心中都住着一个快乐的孩子！🎈' },
    { id: 'dragon', name: '端午节', date: 'lunar-05-05', greeting: '端午节安康！粽叶飘香，龙舟竞渡！🐉', lunar: true },
    { id: 'cpc', name: '建党节', date: '07-01', greeting: '七一建党节，不忘初心，牢记使命！🇨🇳' },
    { id: 'army', name: '建军节', date: '08-01', greeting: '八一建军节，致敬最可爱的人！🎖️' },
    { id: 'qixi', name: '七夕节', date: 'lunar-07-07', greeting: '七夕节快乐！愿天下有情人终成眷属！🌹', lunar: true },
    { id: 'teacher', name: '教师节', date: '09-10', greeting: '教师节快乐！感恩师恩，桃李满天下！📚' },
    { id: 'mid_autumn', name: '中秋节', date: 'lunar-08-15', greeting: '中秋节快乐！月圆人团圆，幸福美满！🥮🌕', lunar: true },
    { id: 'national', name: '国庆节', date: '10-01', greeting: '国庆节快乐！祝伟大祖国繁荣昌盛！🇨🇳🎆' },
    { id: 'chongyang', name: '重阳节', date: 'lunar-09-09', greeting: '重阳节快乐！敬老爱老，登高望远！🏔️', lunar: true },
    { id: 'spring', name: '春节', date: 'lunar-01-01', greeting: '新春快乐！恭喜发财，大吉大利！🧧🎆', lunar: true },
    { id: 'lantern_fest', name: '元宵节', date: 'lunar-01-15', greeting: '元宵节快乐！花灯璀璨，团团圆圆！🏮🎊', lunar: true },
    { id: 'christmas', name: '圣诞节', date: '12-25', greeting: '圣诞快乐！Merry Christmas！🎄🎅' },
    { id: 'nye', name: '除夕', date: 'lunar-12-30', greeting: '除夕夜快乐！辞旧迎新，阖家团圆！🎇', lunar: true },
];

// 当前特效配置
let effectsConfig = {};

// ================== 渲染 ==================
function renderPresets() {
    const grid = document.getElementById('presetGrid');
    grid.innerHTML = '';
    for (const [id, ef] of Object.entries(EFFECTS)) {
        const isActive = !!effectsConfig[id]?.enabled;
        const card = document.createElement('div');
        card.style.cssText = `padding:14px;border-radius:10px;border:2px solid ${isActive ? ef.color : '#e5e5e5'};background:${isActive ? ef.color + '10' : '#fafafa'};cursor:pointer;transition:all 0.2s;text-align:center;`;
        card.innerHTML = `
            <div style="font-size:1.8em;margin-bottom:6px;">${ef.icon}</div>
            <div style="font-weight:600;font-size:0.9em;color:#333;">${ef.name}</div>
            <div style="font-size:0.75em;color:#999;margin-top:4px;">${ef.desc}</div>
            <div style="margin-top:8px;font-size:0.75em;font-weight:600;color:${isActive ? '#27ae60' : '#ccc'};">${isActive ? '● 已启用' : '○ 未启用'}</div>
        `;
        card.onmouseenter = () => { card.style.borderColor = ef.color; card.style.transform = 'translateY(-2px)'; };
        card.onmouseleave = () => { card.style.borderColor = isActive ? ef.color : '#e5e5e5'; card.style.transform = ''; };
        card.onclick = () => toggleEffect(id);
        grid.appendChild(card);
    }
    renderActiveParams();
}

function toggleEffect(id) {
    if (!effectsConfig[id]) effectsConfig[id] = { enabled: false, params: {} };
    effectsConfig[id].enabled = !effectsConfig[id].enabled;

    // 初始化默认参数
    if (effectsConfig[id].enabled) {
        const ef = EFFECTS[id];
        if (ef.params) {
            ef.params.forEach(p => {
                if (effectsConfig[id].params[p.key] === undefined) {
                    effectsConfig[id].params[p.key] = p.default;
                }
            });
        }
        if (id === 'welcome' && !effectsConfig[id].festivals) {
            effectsConfig[id].festivals = {};
            FESTIVALS.forEach(f => { effectsConfig[id].festivals[f.id] = { enabled: true, greeting: f.greeting }; });
        }
        if (id === 'bgmusic' && !effectsConfig[id].music_url) {
            effectsConfig[id].music_url = '';
        }
    }
    saveEffects();
}

function renderActiveParams() {
    const panel = document.getElementById('effectParams');
    const body = document.getElementById('effectParamsBody');
    const activeEffects = Object.entries(effectsConfig).filter(([id, c]) => c.enabled && EFFECTS[id]);

    if (!activeEffects.length) {
        panel.style.display = 'none';
        return;
    }
    panel.style.display = '';
    document.getElementById('effectParamsTitle').textContent = '特效参数设置';
    body.innerHTML = '';

    activeEffects.forEach(([id, cfg]) => {
        const ef = EFFECTS[id];
        const section = document.createElement('div');
        section.style.cssText = 'margin-bottom:20px;padding:14px;background:var(--bg);border-radius:8px;';
        let html = `<div style="font-weight:600;font-size:0.95em;margin-bottom:10px;">${ef.icon} ${ef.name}</div>`;

        // 参数滑块
        ef.params.forEach(p => {
            const val = cfg.params?.[p.key] ?? p.default;
            html += `<div class="slider-row">
                <label>${p.label}</label>
                <input type="range" min="${p.min}" max="${p.max}" value="${val}"
                    oninput="updateParam('${id}','${p.key}',this.value);this.nextElementSibling.textContent=this.value">
                <span class="slider-val">${val}</span>
            </div>`;
        });

        // 背景音乐链接
        if (ef.extra === 'music_url') {
            html += `<div class="form-group" style="margin-top:10px;">
                <label style="font-size:0.9em;font-weight:500;"><span style="color:#e74c3c;">*</span> 音乐链接</label>
                <input type="text" class="form-control" value="${escapeHTML(cfg.music_url || '')}"
                    placeholder="https://example.com/music.mp3"
                    onchange="effectsConfig['${id}'].music_url=this.value">
            </div>`;
        }

        // 节日选择
        if (ef.extra === 'festival') {
            html += `<div style="margin-top:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <label style="font-size:0.9em;font-weight:500;">选择触发节日</label>
                    <label style="font-size:0.8em;cursor:pointer;color:var(--primary);" onclick="toggleAllFestivals('${id}')">全选/取消</label>
                </div>
                <div class="festival-grid">`;
            FESTIVALS.forEach(f => {
                const fc = cfg.festivals?.[f.id];
                const checked = fc?.enabled !== false ? 'checked' : '';
                const lunar = f.lunar ? ' <small style="color:var(--text-secondary);">(农历)</small>' : '';
                html += `<label class="festival-item">
                    <input type="checkbox" ${checked} onchange="effectsConfig['${id}'].festivals['${f.id}'].enabled=this.checked">
                    ${f.name}${lunar}
                </label>`;
            });
            html += `</div>
                <div style="margin-top:10px;">
                    <label style="font-size:0.9em;font-weight:500;display:block;margin-bottom:6px;">自定义祝福内容 <small style="color:var(--text-secondary);">(点击展开编辑)</small></label>
                    <details>
                        <summary style="cursor:pointer;color:var(--primary);font-size:0.85em;margin-bottom:8px;">展开编辑各节日祝福语</summary>
                        <div style="max-height:300px;overflow-y:auto;">`;
            FESTIVALS.forEach(f => {
                const greeting = cfg.festivals?.[f.id]?.greeting ?? f.greeting;
                html += `<div style="margin:6px 0;display:flex;align-items:center;gap:8px;">
                    <span style="width:70px;font-size:0.85em;flex-shrink:0;">${f.name}</span>
                    <input type="text" class="form-control" value="${escapeHTML(greeting)}" style="font-size:0.85em;"
                        onchange="effectsConfig['${id}'].festivals['${f.id}'].greeting=this.value">
                </div>`;
            });
            html += `</div></details></div>`;
        }

        section.innerHTML = html;
        body.appendChild(section);
    });
}

function updateParam(effectId, paramKey, value) {
    if (!effectsConfig[effectId]) return;
    if (!effectsConfig[effectId].params) effectsConfig[effectId].params = {};
    effectsConfig[effectId].params[paramKey] = parseInt(value);
}

function toggleAllFestivals(effectId) {
    const cfg = effectsConfig[effectId];
    if (!cfg?.festivals) return;
    const allEnabled = Object.values(cfg.festivals).every(f => f.enabled !== false);
    Object.keys(cfg.festivals).forEach(fid => { cfg.festivals[fid].enabled = !allEnabled; });
    renderActiveParams();
}

// ================== 加载/保存 ==================
async function load() {
    // 加载自定义代码
    const data = await API.get('/admin/api/custom-code.php');
    ['head_css', 'head_js', 'footer_css', 'footer_js'].forEach(pos => {
        const el = document.getElementById(pos);
        if (el) el.value = data[pos] || '';
    });

    // 清理旧的preset代码（迁移到新系统）
    ['head_css', 'head_js', 'footer_css', 'footer_js'].forEach(pos => {
        const el = document.getElementById(pos);
        if (el && el.value.includes('[preset:')) {
            const lines = el.value.split('\n');
            const cleaned = [];
            let skip = false;
            for (const line of lines) {
                if (line.includes('[preset:')) { skip = true; continue; }
                if (skip && line.trim() === '') { skip = false; continue; }
                if (!skip) cleaned.push(line);
            }
            const newVal = cleaned.join('\n').trim();
            if (newVal !== el.value.trim()) {
                el.value = newVal;
                save(pos, true);
            }
        }
    });

    // 加载特效配置
    const settings = await API.get('/admin/api/settings.php');
    try {
        effectsConfig = JSON.parse(settings.effects_config || '{}');
    } catch(e) {
        effectsConfig = {};
    }
    renderPresets();
}

async function save(position, silent) {
    const code = document.getElementById(position).value;
    await API.post('/admin/api/custom-code.php', { position, code });
    if (!silent) AlertModal.success('保存成功', '自定义代码已保存');
}

async function saveEffects() {
    await API.post('/admin/api/settings.php', {
        settings: { effects_config: JSON.stringify(effectsConfig) }
    });
    renderPresets();
}

load();
</script>

<?php admin_footer(); ?>
