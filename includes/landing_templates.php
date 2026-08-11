<?php
/**
 * 分发首页模板定义。
 * 模板只覆盖视觉层，继续复用 index.html 的渲染、下载、轮播和统计逻辑。
 */

function landing_template_catalog(): array {
    return [
        'classic' => [
            'name' => '经典',
            'description' => '保留 AppDown 当前清爽渐变与卡片布局。',
            'preview' => '经典浅色渐变、圆角卡片',
        ],
        'glass' => [
            'name' => '玻璃拟态',
            'description' => '半透明玻璃卡片、柔和光晕与悬浮层次。',
            'preview' => '玻璃卡片、柔光背景',
        ],
        'minimal' => [
            'name' => '极简白',
            'description' => '更克制的留白、细边框与内容优先布局。',
            'preview' => '大留白、细线、低干扰',
        ],
        'midnight' => [
            'name' => '午夜深色',
            'description' => '深色背景、柔和高亮，适合工具和开发者产品。',
            'preview' => '深色、霓虹高亮',
        ],
        'aurora' => [
            'name' => '极光渐变',
            'description' => '更鲜明的渐变背景与高饱和按钮，适合品牌展示。',
            'preview' => '极光渐变、彩色玻璃',
        ],
    ];
}

function normalize_landing_template(string $template): string {
    $templates = landing_template_catalog();
    return isset($templates[$template]) ? $template : 'classic';
}

