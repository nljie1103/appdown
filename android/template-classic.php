<?php
/**
 * Android安装引导页 - 经典风格（仿Play Store）
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Roboto', system-ui, -apple-system, sans-serif; background: #f1f3f4; color: #202124; min-height: 100vh; -webkit-text-size-adjust: 100%; }
        a { text-decoration: none; color: inherit; }
        .wrap { max-width: 500px; margin: 0 auto; padding: 16px; }

        /* 应用信息区 - Google Play风格 */
        .app-item { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .app-top { display: flex; gap: 16px; align-items: center; }
        .app-top .icon { width: 72px; height: 72px; border-radius: 16px; object-fit: cover; flex-shrink: 0; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        .app-top .icon-placeholder { width: 72px; height: 72px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2em; color: #fff; flex-shrink: 0; }
        .app-top .info { flex: 1; }
        .app-top .info strong { font-size: 1.1em; display: block; margin-bottom: 2px; color: #202124; }
        .app-top .info p { font-size: 0.8em; color: #5f6368; }
        .install-box { margin-top: 16px; }
        .install-btn { display: block; width: 100%; background: <?= $themeColor ?>; color: #fff; padding: 12px; border-radius: 8px; font-size: 0.95em; font-weight: 500; text-align: center; letter-spacing: 0.5px; transition: box-shadow 0.2s; }
        .install-btn:active { box-shadow: 0 1px 2px rgba(0,0,0,0.2); }
        .install-btn.disabled { background: #dadce0; color: #80868b; pointer-events: none; }

        /* 评分区 */
        .app-stats { display: flex; justify-content: space-around; margin-top: 16px; padding-top: 14px; border-top: 1px solid #e8eaed; text-align: center; }
        .app-stats .col b { font-size: 0.95em; display: block; color: #202124; }
        .app-stats .col p { font-size: 0.7em; color: #5f6368; margin-top: 2px; }
        .star-row { display: flex; align-items: center; justify-content: center; gap: 2px; }
        .star-row span { font-size: 0.8em; color: #fbbc04; }

        /* 引导按钮 */
        .guide-btn { display: block; text-align: center; padding: 12px; margin-bottom: 12px; background: #fff; border-radius: 12px; color: <?= $themeColor ?>; font-size: 0.95em; font-weight: 500; border: 1px solid #e8eaed; transition: background 0.2s; }
        .guide-btn:active { background: #f1f3f4; }

        /* 步骤 */
        .steps { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .steps h3 { font-size: 0.95em; margin-bottom: 16px; color: #202124; }
        .step-item { display: flex; gap: 12px; margin-bottom: 14px; align-items: flex-start; }
        .step-item:last-child { margin-bottom: 0; }
        .step-num { width: 24px; height: 24px; border-radius: 50%; background: <?= $themeColor ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.8em; font-weight: 500; flex-shrink: 0; }
        .step-desc { font-size: 0.85em; line-height: 1.6; color: #3c4043; padding-top: 2px; }
        .step-desc strong { color: #1a73e8; }

        /* FAQ */
        .faq { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .faq h3 { font-size: 0.95em; margin-bottom: 14px; }
        .faq-q { font-size: 0.85em; font-weight: 500; color: #202124; margin-bottom: 4px; }
        .faq-a { font-size: 0.82em; color: #5f6368; line-height: 1.5; margin-bottom: 12px; }
        .faq-a:last-child { margin-bottom: 0; }

        /* 应用信息 */
        .app-info { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .app-info h3 { font-size: 0.95em; margin-bottom: 12px; }
        .app-info li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f3f4; font-size: 0.85em; list-style: none; }
        .app-info li:last-child { border: none; }
        .app-info li span:first-child { color: #5f6368; }
        .app-info li span:last-child { color: #202124; }

        footer { text-align: center; padding: 16px; font-size: 0.75em; color: #5f6368; }

        /* 微信/QQ提示 */
        .wechat-tip { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; justify-content: center; align-items: center; }
        .wechat-tip.show { display: flex; }
        .wechat-tip-box { background: #fff; border-radius: 12px; padding: 30px; text-align: center; max-width: 280px; }
        .wechat-tip-box p { margin-top: 12px; font-size: 0.95em; line-height: 1.6; color: #3c4043; }

        /* 安装引导弹窗 */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; border-radius: 16px; width: 90%; max-width: 420px; max-height: 85vh; overflow: hidden; position: relative; padding: 24px; }
        .modal-close { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; background: #f1f3f4; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; font-size: 1.1em; color: #5f6368; }
        .modal-step { display: none; text-align: center; padding: 20px 0; }
        .modal-step.active { display: block; }
        .modal-step .emoji { font-size: 3em; margin-bottom: 16px; }
        .modal-step .text { font-size: 0.95em; line-height: 1.7; color: #3c4043; }
        .modal-dots { text-align: center; margin: 12px 0; }
        .modal-dots span { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #dadce0; margin: 0 4px; transition: background 0.3s; }
        .modal-dots span.active { background: <?= $themeColor ?>; }
        .modal-btns { display: flex; justify-content: flex-end; gap: 8px; }
        .modal-btns button { background: <?= $themeColor ?>; color: #fff; border: none; border-radius: 8px; padding: 8px 24px; font-size: 0.9em; cursor: pointer; font-weight: 500; }
        .modal-btns button:active { opacity: 0.8; }
        .modal-btns button.hide { display: none; }
    </style>
</head>
<body>
    <!-- 微信/QQ提示 -->
    <div class="wechat-tip" id="wechatTip" onclick="this.classList.remove('show')">
        <div class="wechat-tip-box">
            <div style="font-size:2.5em;">🌐</div>
            <p>请点击右上角 <strong>···</strong><br>选择<strong>「在浏览器中打开」</strong></p>
        </div>
    </div>

    <div class="wrap">
        <!-- 应用卡片 -->
        <div class="app-item">
            <div class="app-top">
                <?php if ($iconUrl): ?>
                <img src="/<?= $iconUrl ?>" alt="<?= $appName ?>" class="icon">
                <?php else: ?>
                <div class="icon-placeholder" style="background:<?= $themeColor ?>;">🤖</div>
                <?php endif; ?>
                <div class="info">
                    <strong><?= $appName ?></strong>
                    <p><?= $siteName ?></p>
                    <p style="margin-top:4px;font-size:0.75em;color:#01875f;">包含广告 · 应用内购买</p>
                </div>
            </div>
            <div class="install-box">
<?php if ($downloadHref): ?>
                <a class="install-btn" id="downloadBtn" href="<?= $downloadHref ?>" download>安装</a>
<?php else: ?>
                <a class="install-btn disabled" href="javascript:void(0)">暂无下载</a>
<?php endif; ?>
            </div>
            <div class="app-stats">
                <div class="col">
                    <div class="star-row"><span>★</span><b>4.8</b></div>
                    <p>12K 条评价</p>
                </div>
                <div class="col">
                    <b>100万+</b>
                    <p>次下载</p>
                </div>
                <div class="col">
                    <b>18+</b>
                    <p>适用年龄</p>
                </div>
            </div>
        </div>

        <!-- 安装引导 -->
        <a href="javascript:;" class="guide-btn" id="showGuide">📖 查看Android安装步骤指南</a>

        <!-- 安装步骤 -->
        <div class="steps">
            <h3>安装说明</h3>
            <div class="step-item"><span class="step-num">1</span><span class="step-desc">点击上方<strong>「安装」</strong>按钮下载APK文件</span></div>
            <div class="step-item"><span class="step-num">2</span><span class="step-desc">如系统提示<strong>「安全风险」</strong>，请选择「仍然安装」或在设置中允许安装未知来源应用</span></div>
            <div class="step-item"><span class="step-num">3</span><span class="step-desc">下载完成后打开APK文件，点击<strong>「安装」</strong></span></div>
            <div class="step-item"><span class="step-num">4</span><span class="step-desc">安装完成，在桌面找到应用图标即可使用</span></div>
        </div>

        <!-- FAQ -->
        <div class="faq">
            <h3>常见问题</h3>
            <div class="faq-q">提示"安装包有风险"怎么办？</div>
            <div class="faq-a">这是系统对非应用商店来源的安全提示，选择「仍然安装」即可。应用本身是安全的。</div>
            <div class="faq-q">下载后找不到文件？</div>
            <div class="faq-a">查看手机通知栏的下载完成通知，或在文件管理器的「Download」文件夹中查找。</div>
            <div class="faq-q">提示"解析包时出现问题"？</div>
            <div class="faq-a">可能是网络不稳定导致下载不完整，请删除已下载文件后重新下载。</div>
        </div>

        <!-- 应用信息 -->
        <div class="app-info">
            <h3>关于此应用</h3>
            <ul>
                <li><span>平台</span><span>Android</span></li>
                <li><span>格式</span><span>APK安装包</span></li>
                <li><span>语言</span><span>简体中文</span></li>
                <li><span>价格</span><span>免费</span></li>
            </ul>
        </div>

        <footer>
            <p>免责声明：本站仅提供下载托管，内容由开发者负责</p>
            <p style="margin-top:6px;"><a href="/" style="color:<?= $themeColor ?>">← 返回首页</a></p>
        </footer>
    </div>

    <!-- 安装引导弹窗 -->
    <div class="modal-overlay" id="guideModal">
        <div class="modal-box">
            <button class="modal-close" id="guideClose">✕</button>
            <div id="modalSteps">
                <div class="modal-step active">
                    <div class="emoji">📥</div>
                    <div class="text">第一步<br>点击「安装」按钮<br>等待APK文件下载完成</div>
                </div>
                <div class="modal-step">
                    <div class="emoji">🛡️</div>
                    <div class="text">第二步<br>如提示安全风险<br>选择<strong>「仍然安装」</strong>或<br>前往设置开启「安装未知应用」</div>
                </div>
                <div class="modal-step">
                    <div class="emoji">📂</div>
                    <div class="text">第三步<br>在通知栏或文件管理器中<br>打开已下载的APK文件</div>
                </div>
                <div class="modal-step">
                    <div class="emoji">✅</div>
                    <div class="text">第四步<br>按照提示完成安装<br>在桌面找到应用图标即可使用</div>
                </div>
            </div>
            <div class="modal-dots" id="modalDots"></div>
            <div class="modal-btns">
                <button id="prevBtn" class="hide">上一步</button>
                <button id="nextBtn">下一步</button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        // 微信/QQ/微博等检测
        var ua = navigator.userAgent.toLowerCase();
        var isInApp = /micromessenger/i.test(ua) || (/qq/i.test(ua) && !/mqqbrowser/i.test(ua)) || /weibo/i.test(ua) || /dingtalk/i.test(ua) || /alipayclient/i.test(ua);
        if (isInApp) {
            var btn = document.getElementById('downloadBtn');
            if (btn) {
                btn.href = 'javascript:void(0)';
                btn.removeAttribute('download');
                btn.onclick = function(e) {
                    e.preventDefault();
                    document.getElementById('wechatTip').classList.add('show');
                };
            }
        }

<?php if ($downloadHref): ?>
        // 下载按钮反馈
        document.getElementById('downloadBtn').addEventListener('click', function() {
            if (isInApp) return;
            var btn = this, text = btn.textContent;
            setTimeout(function() { btn.textContent = '正在下载...'; }, 100);
            setTimeout(function() {
                btn.textContent = '下载完成，请安装';
                btn.style.background = '#01875f';
            }, 3000);
            setTimeout(function() {
                btn.textContent = text;
                btn.style.background = '<?= $themeColor ?>';
            }, 10000);
        });
<?php endif; ?>

        // 弹窗轮播
        var steps = document.querySelectorAll('#modalSteps .modal-step');
        var total = steps.length, current = 0;
        var dotsEl = document.getElementById('modalDots');
        var prev = document.getElementById('prevBtn');
        var next = document.getElementById('nextBtn');

        for (var i = 0; i < total; i++) {
            var dot = document.createElement('span');
            if (i === 0) dot.className = 'active';
            dotsEl.appendChild(dot);
        }

        function updateModal() {
            for (var i = 0; i < total; i++) {
                steps[i].className = 'modal-step' + (i === current ? ' active' : '');
                dotsEl.children[i].className = i === current ? 'active' : '';
            }
            prev.className = current <= 0 ? 'hide' : '';
            next.className = current >= total - 1 ? 'hide' : '';
        }

        prev.onclick = function() { if (current > 0) { current--; updateModal(); } };
        next.onclick = function() { if (current < total - 1) { current++; updateModal(); } };

        var modal = document.getElementById('guideModal');
        document.getElementById('showGuide').onclick = function() {
            current = 0; updateModal();
            modal.classList.add('show');
        };
        document.getElementById('guideClose').onclick = function() { modal.classList.remove('show'); };
        modal.onclick = function(e) { if (e.target === modal) modal.classList.remove('show'); };
    })();
    </script>
</body>
</html>
