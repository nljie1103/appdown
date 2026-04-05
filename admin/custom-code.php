<?php
/**
 * 自定义代码注入页
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
    <h3><i class="fas fa-magic"></i> 内置特效 <small style="color:#999;font-weight:400;">（点击启用/禁用，自动写入对应代码区域）</small></h3>
    <div id="presetGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:14px;"></div>
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

<script>
// ==================== 内置特效预设 ====================
const PRESETS = [
    {
        id: 'sakura', name: '全屏樱花', icon: '🌸', color: '#FFB7C5',
        desc: '飘落的樱花瓣特效',
        target: 'footer_js',
        code: `// [preset:sakura] 全屏樱花特效
(function(){const c=document.createElement('canvas');c.id='sakura-canvas';c.style.cssText='position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999';document.body.appendChild(c);const x=c.getContext('2d');let w,h;function resize(){w=c.width=innerWidth;h=c.height=innerHeight}resize();addEventListener('resize',resize);const petals=[];function Petal(){this.x=Math.random()*w;this.y=-10;this.s=Math.random()*8+4;this.r=Math.random()*Math.PI*2;this.vx=Math.random()*2-1;this.vy=Math.random()*1+1;this.vr=Math.random()*0.02-0.01;this.a=Math.random()*0.5+0.5}function draw(){x.clearRect(0,0,w,h);petals.forEach((p,i)=>{p.x+=p.vx+Math.sin(p.r)*0.5;p.y+=p.vy;p.r+=p.vr;if(p.y>h+10){petals[i]=new Petal()}x.save();x.translate(p.x,p.y);x.rotate(p.r);x.globalAlpha=p.a;x.fillStyle='#FFB7C5';x.beginPath();x.ellipse(0,0,p.s,p.s/2,0,0,Math.PI*2);x.fill();x.restore()});requestAnimationFrame(draw)}for(let i=0;i<35;i++){const p=new Petal();p.y=Math.random()*h;petals.push(p)}draw()})();`
    },
    {
        id: 'snow', name: '全屏雪花', icon: '❄️', color: '#87CEEB',
        desc: '飘落的雪花特效',
        target: 'footer_js',
        code: `// [preset:snow] 全屏雪花特效
(function(){const c=document.createElement('canvas');c.id='snow-canvas';c.style.cssText='position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999';document.body.appendChild(c);const x=c.getContext('2d');let w,h;function resize(){w=c.width=innerWidth;h=c.height=innerHeight}resize();addEventListener('resize',resize);const flakes=[];function Flake(){this.x=Math.random()*w;this.y=-5;this.r=Math.random()*3+1;this.vx=Math.random()*1-0.5;this.vy=Math.random()*1.5+0.5;this.a=Math.random()*0.6+0.4}function draw(){x.clearRect(0,0,w,h);flakes.forEach((f,i)=>{f.x+=f.vx+Math.sin(Date.now()*0.001+i)*0.3;f.y+=f.vy;if(f.y>h+5){flakes[i]=new Flake()}x.beginPath();x.arc(f.x,f.y,f.r,0,Math.PI*2);x.fillStyle='rgba(255,255,255,'+f.a+')';x.fill()});requestAnimationFrame(draw)}for(let i=0;i<60;i++){const f=new Flake();f.y=Math.random()*h;flakes.push(f)}draw()})();`
    },
    {
        id: 'grayscale', name: '全站灰色', icon: '🕯️', color: '#888',
        desc: '纪念/悼念模式，全站变灰',
        target: 'head_css',
        code: `/* [preset:grayscale] 全站灰色（纪念/悼念模式） */
html { filter: grayscale(100%); -webkit-filter: grayscale(100%); }`
    },
    {
        id: 'lantern', name: '节日灯笼', icon: '🏮', color: '#FF4500',
        desc: '页面顶部悬挂灯笼',
        target: 'footer_js',
        code: `// [preset:lantern] 节日灯笼
