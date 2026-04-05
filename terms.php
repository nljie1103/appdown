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
    <title>用户协议 - <?= $s ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="policy-header">
            <a href="/" class="back-button">← 返回首页</a>
            <h1>用户协议</h1>
            <p>最后更新日期：<?= date('Y') ?>年1月1日 &nbsp;|&nbsp; 生效日期：<?= date('Y') ?>年1月1日</p>
        </header>

        <main class="policy-content">
            <section>
                <h2>引言</h2>
                <p>欢迎使用<?= $s ?>（以下简称"本应用"）。本协议是您与<?= $s ?>之间关于使用本应用服务的法律协议。使用本应用即表示您已阅读、理解并同意接受本协议的全部条款。若您不同意本协议，请停止使用本应用。</p>
            </section>

            <section>
                <h2>1. 服务说明</h2>
                <h3>1.1 服务内容</h3>
                <p>本应用为用户提供应用内容的浏览、搜索、下载等服务。具体服务内容以应用实际提供的为准。</p>
                <h3>1.2 服务形式</h3>
                <ul>
                    <li>本应用提供Android客户端、iOS客户端、Windows客户端及网页版等多平台服务</li>
                    <li>为保障服务质量，本应用可能会对部分功能进行调整、升级或下线</li>
                    <li>因系统维护或升级需要暂停服务时，我们将提前通过应用内公告通知用户</li>
                </ul>
            </section>

            <section>
                <h2>2. 账号管理</h2>
                <h3>2.1 账号注册</h3>
                <ul>
                    <li>您需要提供真实、准确的注册信息，并在信息变更时及时更新</li>
                    <li>每位用户仅可注册一个账号，不得使用他人信息注册</li>
                </ul>
                <h3>2.2 账号安全</h3>
                <ul>
                    <li>您应妥善保管账号和密码，因账号密码保管不善导致的损失由您自行承担</li>
                    <li>如发现账号被盗用或存在异常，请立即联系我们处理</li>
                    <li>禁止以任何形式转让、出租、借用账号给他人使用</li>
                </ul>
                <h3>2.3 账号注销</h3>
                <ul>
                    <li>您有权随时通过应用内设置申请注销账号</li>
                    <li>注销后，账号下的所有数据将被永久删除且不可恢复</li>
                </ul>
            </section>

            <section>
                <h2>3. 用户行为规范</h2>
                <p>您在使用本应用时应遵守以下规范：</p>
                <h3>3.1 您不得进行以下行为</h3>
                <ul>
                    <li>利用本应用从事违反法律法规的活动</li>
                    <li>未经授权对本应用进行反编译、逆向工程、拆解或修改</li>
                    <li>使用自动化工具、脚本或爬虫等手段批量访问或获取本应用内容</li>
                    <li>对本应用的服务器或网络进行干扰、破坏或施加不合理负荷</li>
                    <li>传播恶意软件、病毒或其他有害程序</li>
                </ul>
                <h3>3.2 违规处理</h3>
                <p>如发现您存在违反本协议的行为，我们有权采取包括但不限于警告、限制功能、封禁账号等措施。</p>
            </section>

            <section>
                <h2>4. 知识产权</h2>
                <ul>
                    <li>本应用中的软件、界面设计、图标、文字、图片及相关标识等均受知识产权法律保护</li>
                    <li>本应用中提供的内容之版权归原始权利人所有，本应用仅提供技术服务</li>
                    <li>未经书面许可，任何个人或组织不得以任何形式复制、传播或使用本应用的任何内容</li>
                </ul>
            </section>

            <section>
                <h2>5. 免责声明</h2>
                <ul>
                    <li><strong>服务中断：</strong>因不可抗力、网络故障、系统维护等原因导致的服务中断，我们不承担责任，但将尽快恢复服务</li>
                    <li><strong>第三方链接：</strong>本应用可能包含指向第三方网站的链接，我们不对第三方内容的安全性和合法性负责</li>
                </ul>
            </section>

            <section>
                <h2>6. 协议修订</h2>
                <p>我们保留修订本协议的权利。修订后的协议将通过网站公告等方式发布。若您在协议修订后继续使用本应用，即表示您同意修订后的协议。</p>
            </section>

            <section>
                <h2>7. 法律适用与争议解决</h2>
                <ul>
                    <li>本协议的订立、执行和解释均适用中华人民共和国法律</li>
                    <li>因本协议产生的争议，双方应友好协商解决；协商不成的，任何一方均有权向有管辖权的人民法院提起诉讼</li>
                </ul>
            </section>

            <section>
                <h2>8. 联系方式</h2>
                <p>如您对本协议有任何疑问或建议，可通过应用内反馈功能联系我们。</p>
            </section>
        </main>

        <footer class="policy-footer">
            <p style="font-weight: bold;"><?= $c ?></p>
        </footer>
    </div>
</body>
</html>
