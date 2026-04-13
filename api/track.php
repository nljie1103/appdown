<?php
/**
 * 公共API: 记录访问/下载事件
 * POST /api/track.php
 */

require_once __DIR__ . '/../includes/init.php';
require_method('POST');

$data = get_json_input();
$type = $data['type'] ?? '';
$ip = get_client_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? 'direct';
$date = today();

$pdo = get_db();

// 爬虫UA检测：开启时标记为 bot:xxx 来源
if (get_setting($pdo, 'filter_bots') === '1') {
    $botPatterns = [
        'Googlebot' => 'Googlebot', 'Baiduspider' => 'Baiduspider', 'bingbot' => 'Bingbot',
        'YandexBot' => 'YandexBot', 'Sogou' => 'Sogou', '360Spider' => '360Spider',
        'Bytespider' => 'Bytespider', 'python-requests' => 'python-requests',
        'curl/' => 'curl', 'Wget/' => 'wget', 'Scrapy/' => 'Scrapy',
        'AhrefsBot' => 'AhrefsBot', 'SemrushBot' => 'SemrushBot',
    ];
    foreach ($botPatterns as $needle => $label) {
        if (stripos($ua, $needle) !== false) {
            $referer = 'bot:' . $label;
            break;
        }
    }
}

// 简单限流: 同IP同分钟最多10次
$minute_key = date('Y-m-d H:i');
$count = $pdo->prepare("SELECT COUNT(*) as c FROM page_visits WHERE ip = ? AND created_at >= datetime('now', '-1 minute')
                         UNION ALL
                         SELECT COUNT(*) FROM download_clicks WHERE ip = ? AND created_at >= datetime('now', '-1 minute')");
$count->execute([$ip, $ip]);
$total = 0;
while ($r = $count->fetch()) {
    $total += $r['c'];
}
if ($total >= 10) {
    json_response(['ok' => false, 'error' => 'rate_limited'], 429);
}

if ($type === 'visit') {
    $page = $data['page'] ?? '/';
    $stmt = $pdo->prepare('INSERT INTO page_visits (ip, user_agent, referer, page_url, visit_date) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$ip, $ua, $referer, $page, $date]);
    json_response(['ok' => true]);
}

if ($type === 'download') {
    $app_slug = $data['app'] ?? '';
    $btn_type = $data['btn_type'] ?? $data['download_type'] ?? '';
    $href = $data['href'] ?? '';

    if (empty($app_slug) || empty($btn_type)) {
        json_response(['ok' => false, 'error' => 'missing fields'], 400);
    }

    $stmt = $pdo->prepare('INSERT INTO download_clicks (app_slug, btn_type, href, ip, user_agent, click_date) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$app_slug, $btn_type, $href, $ip, $ua, $date]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'invalid type'], 400);