(function(){const d=document.createElement('div');d.id='lanterns';d.innerHTML='<div style="position:fixed;top:-10px;left:15%;z-index:9998;animation:swing 3s ease-in-out infinite;transform-origin:top center;"><div style="width:50px;height:60px;background:radial-gradient(circle,#ff6b35,#e63900);border-radius:50% 50% 50% 50%/60% 60% 40% 40%;box-shadow:0 0 20px rgba(255,100,0,0.5);display:flex;align-items:center;justify-content:center;color:#ffe0b2;font-size:14px;font-weight:bold;">福</div><div style="width:30px;height:15px;background:#ffd700;margin:0 auto;border-radius:0 0 4px 4px;"></div></div><div style="position:fixed;top:-10px;right:15%;z-index:9998;animation:swing 3s ease-in-out infinite 0.5s;transform-origin:top center;"><div style="width:50px;height:60px;background:radial-gradient(circle,#ff6b35,#e63900);border-radius:50% 50% 50% 50%/60% 60% 40% 40%;box-shadow:0 0 20px rgba(255,100,0,0.5);display:flex;align-items:center;justify-content:center;color:#ffe0b2;font-size:14px;font-weight:bold;">春</div><div style="width:30px;height:15px;background:#ffd700;margin:0 auto;border-radius:0 0 4px 4px;"></div></div>';const s=document.createElement('style');s.textContent='@keyframes swing{0%,100%{transform:rotate(-5deg)}50%{transform:rotate(5deg)}}';document.head.appendChild(s);document.body.appendChild(d)})();`
    },
    {
        id: 'welcome', name: '节日欢迎弹窗', icon: '🎉', color: '#FF6B6B',
        desc: '进入网站弹出节日欢迎语',
        target: 'footer_js',
        code: `// [preset:welcome] 节日欢迎弹窗（修改文字后使用）