function landing_template_css(string $template): string {
    $template = normalize_landing_template($template);
    $css = [
        'classic' => '',
        'glass' => <<<'CSS'
body{background:linear-gradient(135deg,#eef7ff 0%,#f9f2ff 45%,#fff7ef 100%)!important;background-attachment:fixed!important}
body::before{background:radial-gradient(circle at 15% 20%,rgba(80,170,255,.26),transparent 34%),radial-gradient(circle at 82% 20%,rgba(182,113,255,.24),transparent 31%),radial-gradient(circle at 55% 85%,rgba(255,160,92,.2),transparent 35%)!important;filter:blur(16px)!important;opacity:1!important}
.container{max-width:1180px!important}
.app-tabs-container,.download-notice,.feature-card,.partners-section,.download-stats{background:rgba(255,255,255,.56)!important;border:1px solid rgba(255,255,255,.78)!important;box-shadow:0 18px 50px rgba(43,61,92,.12)!important;backdrop-filter:blur(22px) saturate(135%)!important;-webkit-backdrop-filter:blur(22px) saturate(135%)!important}
.app-tabs-container{border-radius:28px!important;padding:7px!important}
.app-tab{border-radius:20px!important;border-bottom:0!important}
.app-tab.active{background:rgba(255,255,255,.8)!important;box-shadow:0 8px 24px rgba(35,55,90,.12)!important}
.app-section h2{border:0!important}
.download-button{border-radius:18px!important;box-shadow:0 14px 28px rgba(25,60,105,.18)!important}
.carousel-container{border-radius:28px!important;background:rgba(255,255,255,.46)!important;border:1px solid rgba(255,255,255,.7)!important;box-shadow:0 24px 55px rgba(38,59,90,.15)!important;backdrop-filter:blur(18px)!important}
.friend-link-card{background:rgba(255,255,255,.62)!important;border:1px solid rgba(255,255,255,.75)!important;border-radius:16px!important}
CSS,
        'minimal' => <<<'CSS'
body{background:#fff!important;color:#111!important}
body::before{display:none!important}
.container{max-width:980px!important;padding-top:44px!important}
.logo{border-radius:24px!important;box-shadow:none!important;border:1px solid #ececec!important}
h1{font-size:clamp(2rem,5vw,3.6rem)!important;letter-spacing:-.045em!important;font-weight:760!important}
.download-notice{background:#fafafa!important;color:#444!important;border:1px solid #ededed!important;box-shadow:none!important;font-weight:500!important}
.app-tabs-container{background:transparent!important;border:0!important;border-bottom:1px solid #e8e8e8!important;border-radius:0!important;box-shadow:none!important;backdrop-filter:none!important}
.app-tab{font-size:1rem!important;font-weight:600!important;padding:15px 18px!important;border-bottom:2px solid transparent!important}
.app-tab:hover,.app-tab.active{background:transparent!important;box-shadow:none!important;transform:none!important}
.app-section h2{font-size:1.45rem!important;border:0!important;margin-top:30px!important}
.download-button{border-radius:12px!important;box-shadow:none!important;min-width:190px!important}
.carousel-container{border-radius:18px!important;box-shadow:none!important;border:1px solid #ececec!important;background:#fafafa!important}
.download-stats{background:#fafafa!important;border:1px solid #ececec!important;box-shadow:none!important;border-radius:16px!important}
.feature-card{box-shadow:none!important;border:1px solid #ececec!important;border-radius:16px!important;background:#fff!important}
.partners-section{box-shadow:none!important;border-top:1px solid #eee!important;background:#fafafa!important}
.friend-link-card{box-shadow:none!important;border:1px solid #e8e8e8!important;background:#fff!important}
CSS,
        'midnight' => <<<'CSS'
:root{--primary:#f4f7ff!important;--secondary:#a7b0c4!important}
body{background:#070a12!important;color:#f4f7ff!important}
body::before{background:radial-gradient(circle at 20% 15%,rgba(59,130,246,.22),transparent 34%),radial-gradient(circle at 80% 18%,rgba(139,92,246,.2),transparent 32%),radial-gradient(circle at 50% 90%,rgba(14,165,233,.12),transparent 40%)!important;filter:blur(10px)!important;opacity:1!important}
h1,h2,h3,.stat-number{color:#f6f8ff!important}
.download-notice{background:rgba(251,191,36,.1)!important;color:#fcd34d!important;border:1px solid rgba(251,191,36,.25)!important;box-shadow:none!important}
.app-tabs-container,.download-stats,.feature-card,.partners-section{background:rgba(17,24,39,.78)!important;border:1px solid rgba(148,163,184,.14)!important;box-shadow:0 18px 48px rgba(0,0,0,.28)!important;backdrop-filter:blur(18px)!important}
.app-tab{color:#aeb8cc!important}
.app-tab:hover{background:rgba(255,255,255,.05)!important}
.app-tab.active{background:rgba(96,165,250,.1)!important}
.app-section h2{border-color:rgba(148,163,184,.18)!important}
.download-button{box-shadow:0 12px 32px rgba(0,0,0,.28)!important;border:1px solid rgba(255,255,255,.12)!important}
.carousel-container{background:#0d1321!important;border:1px solid rgba(148,163,184,.14)!important;box-shadow:0 24px 60px rgba(0,0,0,.38)!important}
.friend-link-card{background:#111827!important;color:#dce5f7!important;border:1px solid rgba(148,163,184,.15)!important}
footer,footer a,.stat-label,.feature-card p{color:#9ca9be!important}
CSS,
        'aurora' => <<<'CSS'
body{background:linear-gradient(140deg,#dff8ff 0%,#eee6ff 34%,#ffe7ef 67%,#fff2d9 100%)!important;background-attachment:fixed!important}
body::before{background:radial-gradient(circle at 8% 15%,rgba(0,210,255,.35),transparent 31%),radial-gradient(circle at 88% 13%,rgba(146,76,255,.33),transparent 31%),radial-gradient(circle at 65% 80%,rgba(255,78,145,.24),transparent 34%),radial-gradient(circle at 18% 82%,rgba(255,188,58,.2),transparent 30%)!important;filter:blur(24px)!important;opacity:.9!important}
.logo{box-shadow:0 18px 50px rgba(104,65,180,.2)!important}
h1{font-size:clamp(2rem,5vw,3.4rem)!important;letter-spacing:-.04em!important}
.app-tabs-container{background:rgba(255,255,255,.68)!important;border:1px solid rgba(255,255,255,.82)!important;border-radius:999px!important;padding:7px!important;box-shadow:0 18px 45px rgba(75,61,128,.13)!important;backdrop-filter:blur(20px)!important}
.app-tab{border:0!important;border-radius:999px!important}
.app-tab.active{background:#fff!important;box-shadow:0 8px 22px rgba(72,55,120,.13)!important}
.app-section h2{border:0!important}
.download-button{border-radius:999px!important;box-shadow:0 16px 30px rgba(75,61,128,.18)!important}
.carousel-container{border-radius:30px!important;background:rgba(255,255,255,.58)!important;border:1px solid rgba(255,255,255,.88)!important;box-shadow:0 26px 60px rgba(73,56,120,.15)!important;backdrop-filter:blur(18px)!important}
.download-stats,.feature-card,.partners-section{background:rgba(255,255,255,.58)!important;border:1px solid rgba(255,255,255,.8)!important;box-shadow:0 20px 48px rgba(73,56,120,.12)!important;backdrop-filter:blur(18px)!important}
.feature-card,.friend-link-card{border-radius:22px!important}
CSS,
    ];
    return $css[$template] ?? '';
}
