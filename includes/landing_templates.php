<?php
/**
 * 分发首页模板定义。
 *
 * 2.0 模板系统把“视觉皮肤”和“结构布局”拆开：
 * - 本文件提供模板目录与少量主题 token / 背景 CSS；
 * - static/landing-layouts.js 负责真实 DOM 布局；
 * - static/landing-layouts.css 负责每种布局的组件与响应式样式。
 *
 * 所有模板继续复用同一份应用数据、下载、轮播、统计和事件追踪逻辑。
 */

function landing_template_catalog(): array {
    return [
        'classic' => [
            'name' => '经典',
            'description' => 'AppDown 原始居中分发页，适合希望保持旧站体验的用户。',
            'preview' => '居中 Hero · 顶部应用切换 · 经典轮播',
            'layout' => 'classic',
        ],
        'glass' => [
            'name' => '玻璃工作台',
            'description' => 'Hero 与应用选择器拆成玻璃面板，内容区更像现代产品展示页。',
            'preview' => '玻璃 Hero · 悬浮导航 · 分层内容',
            'layout' => 'spotlight',
        ],
        'minimal' => [
            'name' => '编辑部',
            'description' => '杂志式标题、细线导航和超大截图区域，强调内容本身而非装饰。',
            'preview' => 'Editorial · 大留白 · 横向截图',
            'layout' => 'editorial',
        ],
        'midnight' => [
            'name' => '开发者终端',
            'description' => '左侧应用控制栏 + 右侧产品舞台，适合工具、开发者和科技产品。',
            'preview' => '深色控制栏 · 双栏舞台 · 高密度信息',
            'layout' => 'console',
        ],
        'aurora' => [
            'name' => '品牌发布',
            'description' => '大面积品牌 Hero、胶囊式应用切换和沉浸式截图，适合品牌发布。',
            'preview' => '大 Hero · 极光背景 · 沉浸展示',
            'layout' => 'showcase',
        ],
        'store' => [
            'name' => 'App Store',
            'description' => '应用商店式信息架构：左侧应用列表，右侧详情、下载方式和截图。',
            'preview' => '商店侧栏 · 应用摘要 · 截图画廊',
            'layout' => 'store',
        ],
        'bento' => [
            'name' => 'Bento 展示',
            'description' => '将统计、下载、截图和特色能力组织成 Bento 卡片矩阵。',
            'preview' => 'Bento 网格 · 指标卡 · 模块化组件',
            'layout' => 'bento',
        ],
        'split' => [
            'name' => 'Split 产品页',
            'description' => '桌面端左右分屏：左侧品牌与应用选择，右侧专注当前应用。',
            'preview' => '左右分屏 · 固定产品栏 · 大截图',
            'layout' => 'split',
        ],
        'mobile' => [
            'name' => 'Mobile First',
            'description' => '模拟手机产品页的窄内容流，按钮、截图和功能卡围绕触控体验重新布局。',
            'preview' => '移动优先 · 底部感按钮 · 纵向内容流',
            'layout' => 'mobile',
        ],
    ];
}

function normalize_landing_template(string $template): string {
    $templates = landing_template_catalog();
    return isset($templates[$template]) ? $template : 'classic';
}

function landing_template_layout(string $template): string {
    $template = normalize_landing_template($template);
    $catalog = landing_template_catalog();
    return (string)($catalog[$template]['layout'] ?? 'classic');
}

function landing_template_css(string $template): string {
    $template = normalize_landing_template($template);
    $css = [
        'classic' => '',
        'glass' => <<<'CSS'
body{--adl-accent:#5b7cff;background:linear-gradient(135deg,#eef7ff 0%,#f7f2ff 46%,#fff8ef 100%)!important;background-attachment:fixed!important}
body::before{background:radial-gradient(circle at 15% 20%,rgba(80,170,255,.22),transparent 34%),radial-gradient(circle at 82% 20%,rgba(182,113,255,.2),transparent 31%),radial-gradient(circle at 55% 85%,rgba(255,160,92,.16),transparent 35%)!important}
CSS,
        'minimal' => <<<'CSS'
body{--adl-accent:#111;background:#fff!important;color:#111!important}
body::before,.background-animation{display:none!important}
CSS,
        'midnight' => <<<'CSS'
body{--adl-accent:#60a5fa;background:#070a12!important;color:#f4f7ff!important}
body::before{background:radial-gradient(circle at 20% 15%,rgba(59,130,246,.2),transparent 34%),radial-gradient(circle at 80% 18%,rgba(139,92,246,.18),transparent 32%)!important}
CSS,
        'aurora' => <<<'CSS'
body{--adl-accent:#7c3aed;background:linear-gradient(140deg,#dff8ff 0%,#eee6ff 34%,#ffe7ef 67%,#fff2d9 100%)!important;background-attachment:fixed!important}
body::before{background:radial-gradient(circle at 8% 15%,rgba(0,210,255,.3),transparent 31%),radial-gradient(circle at 88% 13%,rgba(146,76,255,.28),transparent 31%),radial-gradient(circle at 65% 80%,rgba(255,78,145,.2),transparent 34%)!important}
CSS,
        'store' => <<<'CSS'
body{--adl-accent:#007aff;background:#f5f5f7!important;color:#1d1d1f!important}
body::before,.background-animation{display:none!important}
CSS,
        'bento' => <<<'CSS'
body{--adl-accent:#111827;background:#f3f4f6!important;color:#111827!important}
body::before{background:radial-gradient(circle at 10% 10%,rgba(99,102,241,.1),transparent 30%),radial-gradient(circle at 90% 20%,rgba(14,165,233,.09),transparent 28%)!important}
CSS,
        'split' => <<<'CSS'
body{--adl-accent:#2563eb;background:#f8fafc!important;color:#111827!important}
body::before,.background-animation{display:none!important}
CSS,
        'mobile' => <<<'CSS'
body{--adl-accent:#111827;background:#eef0f4!important;color:#111827!important}
body::before{display:none!important}
CSS,
    ];
    return $css[$template] ?? '';
}
