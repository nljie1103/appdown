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

// 来源 TOP10（按域名合并 http/https，识别来源类型）
$referers = $pdo->prepare("SELECT referer, COUNT(*) as c FROM page_visits WHERE visit_date = ? AND referer != '' AND referer != 'direct' GROUP BY referer ORDER BY c DESC");
$referers->execute([$today]);

// 先按域名聚合
$domainCounts = [];
while ($r = $referers->fetch()) {
    $parsed = parse_url($r['referer']);
    $host = $parsed['host'] ?? $r['referer'];
    $host = preg_replace('/^www\./', '', strtolower($host));
    if (!isset($domainCounts[$host])) {
        $domainCounts[$host] = ['count' => 0, 'raw' => $r['referer']];
    }
    $domainCounts[$host]['count'] += (int)$r['c'];
}

// 加上 direct 访问
$directCount = $pdo->prepare("SELECT COUNT(*) as c FROM page_visits WHERE visit_date = ? AND referer = 'direct'");
$directCount->execute([$today]);
$dc = (int)$directCount->fetch()['c'];
if ($dc > 0) {
    $domainCounts['direct'] = ['count' => $dc, 'raw' => 'direct'];
}

// 排序取 TOP10
arsort($domainCounts);
$topReferers = [];
$i = 0;
foreach (array_slice($domainCounts, 0, 10, true) as $host => $info) {
    $source = identifySource($host);
    $topReferers[] = [
        'referer'       => $host,
        'count'         => $info['count'],
        'source_name'   => $source['name'],
        'source_type'   => $source['type'],
        'source_icon'   => $source['icon'],
    ];
}

// 7天趋势
$dates = [];
$visitsTrend = [];
$downloadsTrend = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dates[] = $d;

    $v = $pdo->prepare('SELECT COUNT(*) as c FROM page_visits WHERE visit_date = ?');
    $v->execute([$d]);
    $visitsTrend[] = (int)$v->fetch()['c'];

    $dl = $pdo->prepare('SELECT COUNT(*) as c FROM download_clicks WHERE click_date = ?');
    $dl->execute([$d]);
    $downloadsTrend[] = (int)$dl->fetch()['c'];
}

// 总计数据
$totalVisits = (int)$pdo->query('SELECT COUNT(*) as c FROM page_visits')->fetch()['c'];
$totalDownloads = (int)$pdo->query('SELECT COUNT(*) as c FROM download_clicks')->fetch()['c'];

// 来源识别函数
function identifySource(string $host): array {
    $rules = [
        // 搜索引擎
        ['keywords' => ['google.'], 'name' => 'Google 搜索', 'type' => 'search', 'icon' => 'fab fa-google'],
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
            if (str_contains($host, $kw) || $host === $kw) {
                return ['name' => $rule['name'], 'type' => $rule['type'], 'icon' => $rule['icon']];
            }
        }
    }

    return ['name' => $host, 'type' => 'other', 'icon' => 'fas fa-globe'];
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
