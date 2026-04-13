<?php
/**
 * Plist动态生成 - 根据应用配置生成iOS安装manifest
 * 访问: /api/plist.php?app=slug
 */

require_once __DIR__ . '/../includes/init.php';

/**
 * 对URL中的非ASCII字符（中文等）进行编码，保留URL结构字符
 * 例：https://example.com/uploads/测试.ipa → https://example.com/uploads/%E6%B5%8B%E8%AF%95.ipa
 */
function encode_url_path(string $url): string {
    $parts = parse_url($url);
    if (isset($parts['path'])) {
        $segments = explode('/', $parts['path']);
        $segments = array_map('rawurlencode', $segments);
        $parts['path'] = implode('/', $segments);
    }
    // 重建URL
    $result = '';
    if (isset($parts['scheme'])) $result .= $parts['scheme'] . '://';
    if (isset($parts['host'])) $result .= $parts['host'];
    if (isset($parts['port'])) $result .= ':' . $parts['port'];
    if (isset($parts['path'])) $result .= $parts['path'];
    if (isset($parts['query'])) $result .= '?' . $parts['query'];
    if (isset($parts['fragment'])) $result .= '#' . $parts['fragment'];
    return $result;
}

$slug = trim($_GET['app'] ?? '');
if (empty($slug) || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
    http_response_code(404);
    exit('invalid app');
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM apps WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$app = $stmt->fetch();

if (!$app || empty($app['ios_ipa_url'])) {
    http_response_code(404);
    exit('app not found or no IPA configured');
}

// 构建完整URL（优先使用站点配置的URL，防止Host header注入）
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$siteUrl = '';
$siteUrlRow = $pdo->query("SELECT setting_val FROM site_settings WHERE setting_key = 'site_url'")->fetch();
if ($siteUrlRow && !empty($siteUrlRow['setting_val'])) {
    $siteUrl = rtrim($siteUrlRow['setting_val'], '/');
}
if (empty($siteUrl)) {
    $host = $_SERVER['HTTP_HOST'];
    if (!preg_match('/^[a-zA-Z0-9._:-]+$/', $host)) {
        http_response_code(400);
        exit('invalid host');
    }
    $siteUrl = $scheme . '://' . $host;
}
$baseUrl = $siteUrl;

$ipaUrl = $app['ios_ipa_url'];
// 如果是相对路径，补全为绝对URL
if (!preg_match('#^https?://#', $ipaUrl)) {
    $ipaUrl = $baseUrl . '/' . ltrim($ipaUrl, '/');
}
// 对URL中的非ASCII字符编码（兼容Safari低版本不支持中文URL）
$ipaUrl = encode_url_path($ipaUrl);

// 图标URL
$iconUrl = $app['icon_url'] ?: '';
if ($iconUrl && !preg_match('#^https?://#', $iconUrl)) {
    $iconUrl = $baseUrl . '/' . ltrim($iconUrl, '/');
}
// 获取全局logo作为fallback
if (!$iconUrl) {
    $settings = [];
    $rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
    foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_val'];
    $iconUrl = $settings['logo_url'] ?? '';
    if ($iconUrl && !preg_match('#^https?://#', $iconUrl)) {
        $iconUrl = $baseUrl . '/' . ltrim($iconUrl, '/');
    }
}
if ($iconUrl) {
    $iconUrl = encode_url_path($iconUrl);
}

$bundleId = $app['ios_bundle_id'] ?: 'com.app.' . $app['slug'];
$bundleVersion = $app['ios_version'] ?: '1.0.0';
$title = $app['name'];

header('Content-Type: application/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
  <dict>
    <key>items</key>
    <array>
      <dict>
        <key>assets</key>
        <array>
          <dict>
            <key>kind</key>
            <string>software-package</string>
            <key>url</key>
            <string><![CDATA[<?= $ipaUrl ?>]]></string>
          </dict>
<?php if ($iconUrl): ?>
          <dict>
            <key>kind</key>
            <string>display-image</string>
            <key>needs-shine</key>
            <integer>0</integer>
            <key>url</key>
            <string><![CDATA[<?= $iconUrl ?>]]></string>
          </dict>
          <dict>
            <key>kind</key>
            <string>full-size-image</string>
            <key>needs-shine</key>
            <true/>
            <key>url</key>
            <string><![CDATA[<?= $iconUrl ?>]]></string>
          </dict>
<?php endif; ?>
        </array>
        <key>metadata</key>
        <dict>
          <key>bundle-identifier</key>
          <string><?= htmlspecialchars($bundleId) ?></string>
          <key>bundle-version</key>
          <string><![CDATA[<?= $bundleVersion ?>]]></string>
          <key>kind</key>
          <string>software</string>
          <key>title</key>
          <string><![CDATA[<?= $title ?>]]></string>
        </dict>
      </dict>
    </array>
  </dict>
</plist>
