<?php
require_once __DIR__ . '/includes/init.php';
$pdo = get_db();
$siteName = '';
$copyright = '';
try {
    $rows = $pdo->query("SELECT setting_key, setting_val FROM site_settings WHERE setting_key IN ('site_title','copyright')")->fetchAll();
    foreach ($rows as $r) {
        if ($r['setting_key'] === 'site_title') $siteName = $r['setting_val'];
        if ($r['setting_key'] === 'copyright') $copyright = $r['setting_val'];
    }
} catch (Exception $e) {}
if (empty($siteName)) $siteName = 'APP下载中心';
if (empty($copyright)) $copyright = '© ' . date('Y') . ' ' . $siteName;
$s = htmlspecialchars($siteName);
$c = htmlspecialchars($copyright);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>隐私政策 - <?= $s ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="policy-header">
            <a href="/" class="back-button">← 返回首页</a>
            <h1>隐私政策</h1>
            <p>最后更新日期：<?= date('Y') ?>年1月1日 &nbsp;|&nbsp; 生效日期：<?= date('Y') ?>年1月1日</p>
        </header>

        <main class="policy-content">
            <section>
                <h2>引言</h2>
                <p><?= $s ?>（以下简称"我们"）非常重视您的隐私保护。本隐私政策旨在向您说明我们如何收集、使用、存储、共享和保护您的个人信息，以及您对这些信息享有的权利。请在使用我们的服务前仔细阅读本政策。</p>
            </section>

            <section>
                <h2>1. 信息收集</h2>
                <p>我们可能收集以下类型的信息：</p>
                <h3>1.1 您主动提供的信息</h3>
                <ul>
                    <li>注册信息：用户名、邮箱地址等账号基本信息</li>
                    <li>反馈信息：您通过客服或反馈渠道提交的内容</li>
                </ul>
                <h3>1.2 自动收集的信息</h3>
                <ul>
                    <li>设备信息：设备型号、操作系统版本、屏幕分辨率、唯一设备标识符</li>
                    <li>网络信息：IP地址、网络类型（Wi-Fi/移动数据）、运营商信息</li>
                    <li>使用数据：应用启动时间、功能使用频率、浏览记录、搜索关键词</li>
                    <li>崩溃日志：应用异常时的错误信息，用于修复问题</li>
                </ul>
                <h3>1.3 我们不会收集的信息</h3>
                <ul>
                    <li>我们不会收集您的通讯录、短信、通话记录等敏感权限信息</li>
                    <li>我们不会收集您的银行卡号、身份证号等金融及身份证件信息</li>
                </ul>
            </section>

            <section>
                <h2>2. 信息使用</h2>
                <p>我们收集的信息将用于以下目的：</p>
                <ul>
                    <li><strong>提供核心服务：</strong>为您提供应用内容的浏览、搜索、下载等基本功能</li>
                    <li><strong>个性化体验：</strong>根据您的使用习惯推荐可能感兴趣的内容</li>
                    <li><strong>服务改进：</strong>分析使用数据以优化应用性能和用户体验</li>
                    <li><strong>问题排查：</strong>通过崩溃日志定位和修复技术问题</li>
                    <li><strong>安全保障：</strong>识别和防止异常行为，保护账号安全</li>
                    <li><strong>服务通知：</strong>向您发送版本更新、功能变更等重要通知</li>
                </ul>
            </section>

            <section>
                <h2>3. 信息存储</h2>
                <ul>
                    <li><strong>存储位置：</strong>您的个人信息存储在位于中国境内的服务器上</li>
                    <li><strong>存储期限：</strong>我们仅在实现服务目的所必需的期限内保留您的个人信息。当您注销账号后，我们将在30个工作日内删除或匿名化处理您的个人信息</li>
                    <li><strong>本地存储：</strong>部分数据（如浏览记录、搜索历史）存储在您的设备本地，您可随时在应用设置中清除</li>
                </ul>
            </section>

            <section>
                <h2>4. 信息共享与披露</h2>
                <p>我们承诺不会向第三方出售您的个人信息。仅在以下情况下可能共享：</p>
                <ul>
                    <li><strong>获得您的明确同意后：</strong>在征得您同意的前提下与第三方共享</li>
                    <li><strong>法律要求：</strong>根据法律法规、司法程序或政府主管部门的强制要求</li>
                    <li><strong>服务提供商：</strong>与帮助我们运营的服务提供商（如云服务、CDN加速）共享必要信息，这些服务商受严格保密协议约束</li>
                </ul>
            </section>

            <section>
                <h2>5. 信息保护</h2>
                <p>我们采取多项安全措施保护您的个人信息：</p>
                <ul>
                    <li><strong>传输加密：</strong>使用SSL/TLS协议加密数据传输</li>
                    <li><strong>存储加密：</strong>敏感数据采用加密存储方式</li>
                    <li><strong>访问控制：</strong>严格限制数据访问权限，仅授权人员可接触用户数据</li>
                    <li><strong>安全审计：</strong>定期进行安全评估和漏洞扫描</li>
                </ul>
                <p>尽管我们已尽最大努力保护您的信息安全，但请理解互联网环境并非绝对安全。如发生个人信息安全事件，我们将及时通知您并采取补救措施。</p>
            </section>

            <section>
                <h2>6. 您的权利</h2>
                <p>您对个人信息享有以下权利：</p>
                <ul>
                    <li><strong>查阅与更正：</strong>您可以在应用内查看和修改您的个人资料</li>
                    <li><strong>删除：</strong>您可以请求删除我们持有的您的个人信息</li>
                    <li><strong>撤回同意：</strong>您可以随时撤回此前给予的授权同意</li>
                    <li><strong>注销账号：</strong>您可以通过应用设置注销您的账号</li>
                </ul>
            </section>

            <section>
                <h2>7. 未成年人保护</h2>
                <p>我们重视对未成年人个人信息的保护。若您是未满18周岁的未成年人，请在监护人的指导下使用我们的服务。我们不会主动向未成年人推送不适宜的内容。</p>
            </section>

            <section>
                <h2>8. 政策更新</h2>
                <p>我们可能会不时修订本隐私政策。政策变更时，我们将通过应用内通知或网站公告等方式告知您。重大变更将以弹窗等显著方式提示。若您在政策更新后继续使用我们的服务，即视为同意更新后的政策。</p>
            </section>

            <section>
                <h2>9. 联系我们</h2>
                <p>如您对本隐私政策有任何疑问、意见或建议，可通过应用内反馈功能联系我们。</p>
                <p>我们将在收到您的反馈后15个工作日内予以回复。</p>
            </section>
        </main>

        <footer class="policy-footer">
            <p style="font-weight: bold;"><?= $c ?></p>
        </footer>
    </div>
</body>
</html>
