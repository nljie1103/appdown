<?php
/**
 * Plist动态生成 - 根据应用配置生成iOS安装manifest
 * 访问: /api/plist.php?app=slug
 */

require_once __DIR__ . '/../includes/init.php';

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

// 构建完整URL（验证Host header防止注入）
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
// 只允许合法的域名字符
if (!preg_match('/^[a-zA-Z0-9._:-]+$/', $host)) {
    http_response_code(400);
    exit('invalid host');
}
$baseUrl = $scheme . '://' . $host;

$ipaUrl = $app['ios_ipa_url'];
// 如果是相对路径，补全为绝对URL
if (!preg_match('#^https?://#', $ipaUrl)) {
    $ipaUrl = $baseUrl . '/' . ltrim($ipaUrl, '/');
}

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
