<?php
/**
 * Android安装引导页 - 现代风格
 * 变量由 index.php 提供: $appName, $downloadHref, $themeColor, $iconUrl, $siteName
 */
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="format-detection" content="telephone=no">
    <meta name="description" content="<?= $appName ?>Android版下载安装">
    <title><?= $appName ?> - Android下载</title>
    <link rel="stylesheet" href="/static/fontawesome-free-7.1.0-web/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #e8f5e9 0%, #f5f5f5 100%); color: #333; min-height: 100vh; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .app-card { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .app-header { display: flex; gap: 16px; align-items: center; margin-bottom: 16px; }
        .app-icon { width: 80px; height: 80px; border-radius: 18px; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .app-meta h1 { font-size: 1.3em; margin-bottom: 4px; }
        .app-meta p { color: #86868b; font-size: 0.85em; }
        .download-btn { display: block; width: 100%; padding: 14px; background: <?= $themeColor ?>; color: #fff; text-align: center; text-decoration: none; border-radius: 12px; font-size: 1.1em; font-weight: 600; border: none; cursor: pointer; transition: opacity 0.2s; }
        .download-btn:hover { opacity: 0.9; }
        .download-btn i { margin-right: 6px; }
        .download-btn.disabled { background: #ccc; pointer-events: none; }
        .badge { display: inline-block; background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 6px; font-size: 0.75em; font-weight: 600; }
        .guide-card { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .guide-card h2 { font-size: 1.1em; margin-bottom: 16px; color: #333; }
        .step { display: flex; gap: 12px; margin-bottom: 16px; align-items: flex-start; }
        .step-num { width: 28px; height: 28px; border-radius: 50%; background: <?= $themeColor ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.85em; font-weight: 600; flex-shrink: 0; }
        .step-text { font-size: 0.9em; line-height: 1.6; padding-top: 3px; }
        .step-text strong { color: #2e7d32; }
        .faq-card { background: #fff; border-radius: 16px; padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .faq-card h2 { font-size: 1.1em; margin-bottom: 16px; }
        .faq-item { margin-bottom: 12px; }
        .faq-item strong { font-size: 0.9em; display: block; margin-bottom: 4px; }
        .faq-item p { font-size: 0.85em; color: #666; line-height: 1.5; }
        .info-list { list-style: none; }
        .info-list li { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.9em; }
        .info-list li:last-child { border: none; }
        .info-list li span:first-child { color: #86868b; flex-shrink: 0; width: 80px; }
        .info-list li span:last-child { text-align: right; flex: 1; }
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
            <i class="fab fa-chrome"></i>
            <p>请点击右上角 <strong>···</strong> 选择<br><strong>「在浏览器中打开」</strong><br>即可正常下载</p>
        </div>
    </div>

    <div class="container">
        <div class="app-card">
            <div class="app-header">
                <?php if ($iconUrl): ?>
                <img src="/<?= $iconUrl ?>" alt="<?= $appName ?>" class="app-icon">
                <?php else: ?>
                <div class="app-icon" style="background:<?= $themeColor ?>;display:flex;align-items:center;justify-content:center;font-size:2em;color:#fff;"><i class="fab fa-android"></i></div>
                <?php endif; ?>
                <div class="app-meta">
                    <h1><?= $appName ?></h1>
                    <p>Android版</p>
                    <span class="badge"><i class="fab fa-android"></i> APK安装包</span>
                </div>
            </div>
<?php if ($downloadHref): ?>
            <a class="download-btn" id="downloadBtn" href="<?= $downloadHref ?>" download>
                <i class="fas fa-download"></i> 下载安装
            </a>
<?php else: ?>
            <a class="download-btn disabled" href="javascript:void(0)">
                <i class="fas fa-exclamation-circle"></i> 暂无下载链接
            </a>
<?php endif; ?>
        </div>

        <div class="guide-card">
            <h2><i class="fas fa-book-open"></i> 安装步骤</h2>
            <div class="step"><span class="step-num">1</span><span class="step-text">点击上方「下载安装」按钮，等待APK文件下载完成</span></div>
            <div class="step"><span class="step-num">2</span><span class="step-text">如提示<strong>「允许安装未知应用」</strong>，请在弹窗中点击「设置」并开启权限</span></div>
            <div class="step"><span class="step-num">3</span><span class="step-text">下载完成后点击通知栏的提示，或在文件管理器中打开APK文件</span></div>
            <div class="step"><span class="step-num">4</span><span class="step-text">按照安装提示点击<strong>「安装」</strong>，等待安装完成即可使用</span></div>
        </div>

        <div class="faq-card">
            <h2><i class="fas fa-circle-question"></i> 常见问题</h2>
            <div class="faq-item"><strong>Q: 提示"安全风险"或"未知来源"？</strong><p>这是Android系统对非应用商店来源APK的正常提示。前往「设置 → 安全 → 安装未知应用」中允许当前浏览器安装即可。</p></div>
            <div class="faq-item"><strong>Q: 下载后找不到文件？</strong><p>请查看手机通知栏，或在文件管理器的「下载/Download」文件夹中查找。</p></div>
            <div class="faq-item"><strong>Q: 提示"解析包错误"？</strong><p>可能是下载不完整，请删除后重新下载。确保网络稳定。</p></div>
        </div>

        <div class="app-card">
            <h2 style="font-size:1.1em;margin-bottom:12px;">应用信息</h2>
            <ul class="info-list">
                <li><span>平台</span><span>Android</span></li>
                <li><span>格式</span><span>APK安装包</span></li>
                <li><span>语言</span><span>简体中文</span></li>
                <li><span>价格</span><span>免费</span></li>
            </ul>
        </div>

        <footer>
            <p>免责声明：本站仅提供下载托管，内容由开发者负责</p>
            <p style="margin-top:8px;"><a href="/">← 返回首页</a></p>
        </footer>
    </div>

    <script>
        (function() {
            var ua = navigator.userAgent.toLowerCase();
            if (/micromessenger/i.test(ua) || (/qq/i.test(ua) && !/mqqbrowser/i.test(ua)) || /weibo/i.test(ua) || /dingtalk/i.test(ua) || /alipayclient/i.test(ua)) {
                var btn = document.getElementById('downloadBtn');
                if (btn) {
                    btn.href = 'javascript:void(0)';
                    btn.removeAttribute('download');
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('browserTip').classList.add('show');
                    });
                }
            }
        })();
<?php if ($downloadHref): ?>
        document.getElementById('downloadBtn').addEventListener('click', function() {
            var btn = this, text = btn.innerHTML;
            setTimeout(function() {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 正在下载...';
            }, 100);
            setTimeout(function() {
                btn.innerHTML = '<i class="fas fa-check-circle"></i> 下载完成，请在通知栏打开安装';
                btn.style.background = '#27ae60';
            }, 3000);
            setTimeout(function() {
                btn.innerHTML = text;
                btn.style.background = '<?= $themeColor ?>';
            }, 10000);
        });
<?php endif; ?>
    </script>
</body>
</html>