(function(){if(sessionStorage.getItem('welcomed'))return;sessionStorage.setItem('welcomed','1');const o=document.createElement('div');o.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;display:flex;justify-content:center;align-items:center;opacity:0;transition:opacity 0.3s';const d=document.createElement('div');d.style.cssText='background:#fff;padding:40px;border-radius:16px;text-align:center;max-width:380px;transform:scale(0.8);transition:transform 0.3s';d.innerHTML='<div style="font-size:3em;margin-bottom:10px;">🎊</div><h2 style="margin-bottom:12px;color:#333;">欢迎访问！</h2><p style="color:#666;line-height:1.8;margin-bottom:20px;">祝您节日快乐！感谢您的来访。</p><button style="padding:10px 30px;background:#007AFF;color:#fff;border:none;border-radius:8px;font-size:1em;cursor:pointer;" onclick="this.closest(\\'div\\').parentElement.remove()">知道了</button>';o.appendChild(d);document.body.appendChild(o);setTimeout(()=>{o.style.opacity='1';d.style.transform='scale(1)'},100)})();`
    },
    {
        id: 'bgmusic', name: '背景音乐', icon: '🎵', color: '#9B59B6',
        desc: '可显示/隐藏音乐播放控件',
        target: 'footer_js',
        code: `// [preset:bgmusic] 背景音乐（请修改音乐URL）
(function(){const url='https://example.com/music.mp3';const a=new Audio(url);a.loop=true;a.volume=0.3;const btn=document.createElement('div');btn.style.cssText='position:fixed;bottom:20px;right:20px;width:44px;height:44px;background:#007AFF;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:9999;box-shadow:0 2px 10px rgba(0,0,0,0.2);transition:transform 0.3s;color:#fff;font-size:18px;';btn.innerHTML='🎵';btn.title='点击播放/暂停';let playing=false;btn.onclick=function(){if(playing){a.pause();btn.style.opacity='0.6';btn.style.animation='none'}else{a.play().catch(()=>{});btn.style.opacity='1';btn.style.animation='spin-music 3s linear infinite'}playing=!playing};const s=document.createElement('style');s.textContent='@keyframes spin-music{from{transform:rotate(0)}to{transform:rotate(360deg)}}';document.head.appendChild(s);document.body.appendChild(btn)})();`
    },
    {
        id: 'contextmenu', name: '右键美化', icon: '🖱️', color: '#2ECC71',
        desc: '自定义右键菜单样式',
        target: 'footer_js',
        code: `// [preset:contextmenu] 右键菜单美化
(function(){const menu=document.createElement('div');menu.id='custom-ctx';menu.style.cssText='display:none;position:fixed;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.15);padding:6px 0;z-index:99999;min-width:180px;font-size:14px;';const items=[{icon:'🏠',text:'返回首页',action:()=>location.href='/'},{icon:'↑',text:'回到顶部',action:()=>scrollTo({top:0,behavior:'smooth'})},{icon:'←',text:'返回上页',action:()=>history.back()},{icon:'🔄',text:'刷新页面',action:()=>location.reload()}];items.forEach(it=>{const d=document.createElement('div');d.style.cssText='padding:10px 16px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background 0.15s;';d.innerHTML=it.icon+' '+it.text;d.onmouseenter=()=>d.style.background='#f5f5f5';d.onmouseleave=()=>d.style.background='';d.onclick=()=>{menu.style.display='none';it.action()};menu.appendChild(d)});document.body.appendChild(menu);document.addEventListener('contextmenu',e=>{e.preventDefault();menu.style.display='block';let x=e.clientX,y=e.clientY;if(x+menu.offsetWidth>innerWidth)x=innerWidth-menu.offsetWidth-5;if(y+menu.offsetHeight>innerHeight)y=innerHeight-menu.offsetHeight-5;menu.style.left=x+'px';menu.style.top=y+'px'});document.addEventListener('click',()=>menu.style.display='none')})();`
    },
    {
        id: 'nosource', name: '禁止查看源码', icon: '🔒', color: '#E74C3C',
        desc: '禁用F12、右键查看源码、Ctrl+U',
        target: 'footer_js',
        code: `// [preset:nosource] 禁止查看源码
(function(){document.addEventListener('keydown',function(e){if(e.key==='F12'||(e.ctrlKey&&e.shiftKey&&(e.key==='I'||e.key==='J'||e.key==='C'))||(e.ctrlKey&&e.key==='u')){e.preventDefault();return false}});document.addEventListener('contextmenu',function(e){e.preventDefault()});(function c(){try{const d=new Date();debugger;if(new Date()-d>100){document.body.innerHTML='<div style=\"display:flex;justify-content:center;align-items:center;min-height:100vh;font-size:1.5em;color:#e74c3c;\">检测到开发者工具，页面已保护</div>'}}catch(e){}setTimeout(c,1000)})()})();`
    },
    {
        id: 'particles', name: '粒子背景', icon: '✨', color: '#3498DB',
        desc: '动态粒子连线背景效果',
        target: 'footer_js',
        code: `// [preset:particles] 粒子背景
(function(){const c=document.createElement('canvas');c.id='particles-bg';c.style.cssText='position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:-1;opacity:0.4';document.body.appendChild(c);const x=c.getContext('2d');let w,h;function resize(){w=c.width=innerWidth;h=c.height=innerHeight}resize();addEventListener('resize',resize);const dots=[];const N=50;for(let i=0;i<N;i++)dots.push({x:Math.random()*w,y:Math.random()*h,vx:(Math.random()-0.5)*0.8,vy:(Math.random()-0.5)*0.8,r:Math.random()*2+1});function draw(){x.clearRect(0,0,w,h);dots.forEach(d=>{d.x+=d.vx;d.y+=d.vy;if(d.x<0||d.x>w)d.vx*=-1;if(d.y<0||d.y>h)d.vy*=-1;x.beginPath();x.arc(d.x,d.y,d.r,0,Math.PI*2);x.fillStyle='#007AFF';x.fill()});for(let i=0;i<N;i++)for(let j=i+1;j<N;j++){const dx=dots[i].x-dots[j].x,dy=dots[i].y-dots[j].y,dist=Math.sqrt(dx*dx+dy*dy);if(dist<120){x.beginPath();x.moveTo(dots[i].x,dots[i].y);x.lineTo(dots[j].x,dots[j].y);x.strokeStyle='rgba(0,122,255,'+(1-dist/120)*0.3+')';x.stroke()}}requestAnimationFrame(draw)}draw()})();`
    },
    {
        id: 'cursor', name: '鼠标跟随', icon: '🌟', color: '#F39C12',
        desc: '鼠标移动时产生星星拖尾',
        target: 'footer_js',
        code: `// [preset:cursor] 鼠标星星拖尾
(function(){const stars=[];document.addEventListener('mousemove',function(e){stars.push({x:e.clientX,y:e.clientY,life:1,vx:(Math.random()-0.5)*2,vy:(Math.random()-0.5)*2-1,s:Math.random()*6+3})});const c=document.createElement('canvas');c.style.cssText='position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99999';document.body.appendChild(c);const x=c.getContext('2d');function resize(){c.width=innerWidth;c.height=innerHeight}resize();addEventListener('resize',resize);function draw(){x.clearRect(0,0,c.width,c.height);for(let i=stars.length-1;i>=0;i--){const s=stars[i];s.life-=0.02;s.x+=s.vx;s.y+=s.vy;if(s.life<=0){stars.splice(i,1);continue}x.save();x.globalAlpha=s.life;x.fillStyle='#FFD700';x.translate(s.x,s.y);x.rotate(Math.PI/4);x.fillRect(-s.s/2,-s.s/2,s.s,s.s);x.restore()}if(stars.length>100)stars.splice(0,stars.length-100);requestAnimationFrame(draw)}draw()})();`
    },
    {
        id: 'ribbon', name: '彩带背景', icon: '🎀', color: '#E91E63',
        desc: '点击页面更换彩带背景',
        target: 'footer_js',
        code: `// [preset:ribbon] 彩带背景（点击刷新）
(function(){const c=document.createElement('canvas');c.style.cssText='position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:-1;opacity:0.6';document.body.appendChild(c);const x=c.getContext('2d');let w,h;function resize(){w=c.width=innerWidth;h=c.height=innerHeight}resize();addEventListener('resize',resize);function draw(){x.clearRect(0,0,w,h);const colors=['#FF6B6B','#4ECDC4','#45B7D1','#96CEB4','#FFEAA7','#DDA0DD','#98D8C8'];let py=0,px=0;for(let i=0;i<6;i++){x.beginPath();x.moveTo(px,py);const segments=Math.floor(Math.random()*3)+3;for(let j=0;j<segments;j++){const nx=Math.random()*w;const ny=py+h/6*Math.random()+h/12;x.quadraticCurveTo(Math.random()*w,py+Math.random()*(ny-py),nx,ny);px=nx;py=ny}x.lineTo(w,py);x.lineTo(w,py-h/8);x.closePath();x.fillStyle=colors[i%colors.length]+'40';x.fill();py=Math.random()*h*0.3;px=0}}draw();document.addEventListener('click',draw)})();`
    }
];

