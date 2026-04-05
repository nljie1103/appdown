<?php
/**
 * iOS安装引导页 - 经典风格（仿App Store）
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, system-ui, sans-serif; background: #f2f2f7; color: #333; min-height: 100vh; -webkit-text-size-adjust: 100%; }
        a { text-decoration: none; color: inherit; }

        .wrap { max-width: 500px; margin: 0 auto; padding: 16px; }

        /* 应用信息区 */
        .app-item { background: #fff; border-radius: 14px; padding: 20px; margin-bottom: 12px; }
        .app-top { display: flex; gap: 14px; align-items: center; }
        .app-top .icon { width: 72px; height: 72px; border-radius: 16px; object-fit: cover; flex-shrink: 0; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        .app-top .icon-placeholder { width: 72px; height: 72px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2em; color: #fff; flex-shrink: 0; }
        .app-top .info { flex: 1; }
        .app-top .info strong { font-size: 1.05em; display: block; margin-bottom: 3px; }
        .app-top .info strong span { font-size: 0.7em; color: #86868b; font-weight: 400; }
        .app-top .info p { font-size: 0.8em; color: #86868b; }
        .install-box { margin-top: 14px; text-align: center; }
        .install-btn { display: inline-block; background: <?= $themeColor ?>; color: #fff; padding: 10px 40px; border-radius: 20px; font-size: 1em; font-weight: 600; box-shadow: 0 3px 8px <?= $themeColor ?>44; transition: opacity 0.2s; }
        .install-btn:active { opacity: 0.8; }

        /* 评分条 */
        .app-rating { display: flex; justify-content: space-around; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f0f0f0; text-align: center; }
        .app-rating .col b { font-size: 1.1em; display: block; }
        .app-rating .col p { font-size: 0.7em; color: #86868b; }
        .app-rating .col i { font-style: normal; font-size: 0.75em; color: #86868b; }
        .star-text { color: #ff9500; font-size: 0.85em; letter-spacing: 1px; }

        /* 引导按钮 */
        .guide-btn { display: block; text-align: center; padding: 12px; margin-bottom: 12px; background: #fff; border-radius: 14px; color: <?= $themeColor ?>; font-size: 0.95em; border: 1px solid #e5e5ea; transition: background 0.2s; }
        .guide-btn:active { background: #f2f2f7; }

        /* 常见问题 */
        .faq { background: #fff; border-radius: 14px; padding: 18px; margin-bottom: 12px; font-size: 0.85em; line-height: 1.7; color: #555; }
        .faq strong { color: #333; display: block; margin-bottom: 6px; font-size: 0.95em; }
        .faq p { margin-bottom: 8px; }
        .cert-name { color: #ff3b30; font-weight: 600; }

        /* 评论区 */
        .review { background: #fff; border-radius: 14px; padding: 18px; margin-bottom: 12px; }
        .review h3 { font-size: 0.95em; margin-bottom: 12px; }
        .review-score { display: flex; gap: 16px; align-items: center; }
        .review-score .big { font-size: 3em; font-weight: 700; line-height: 1; }
        .review-score .bars { flex: 1; }
        .bar-row { display: flex; align-items: center; gap: 4px; margin-bottom: 3px; }
        .bar-row span { font-size: 0.7em; color: #86868b; width: 14px; text-align: right; }
        .bar-track { flex: 1; height: 4px; background: #e5e5ea; border-radius: 2px; overflow: hidden; }
        .bar-fill { height: 100%; background: #ff9500; border-radius: 2px; }

        /* 应用信息 */
        .app-info { background: #fff; border-radius: 14px; padding: 18px; margin-bottom: 12px; }
        .app-info h3 { font-size: 0.95em; margin-bottom: 12px; }
        .app-info li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.85em; list-style: none; }
        .app-info li:last-child { border: none; }
        .app-info li span:first-child { color: #86868b; }
        .app-info li span:last-child { text-align: right; flex: 1; margin-left: 16px; }

        /* 更新日志 */
        .changelog { background: #fff; border-radius: 14px; padding: 18px; margin-bottom: 12px; }
        .changelog h3 { font-size: 0.95em; margin-bottom: 8px; }
        .changelog .ver { font-size: 0.85em; color: #86868b; }

        footer { text-align: center; padding: 16px; font-size: 0.75em; color: #86868b; }

        /* 弹窗 */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; border-radius: 16px; width: 90%; max-width: 420px; max-height: 85vh; overflow: hidden; position: relative; }
        .modal-close { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; background: #f2f2f7; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; font-size: 1.2em; color: #666; }

        /* 轮播 */
        .slider { overflow: hidden; padding: 20px 20px 0; }
        .slider-track { display: flex; transition: transform 0.3s ease; }
        .slider-slide { flex: 0 0 100%; text-align: center; }
        .slider-slide .text { padding: 16px 0 8px; font-size: 0.9em; line-height: 1.6; color: #333; }
        .slider-dots { text-align: center; padding: 10px 0; }
        .slider-dots span { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #d1d1d6; margin: 0 4px; cursor: pointer; transition: background 0.3s; }
        .slider-dots span.active { background: <?= $themeColor ?>; }
        .slider-btns { display: flex; justify-content: flex-end; gap: 8px; padding: 8px 20px 20px; }
        .slider-btns button { background: <?= $themeColor ?>; color: #fff; border: none; border-radius: 16px; padding: 8px 24px; font-size: 0.9em; cursor: pointer; }
        .slider-btns button:active { opacity: 0.8; }
        .slider-btns button.hidden { display: none; }

        /* 微信/QQ提示 */
        .wechat-tip { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; justify-content: center; align-items: center; }
        .wechat-tip.show { display: flex; }
        .wechat-tip-box { background: #fff; border-radius: 16px; padding: 30px; text-align: center; max-width: 280px; }
        .wechat-tip-box p { margin-top: 12px; font-size: 0.95em; line-height: 1.6; }
    </style>
</head>
<body>
    <!-- 微信/QQ提示 -->
    <div class="wechat-tip" id="wechatTip" onclick="this.classList.remove('show')">
        <div class="wechat-tip-box">
            <div style="font-size:2.5em;">🧭</div>
            <p>请点击右上角 <strong>···</strong><br>选择<strong>「在Safari中打开」</strong></p>
        </div>
    </div>

    <div class="wrap">
        <!-- 应用卡片 -->
        <div class="app-item">
            <div class="app-top">
                <?php if ($iconUrl): ?>
                <img src="/<?= $iconUrl ?>" alt="<?= $appName ?>" class="icon">
                <?php else: ?>
                <div class="icon-placeholder" style="background:<?= $themeColor ?>;">📱</div>
                <?php endif; ?>
                <div class="info">
                    <strong><?= $appName ?> <span><?= $version ?: '' ?></span></strong>
                    <p><?= $siteName ?></p>
                </div>
            </div>
            <div class="install-box">
                <a class="install-btn" id="installBtn" href="<?= $plistUrl ?>">点击安装</a>
            </div>
            <div class="app-rating">
                <div class="col">
                    <div class="star-text">★★★★★</div>
                    <b>4.9</b>
                    <p>19k 个评分</p>
                </div>
                <div class="col">
                    <i>#</i><b>3</b>
                    <p>排行榜</p>
                </div>
                <div class="col">
                    <b>18+</b>
                    <p>年龄</p>
                </div>
            </div>
        </div>

        <!-- 安装引导 -->
<?php if ($certName): ?>
        <a href="javascript:;" class="guide-btn" id="showGuide">📖 查看iOS安装步骤指南</a>

        <div class="faq">
            <strong>安装须知：</strong>
            <p>1. <span class="cert-name">请复制链接到Safari浏览器打开下载安装</span></p>
            <p>2. 安装后需在「设置-通用-VPN与设备管理」中信任证书</p>
            <p>3. 证书名称：<span class="cert-name"><?= $certName ?></span></p>
            <p>4. 信任后输入锁屏密码确认</p>
        </div>
<?php endif; ?>

        <!-- 评论区 -->
        <div class="review">
            <h3>评分及评论</h3>
            <div class="review-score">
                <div class="big">4.9</div>
                <div class="bars">
                    <div class="bar-row"><span>5</span><div class="bar-track"><div class="bar-fill" style="width:85%"></div></div></div>
                    <div class="bar-row"><span>4</span><div class="bar-track"><div class="bar-fill" style="width:10%"></div></div></div>
                    <div class="bar-row"><span>3</span><div class="bar-track"><div class="bar-fill" style="width:3%"></div></div></div>
                    <div class="bar-row"><span>2</span><div class="bar-track"><div class="bar-fill" style="width:1%"></div></div></div>
                    <div class="bar-row"><span>1</span><div class="bar-track"><div class="bar-fill" style="width:1%"></div></div></div>
                </div>
            </div>
            <p style="text-align:right;font-size:0.75em;color:#86868b;margin-top:6px;">19k 个评分</p>
        </div>

        <!-- 版本信息 -->
<?php if ($version): ?>
        <div class="changelog">
            <h3>新功能</h3>
            <p class="ver"><?= $version ?></p>
        </div>
<?php endif; ?>

        <!-- 应用信息 -->
        <div class="app-info">
            <h3>信息</h3>
            <ul>
<?php if ($size): ?>    <li><span>大小</span><span><?= $size ?></span></li><?php endif; ?>
<?php if ($description): ?><li><span>介绍</span><span><?= $description ?></span></li><?php endif; ?>
                <li><span>兼容性</span><span>需要 iOS 8.0 或更高版本</span></li>
                <li><span>语言</span><span>简体中文</span></li>
                <li><span>年龄分级</span><span>限18岁以上</span></li>
                <li><span>价格</span><span>免费</span></li>
            </ul>
        </div>

        <!-- 详细FAQ -->
<?php if ($certName): ?>
        <div class="faq">
            <strong>常见问题解答：</strong>
            <p>Q1：安装后提示"未受信任的开发者"怎么办？<br>
            A：进入「设置-通用-VPN与设备管理」→ 选择<span class="cert-name"><?= $certName ?></span>证书 → 点击信任</p>
            <p>Q2：文件无法下载？<br>
            A：请关闭广告拦截插件，或切换Safari浏览器重试</p>
            <p>Q3：安装后闪退？<br>
            A：重新信任<span class="cert-name"><?= $certName ?></span>证书，或卸载后重新安装</p>
        </div>
<?php endif; ?>

        <footer>
            <p>免责声明：本站仅提供下载托管，内容由开发者负责</p>
            <p style="margin-top:6px;"><a href="/" style="color:<?= $themeColor ?>">← 返回首页</a></p>
        </footer>
    </div>

    <!-- 安装引导弹窗 -->
    <div class="modal-overlay" id="guideModal">
        <div class="modal-box">
            <button class="modal-close" id="guideClose">✕</button>
            <div class="slider">
                <div class="slider-track" id="sliderTrack">
                    <div class="slider-slide">
                        <div style="font-size:4em;padding:20px 0;">👆</div>
                        <div class="text">第一步<br>点击上方「点击安装」按钮</div>
                    </div>
                    <div class="slider-slide">
                        <div style="font-size:4em;padding:20px 0;">🏠</div>
                        <div class="text">第二步<br>返回桌面等待下载完成<br><small style="color:#86868b;">此时直接打开会提示"未受信任的企业级开发者"</small></div>
                    </div>
                    <div class="slider-slide">
                        <div style="font-size:4em;padding:20px 0;">⚙️</div>
                        <div class="text">第三步<br>打开 设置 → 通用 → VPN与设备管理</div>
                    </div>
<?php if ($certName): ?>
                    <div class="slider-slide">
                        <div style="font-size:4em;padding:20px 0;">🔐</div>
                        <div class="text">第四步<br>在「企业级应用」部分，选择<br><span class="cert-name"><?= $certName ?></span></div>
                    </div>
                    <div class="slider-slide">
                        <div style="font-size:4em;padding:20px 0;">✅</div>
                        <div class="text">第五步<br>点击信任 → 输入锁屏密码<br>即可正常使用</div>
                    </div>
<?php endif; ?>
                </div>
            </div>
            <div class="slider-dots" id="sliderDots"></div>
            <div class="slider-btns">
                <button id="prevBtn" class="hidden">上一步</button>
                <button id="nextBtn">下一步</button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        // 微信/QQ检测
        var ua = navigator.userAgent.toLowerCase();
        var isWx = /micromessenger/i.test(ua);
        var isQQ = /qq/i.test(ua) && !/mqqbrowser/i.test(ua);
        if (isWx || isQQ) {
            document.getElementById('installBtn').href = 'javascript:void(0)';
            document.getElementById('installBtn').onclick = function(e) {
                e.preventDefault();
                document.getElementById('wechatTip').classList.add('show');
            };
        }

        // 安装按钮反馈
        document.getElementById('installBtn').addEventListener('click', function() {
            if (isWx || isQQ) return;
            var btn = this, text = btn.textContent;
            btn.textContent = '正在请求安装...';
            setTimeout(function() { btn.textContent = text; }, 2000);
        });

        // 轮播引导
        var track = document.getElementById('sliderTrack');
        var slides = track ? track.children : [];
        var total = slides.length;
        var current = 0;
        var dotsEl = document.getElementById('sliderDots');
        var prev = document.getElementById('prevBtn');
        var next = document.getElementById('nextBtn');

        // 初始化圆点
        for (var i = 0; i < total; i++) {
            var dot = document.createElement('span');
            if (i === 0) dot.className = 'active';
            dotsEl.appendChild(dot);
        }

        function updateSlider() {
            track.style.transform = 'translateX(-' + (current * 100) + '%)';
            var dots = dotsEl.children;
            for (var i = 0; i < dots.length; i++) {
                dots[i].className = i === current ? 'active' : '';
            }
            prev.className = current <= 0 ? 'hidden' : '';
            next.className = current >= total - 1 ? 'hidden' : '';
        }

        prev.onclick = function() { if (current > 0) { current--; updateSlider(); } };
        next.onclick = function() { if (current < total - 1) { current++; updateSlider(); } };

        // 弹窗控制
        var modal = document.getElementById('guideModal');
        var guideBtn = document.getElementById('showGuide');
        if (guideBtn) {
            guideBtn.onclick = function() {
                current = 0; updateSlider();
                modal.classList.add('show');
            };
        }
        document.getElementById('guideClose').onclick = function() { modal.classList.remove('show'); };
        modal.onclick = function(e) { if (e.target === modal) modal.classList.remove('show'); };
    })();
    </script>
</body>
</html>
