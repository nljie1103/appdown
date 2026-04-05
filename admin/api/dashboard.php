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
$dl_detail = $pdo->prepare('SELECT app_slug, btn_type, COUNT(*) as c FROM download_clicks WHERE click_date = ? GROUP BY app_slug, btn_type ORDER BY app_slug, btn_type');
$dl_detail->execute([$today]);
$todayDlByApp = [];
while ($r = $dl_detail->fetch()) {
    if (!isset($todayDlByApp[$r['app_slug']])) {
        $todayDlByApp[$r['app_slug']] = [];
    }
    $todayDlByApp[$r['app_slug']][$r['btn_type']] = (int)$r['c'];
}

// 来源 TOP10
$referers = $pdo->prepare("SELECT referer, COUNT(*) as c FROM page_visits WHERE visit_date = ? AND referer != '' GROUP BY referer ORDER BY c DESC LIMIT 10");
$referers->execute([$today]);
$topReferers = [];
while ($r = $referers->fetch()) {
    $domain = $r['referer'];
    if ($domain !== 'direct') {
        $parsed = parse_url($domain);
        $domain = $parsed['host'] ?? $domain;
    }
    $topReferers[] = ['referer' => $domain, 'count' => (int)$r['c']];
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
