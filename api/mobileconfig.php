<?php
/**
 * Mobileconfig动态生成 - 根据应用配置生成iOS WebClip描述文件
 * 访问: /api/mobileconfig.php?app=slug
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

if (!$app || empty($app['mc_url'])) {
    http_response_code(404);
    exit('app not found or no mobileconfig configured');
}

// 图标base64数据
$iconData = $app['mc_icon_data'] ?? '';

// 如果没有专用图标，尝试从 icon_url 读取
if (empty($iconData) && !empty($app['icon_url'])) {
    $iconPath = __DIR__ . '/../' . ltrim($app['icon_url'], '/');
    if (file_exists($iconPath)) {
        $iconData = base64_encode(file_get_contents($iconPath));
    }
}

// 再fallback到全局logo
if (empty($iconData)) {
    $logoUrl = '';
    $rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
    foreach ($rows as $r) {
        if ($r['setting_key'] === 'logo_url') $logoUrl = $r['setting_val'];
    }
    if ($logoUrl) {
        $logoPath = __DIR__ . '/../' . ltrim($logoUrl, '/');
        if (file_exists($logoPath)) {
            $iconData = base64_encode(file_get_contents($logoPath));
        }
    }
}

$displayName = $app['name'];
$bundleId = $app['mc_bundle_id'] ?: 'com.webclip.' . $app['slug'];
$url = $app['mc_url'];
$fullscreen = !empty($app['mc_fullscreen']);
$description = $app['mc_description'] ?: $displayName;

// 生成确定性UUID（基于slug）
$hash1 = md5($slug . '_webclip_payload');
$uuid1 = strtoupper(substr($hash1, 0, 8) . '-' . substr($hash1, 8, 4) . '-' . substr($hash1, 12, 4) . '-' . substr($hash1, 16, 4) . '-' . substr($hash1, 20, 12));
$hash2 = md5($slug . '_webclip_config');
$uuid2 = strtoupper(substr($hash2, 0, 8) . '-' . substr($hash2, 8, 4) . '-' . substr($hash2, 12, 4) . '-' . substr($hash2, 16, 4) . '-' . substr($hash2, 20, 12));

header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename="' . $slug . '.mobileconfig"');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>PayloadContent</key>
  <array>
    <dict>
      <key>FullScreen</key>
      <?= $fullscreen ? '<true/>' : '<false/>' ?>

<?php if (!empty($iconData)): ?>
      <key>Icon</key>
      <data><?= $iconData ?></data>
<?php endif; ?>
      <key>IgnoreManifestScope</key>
      <true/>
      <key>IsRemovable</key>
      <true/>
      <key>Label</key>
      <string><?= htmlspecialchars($displayName) ?></string>
      <key>PayloadDescription</key>
      <string><?= htmlspecialchars($description) ?></string>
      <key>PayloadDisplayName</key>
      <string><?= htmlspecialchars($displayName) ?></string>
      <key>PayloadIdentifier</key>
      <string><?= htmlspecialchars($bundleId) ?></string>
      <key>PayloadType</key>
      <string>com.apple.webClip.managed</string>
      <key>PayloadUUID</key>
      <string><?= $uuid1 ?></string>
      <key>PayloadVersion</key>
      <integer>1</integer>
      <key>Precomposed</key>
      <true/>
      <key>URL</key>
      <string><?= htmlspecialchars($url) ?></string>
    </dict>
  </array>
  <key>PayloadDisplayName</key>
  <string><?= htmlspecialchars($displayName) ?></string>
  <key>PayloadIdentifier</key>
  <string><?= htmlspecialchars($bundleId) ?></string>
  <key>PayloadRemovalDisallowed</key>
  <false/>
  <key>PayloadType</key>
  <string>Configuration</string>
  <key>PayloadUUID</key>
  <string><?= $uuid2 ?></string>
  <key>PayloadVersion</key>
  <integer>1</integer>
</dict>
</plist>
