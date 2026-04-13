<?php
/**
 * 仪表盘API - 统计数据
 * GET /admin/api/dashboard.php
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();
require_method('GET');

$pdo = get_db();
$today = today();

// 今日访问
$visits_today = $pdo->prepare('SELECT COUNT(*) as c FROM page_visits WHERE visit_date = ?');
$visits_today->execute([$today]);
$todayVisits = (int)$visits_today->fetch()['c'];

// 昨日访问（用于对比）
$yesterday = date('Y-m-d', strtotime('-1 day'));
$visits_yesterday = $pdo->prepare('SELECT COUNT(*) as c FROM page_visits WHERE visit_date = ?');
$visits_yesterday->execute([$yesterday]);
$yesterdayVisits = (int)$visits_yesterday->fetch()['c'];

// 今日下载总数
$dl_today = $pdo->prepare('SELECT COUNT(*) as c FROM download_clicks WHERE click_date = ?');
$dl_today->execute([$today]);
$todayDownloads = (int)$dl_today->fetch()['c'];

// 今日下载按app和类型分组
$dl_detail = $pdo->prepare('SELECT d.app_slug, d.btn_type, COUNT(*) as c, a.name as app_name FROM download_clicks d LEFT JOIN apps a ON a.slug = d.app_slug WHERE d.click_date = ? GROUP BY d.app_slug, d.btn_type ORDER BY d.app_slug, d.btn_type');
$dl_detail->execute([$today]);
$todayDlByApp = [];
while ($r = $dl_detail->fetch()) {
    $name = $r['app_name'] ?: $r['app_slug'];
    if (!isset($todayDlByApp[$name])) {
        $todayDlByApp[$name] = [];
    }
    $todayDlByApp[$name][$r['btn_type']] = (int)$r['c'];
}

// 来源 TOP10（域名 + UA 综合识别）
// 获取当前站点域名，用于排除自身引用
$siteHost = preg_replace('/^www\./', '', strtolower($_SERVER['HTTP_HOST'] ?? ''));

$allVisits = $pdo->prepare("SELECT referer, user_agent FROM page_visits WHERE visit_date = ?");
$allVisits->execute([$today]);

// UA 特征规则（应用内浏览器 / 客户端）
$uaRules = [
    ['pattern' => '/MicroMessenger/i', 'key' => 'ua:wechat'],
    ['pattern' => '/\bQQ[\b\/]/i', 'key' => 'ua:qq', 'exclude' => '/MQQBrowser/i'],
    ['pattern' => '/Weibo/i', 'key' => 'ua:weibo'],
    ['pattern' => '/DingTalk/i', 'key' => 'ua:dingtalk'],
    ['pattern' => '/AlipayClient/i', 'key' => 'ua:alipay'],
    ['pattern' => '/BytedanceWebview|ToutiaoMicroApp|aweme/i', 'key' => 'ua:douyin'],
    ['pattern' => '/XiaoHongShu/i', 'key' => 'ua:xiaohongshu'],
    ['pattern' => '/BaiduBoxApp/i', 'key' => 'ua:baiduapp'],
    ['pattern' => '/Douban/i', 'key' => 'ua:douban'],
    ['pattern' => '/FBAN|FBAV/i', 'key' => 'ua:facebook'],
    ['pattern' => '/Instagram/i', 'key' => 'ua:instagram'],
    ['pattern' => '/Line\//i', 'key' => 'ua:line'],
    ['pattern' => '/Telegram/i', 'key' => 'ua:telegram'],
];

$sourceCounts = [];
while ($r = $allVisits->fetch()) {
    $referer = $r['referer'];
    $ua = $r['user_agent'];

    // 1. 先提取 referer 域名
    $host = 'direct';
    if ($referer !== 'direct' && $referer !== '') {
        $parsed = parse_url($referer);
        $host = $parsed['host'] ?? $referer;
        $host = preg_replace('/^www\./', '', strtolower($host));
    }

    // 2. 如果 referer 是直接访问或来自自身站点，尝试用 UA 识别来源
    $key = $host;
    if ($host === 'direct' || $host === $siteHost) {
        $uaDetected = false;
        foreach ($uaRules as $rule) {
            if (isset($rule['exclude']) && preg_match($rule['exclude'], $ua)) continue;
            if (preg_match($rule['pattern'], $ua)) {
                $key = $rule['key'];
                $uaDetected = true;
                break;
            }
        }
        if (!$uaDetected) {
            $key = 'direct';
        }
    }

    if (!isset($sourceCounts[$key])) {
        $sourceCounts[$key] = 0;
    }
    $sourceCounts[$key]++;
}

// 排序取 TOP10
arsort($sourceCounts);
$topReferers = [];
foreach (array_slice($sourceCounts, 0, 10, true) as $key => $count) {
    $source = identifySource($key);
    $topReferers[] = [
        'referer'       => $key,
        'count'         => $count,
        'source_name'   => $source['name'],
        'source_type'   => $source['type'],
        'source_icon'   => $source['icon'],
    ];
}

// 7天趋势（2条查询代替14条）
$dates = [];
$visitsTrend = [];
$downloadsTrend = [];
$dateStart = date('Y-m-d', strtotime('-6 days'));
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dates[] = $d;
    $visitsTrend[$d] = 0;
    $downloadsTrend[$d] = 0;
}

$vStmt = $pdo->prepare('SELECT visit_date, COUNT(*) as c FROM page_visits WHERE visit_date >= ? GROUP BY visit_date');
$vStmt->execute([$dateStart]);
while ($r = $vStmt->fetch()) {
    if (isset($visitsTrend[$r['visit_date']])) $visitsTrend[$r['visit_date']] = (int)$r['c'];
}

$dStmt = $pdo->prepare('SELECT click_date, COUNT(*) as c FROM download_clicks WHERE click_date >= ? GROUP BY click_date');
$dStmt->execute([$dateStart]);
while ($r = $dStmt->fetch()) {
    if (isset($downloadsTrend[$r['click_date']])) $downloadsTrend[$r['click_date']] = (int)$r['c'];
}

$visitsTrend = array_values($visitsTrend);
$downloadsTrend = array_values($downloadsTrend);

// 总计数据
$totalVisits = (int)$pdo->query('SELECT COUNT(*) as c FROM page_visits')->fetch()['c'];
$totalDownloads = (int)$pdo->query('SELECT COUNT(*) as c FROM download_clicks')->fetch()['c'];

// 来源识别函数（支持域名和 UA 标识 key）
function identifySource(string $key): array {
    // UA 标识 key 直接映射
    $uaMap = [
        'ua:wechat'      => ['name' => '微信', 'type' => 'social', 'icon' => 'fab fa-weixin'],
        'ua:qq'          => ['name' => 'QQ', 'type' => 'social', 'icon' => 'fab fa-qq'],
        'ua:weibo'       => ['name' => '微博', 'type' => 'social', 'icon' => 'fab fa-weibo'],
        'ua:dingtalk'    => ['name' => '钉钉', 'type' => 'social', 'icon' => 'fas fa-comment-dots'],
        'ua:alipay'      => ['name' => '支付宝', 'type' => 'social', 'icon' => 'fab fa-alipay'],
        'ua:douyin'      => ['name' => '抖音', 'type' => 'social', 'icon' => 'fab fa-tiktok'],
        'ua:xiaohongshu' => ['name' => '小红书', 'type' => 'social', 'icon' => 'fas fa-book-open'],
        'ua:baiduapp'    => ['name' => '百度APP', 'type' => 'social', 'icon' => 'fas fa-paw'],
        'ua:douban'      => ['name' => '豆瓣', 'type' => 'social', 'icon' => 'fas fa-seedling'],
        'ua:facebook'    => ['name' => 'Facebook', 'type' => 'social', 'icon' => 'fab fa-facebook'],
        'ua:instagram'   => ['name' => 'Instagram', 'type' => 'social', 'icon' => 'fab fa-instagram'],
        'ua:line'        => ['name' => 'LINE', 'type' => 'social', 'icon' => 'fab fa-line'],
        'ua:telegram'    => ['name' => 'Telegram', 'type' => 'social', 'icon' => 'fab fa-telegram'],
    ];
    if (isset($uaMap[$key])) return $uaMap[$key];

    // 爬虫来源识别（bot:xxx 格式）
    if (strpos($key, 'bot:') === 0) {
        $botName = substr($key, 4);
        $botIcons = [
            'Googlebot' => 'fab fa-google', 'Baiduspider' => 'fas fa-paw',
            'Bingbot' => 'fab fa-microsoft', 'YandexBot' => 'fab fa-yandex-international',
            'Bytespider' => 'fab fa-tiktok', 'python-requests' => 'fab fa-python',
            'curl' => 'fas fa-terminal', 'wget' => 'fas fa-terminal', 'Scrapy' => 'fab fa-python',
        ];
        return ['name' => $botName . ' (爬虫)', 'type' => 'bot', 'icon' => $botIcons[$botName] ?? 'fas fa-robot'];
    }

    // 域名匹配规则
    $rules = [
        // 搜索引擎
        ['keywords' => ['google.com', 'google.co.', 'google.com.'], 'name' => 'Google 搜索', 'type' => 'search', 'icon' => 'fab fa-google'],
        ['keywords' => ['baidu.com', 'baidu.cn'], 'name' => '百度搜索', 'type' => 'search', 'icon' => 'fas fa-paw'],
        ['keywords' => ['bing.com'], 'name' => 'Bing 搜索', 'type' => 'search', 'icon' => 'fab fa-microsoft'],
        ['keywords' => ['sogou.com'], 'name' => '搜狗搜索', 'type' => 'search', 'icon' => 'fas fa-search'],
        ['keywords' => ['so.com', '360.cn'], 'name' => '360 搜索', 'type' => 'search', 'icon' => 'fas fa-search'],
        ['keywords' => ['yahoo.com', 'yahoo.co'], 'name' => 'Yahoo 搜索', 'type' => 'search', 'icon' => 'fab fa-yahoo'],
        ['keywords' => ['yandex.'], 'name' => 'Yandex', 'type' => 'search', 'icon' => 'fab fa-yandex'],
        ['keywords' => ['duckduckgo.com'], 'name' => 'DuckDuckGo', 'type' => 'search', 'icon' => 'fas fa-search'],
        ['keywords' => ['toutiao.com', 'bytedance.com'], 'name' => '头条搜索', 'type' => 'search', 'icon' => 'fas fa-search'],
        ['keywords' => ['sm.cn'], 'name' => '神马搜索', 'type' => 'search', 'icon' => 'fas fa-search'],
        // 社交平台
        ['keywords' => ['weixin.qq.com', 'wechat.com', 'wx.'], 'name' => '微信', 'type' => 'social', 'icon' => 'fab fa-weixin'],
        ['keywords' => ['qq.com', 'im.qq.com'], 'name' => 'QQ', 'type' => 'social', 'icon' => 'fab fa-qq'],
        ['keywords' => ['weibo.com', 'weibo.cn', 't.cn'], 'name' => '微博', 'type' => 'social', 'icon' => 'fab fa-weibo'],
        ['keywords' => ['douyin.com', 'tiktok.com'], 'name' => '抖音/TikTok', 'type' => 'social', 'icon' => 'fab fa-tiktok'],
        ['keywords' => ['xiaohongshu.com', 'xhslink.com'], 'name' => '小红书', 'type' => 'social', 'icon' => 'fas fa-book-open'],
        ['keywords' => ['zhihu.com'], 'name' => '知乎', 'type' => 'social', 'icon' => 'fab fa-zhihu'],
        ['keywords' => ['bilibili.com', 'b23.tv'], 'name' => 'B站', 'type' => 'social', 'icon' => 'fab fa-bilibili'],
        ['keywords' => ['douban.com'], 'name' => '豆瓣', 'type' => 'social', 'icon' => 'fas fa-seedling'],
        ['keywords' => ['telegram.org', 't.me'], 'name' => 'Telegram', 'type' => 'social', 'icon' => 'fab fa-telegram'],
        ['keywords' => ['twitter.com', 'x.com'], 'name' => 'X/Twitter', 'type' => 'social', 'icon' => 'fab fa-x-twitter'],
        ['keywords' => ['facebook.com', 'fb.com'], 'name' => 'Facebook', 'type' => 'social', 'icon' => 'fab fa-facebook'],
        ['keywords' => ['instagram.com'], 'name' => 'Instagram', 'type' => 'social', 'icon' => 'fab fa-instagram'],
        ['keywords' => ['reddit.com'], 'name' => 'Reddit', 'type' => 'social', 'icon' => 'fab fa-reddit'],
        ['keywords' => ['discord.com', 'discord.gg'], 'name' => 'Discord', 'type' => 'social', 'icon' => 'fab fa-discord'],
        ['keywords' => ['linkedin.com'], 'name' => 'LinkedIn', 'type' => 'social', 'icon' => 'fab fa-linkedin'],
        ['keywords' => ['whatsapp.com'], 'name' => 'WhatsApp', 'type' => 'social', 'icon' => 'fab fa-whatsapp'],
        ['keywords' => ['line.me'], 'name' => 'LINE', 'type' => 'social', 'icon' => 'fab fa-line'],
        // 开发者平台
        ['keywords' => ['github.com'], 'name' => 'GitHub', 'type' => 'dev', 'icon' => 'fab fa-github'],
        ['keywords' => ['gitee.com'], 'name' => 'Gitee', 'type' => 'dev', 'icon' => 'fas fa-code-branch'],
        ['keywords' => ['csdn.net'], 'name' => 'CSDN', 'type' => 'dev', 'icon' => 'fas fa-code'],
        ['keywords' => ['juejin.cn'], 'name' => '掘金', 'type' => 'dev', 'icon' => 'fas fa-gem'],
        ['keywords' => ['v2ex.com'], 'name' => 'V2EX', 'type' => 'dev', 'icon' => 'fas fa-comments'],
        // 其他
        ['keywords' => ['direct'], 'name' => '直接访问', 'type' => 'direct', 'icon' => 'fas fa-sign-in-alt'],
    ];

    foreach ($rules as $rule) {
        foreach ($rule['keywords'] as $kw) {
            if (strpos($key, $kw) !== false || $key === $kw) {
                return ['name' => $rule['name'], 'type' => $rule['type'], 'icon' => $rule['icon']];
            }
        }
    }

    return ['name' => $key, 'type' => 'other', 'icon' => 'fas fa-globe'];
}

json_response([
    'today_visits'          => $todayVisits,
    'yesterday_visits'      => $yesterdayVisits,
    'today_downloads'       => $todayDlByApp,
    'today_downloads_total' => $todayDownloads,
    'total_visits'          => $totalVisits,
    'total_downloads'       => $totalDownloads,
    'top_referers'          => $topReferers,
    'trend_7day' => [
        'dates'     => $dates,
        'visits'    => $visitsTrend,
        'downloads' => $downloadsTrend,
    ],
]);