// 渲染预设网格
function renderPresets() {
    const grid = document.getElementById('presetGrid');
    grid.innerHTML = '';
    PRESETS.forEach(p => {
        const isActive = isPresetActive(p);
        const card = document.createElement('div');
        card.style.cssText = `padding:14px;border-radius:10px;border:2px solid ${isActive ? p.color : '#e5e5e5'};background:${isActive ? p.color + '10' : '#fafafa'};cursor:pointer;transition:all 0.2s;text-align:center;`;
        card.innerHTML = `
            <div style="font-size:1.8em;margin-bottom:6px;">${p.icon}</div>
            <div style="font-weight:600;font-size:0.9em;color:#333;">${p.name}</div>
            <div style="font-size:0.75em;color:#999;margin-top:4px;">${p.desc}</div>
            <div style="margin-top:8px;font-size:0.75em;font-weight:600;color:${isActive ? '#27ae60' : '#ccc'};">${isActive ? '● 已启用' : '○ 未启用'}</div>
        `;
        card.onmouseenter = () => { card.style.borderColor = p.color; card.style.transform = 'translateY(-2px)'; };
        card.onmouseleave = () => { card.style.borderColor = isActive ? p.color : '#e5e5e5'; card.style.transform = ''; };
        card.onclick = () => togglePreset(p);
        grid.appendChild(card);
    });
}

function isPresetActive(preset) {
    const ta = document.getElementById(preset.target);
    return ta && ta.value.includes('[preset:' + preset.id + ']');
}

async function togglePreset(preset) {
    const ta = document.getElementById(preset.target);
    if (!ta) return;
    const marker = '[preset:' + preset.id + ']';
    if (ta.value.includes(marker)) {
        // 移除：找到整个预设代码块并删除
        const lines = ta.value.split('\n');
        const newLines = [];
        let skip = false;
        for (const line of lines) {
            if (line.includes(marker)) { skip = true; continue; }
            if (skip && line.trim() === '') { skip = false; continue; }
            if (!skip) newLines.push(line);
        }
        ta.value = newLines.join('\n').trim();
    } else {
        // 添加
        ta.value = (ta.value.trim() ? ta.value.trim() + '\n\n' : '') + preset.code;
    }
    // 自动保存
    await save(preset.target);
    renderPresets();
}

async function load() {
    const data = await API.get('/admin/api/custom-code.php');
    ['head_css', 'head_js', 'footer_css', 'footer_js'].forEach(pos => {
        const el = document.getElementById(pos);
        if (el) el.value = data[pos] || '';
    });
    renderPresets();
}

async function save(position) {
    const code = document.getElementById(position).value;
    await API.post('/admin/api/custom-code.php', { position, code });
    Toast.success('已保存');
}

load();
</script>

<?php admin_footer(); ?>
