<?php
/**
 * iOS安装引导页 - 现代风格（毛玻璃）
 * 变量由 index.php 提供: $appName, $plistUrl, $certName, $description, $version, $size, $themeColor, $iconUrl, $siteName
 */
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="format-detection" content="telephone=no">
    <meta name="description" content="<?= $appName ?>iOS版下载，苹果手机安装教程">
    <title><?= $appName ?> - iOS下载</title>
    <link rel="stylesheet" href="/static/fontawesome-free-7.1.0-web/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f7; color: #333; min-height: 100vh; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .app-card { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .app-header { display: flex; gap: 16px; align-items: center; margin-bottom: 16px; }
        .app-icon { width: 80px; height: 80px; border-radius: 18px; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .app-meta h1 { font-size: 1.3em; margin-bottom: 4px; }
        .app-meta p { color: #86868b; font-size: 0.85em; }
        .install-btn { display: block; width: 100%; padding: 14px; background: <?= $themeColor ?>; color: #fff; text-align: center; text-decoration: none; border-radius: 12px; font-size: 1.1em; font-weight: 600; border: none; cursor: pointer; transition: opacity 0.2s; }
        .install-btn:hover { opacity: 0.9; }
        .install-btn i { margin-right: 6px; }
        .stars { color: #ff9500; font-size: 0.9em; }
        .info-list { list-style: none; }
        .info-list li { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.9em; }
        .info-list li:last-child { border: none; }
        .info-list li span:first-child { color: #86868b; flex-shrink: 0; width: 80px; }
        .info-list li span:last-child { text-align: right; flex: 1; }
        .guide-card { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .guide-card h2 { font-size: 1.1em; margin-bottom: 16px; color: #333; }
        .step { display: flex; gap: 12px; margin-bottom: 16px; align-items: flex-start; }
        .step-num { width: 28px; height: 28px; border-radius: 50%; background: <?= $themeColor ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.85em; font-weight: 600; flex-shrink: 0; }
        .step-text { font-size: 0.9em; line-height: 1.6; padding-top: 3px; }
        .cert-name { color: #ff3b30; font-weight: 600; }
        .faq-card { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .faq-card h2 { font-size: 1.1em; margin-bottom: 16px; }
        .faq-item { margin-bottom: 12px; }
        .faq-item strong { font-size: 0.9em; display: block; margin-bottom: 4px; }
        .faq-item p { font-size: 0.85em; color: #666; line-height: 1.5; }
        .browser-tip { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: flex-start; padding-top: 60px; }
        .browser-tip.show { display: flex; }
        .browser-tip-content { background: #fff; border-radius: 16px; padding: 30px; max-width: 300px; text-align: center; }
        .browser-tip-content i { font-size: 3em; color: <?= $themeColor ?>; }
        .browser-tip-content p { margin-top: 16px; font-size: 0.95em; line-height: 1.6; }
        footer { text-align: center; padding: 20px; color: #86868b; font-size: 0.8em; }
        footer a { color: <?= $themeColor ?>; text-decoration: none; }
    </style>
</head>
<body>
    <div class="browser-tip" id="browserTip" onclick="this.classList.remove('show')">
        <div class="browser-tip-content">
            <i class="fas fa-compass"></i>
            <p>请点击右上角 <strong>···</strong> 选择<br><strong>「在Safari中打开」</strong><br>即可正常安装</p>
        </div>
    </div>

    <div class="container">
        <div class="app-card">
            <div class="app-header">
                <?php if ($iconUrl): ?>
                <img src="/<?= $iconUrl ?>" alt="<?= $appName ?>" class="app-icon">
                <?php else: ?>
                <div class="app-icon" style="background:<?= $themeColor ?>;display:flex;align-items:center;justify-content:center;font-size:2em;color:#fff;"><i class="fas fa-mobile-alt"></i></div>
                <?php endif; ?>
                <div class="app-meta">
                    <h1><?= $appName ?></h1>
                    <p>iOS版 · <?= $version ?: '最新版' ?></p>
                    <div class="stars">★★★★★ <span style="color:#333;font-weight:600;">4.9</span></div>
                </div>
            </div>
            <a class="install-btn" id="installBtn" href="<?= $plistUrl ?>">
                <i class="fas fa-download"></i> 点击安装
            </a>
        </div>

<?php if ($certName): ?>
        <div class="guide-card">
            <h2><i class="fas fa-book-open"></i> 安装步骤</h2>
            <div class="step"><span class="step-num">1</span><span class="step-text">请使用 <strong>Safari浏览器</strong> 打开此页面，点击上方「点击安装」</span></div>
            <div class="step"><span class="step-num">2</span><span class="step-text">弹窗提示中选择「安装」，返回桌面等待安装完成</span></div>
            <div class="step"><span class="step-num">3</span><span class="step-text">打开 <strong>设置 → 通用 → VPN与设备管理</strong></span></div>
            <div class="step"><span class="step-num">4</span><span class="step-text">找到 <span class="cert-name"><?= $certName ?></span> 并点击「信任」</span></div>
            <div class="step"><span class="step-num">5</span><span class="step-text">输入锁屏密码确认信任，即可正常使用</span></div>
        </div>
<?php endif; ?>

        <div class="app-card">
            <h2 style="font-size:1.1em;margin-bottom:12px;">应用信息</h2>
            <ul class="info-list">
<?php if ($size): ?>    <li><span>大小</span><span><?= $size ?></span></li><?php endif; ?>
<?php if ($version): ?> <li><span>版本</span><span><?= $version ?></span></li><?php endif; ?>
<?php if ($description): ?><li><span>简介</span><span><?= $description ?></span></li><?php endif; ?>
                <li><span>兼容性</span><span>需要 iOS 8.0 或更高版本</span></li>
                <li><span>语言</span><span>简体中文</span></li>
                <li><span>价格</span><span>免费</span></li>
            </ul>
        </div>

<?php if ($certName): ?>
        <div class="faq-card">
            <h2><i class="fas fa-circle-question"></i> 常见问题</h2>
            <div class="faq-item"><strong>Q: 提示"未受信任的开发者"？</strong><p>进入「设置→通用→VPN与设备管理」→ 找到 <span class="cert-name"><?= $certName ?></span> → 点击信任</p></div>
            <div class="faq-item"><strong>Q: 无法下载？</strong><p>请关闭广告拦截插件，或确认使用Safari浏览器打开</p></div>
            <div class="faq-item"><strong>Q: 安装后闪退？</strong><p>重新信任证书，或卸载后重新安装</p></div>
        </div>
<?php endif; ?>

        <footer>
            <p>免责声明：本站仅提供下载托管，内容由开发者负责</p>
            <p style="margin-top:8px;"><a href="/">← 返回首页</a></p>
        </footer>
    </div>

    <script>
        (function() {
            var ua = navigator.userAgent.toLowerCase();
            if (/micromessenger/i.test(ua) || (/qq/i.test(ua) && !/mqqbrowser/i.test(ua))) {
                document.getElementById('installBtn').href = 'javascript:void(0)';
                document.getElementById('installBtn').addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('browserTip').classList.add('show');
                });
            }
        })();
        document.getElementById('installBtn').addEventListener('click', function() {
            var btn = this, text = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 正在请求安装...';
            setTimeout(function() { btn.innerHTML = text; }, 2000);
        });
    </script>
</body>
</html>
