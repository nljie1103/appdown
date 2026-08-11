<?php
/**
 * SaaS Plist 动态生成。
 * 访问: /<tenant>/api/plist.php?app=slug
 */
require_once __DIR__ . '/../includes/init.php';
$tenant = require_tenant_context();

function encode_url_path(string $url): string {
    $parts = parse_url($url);
    if ($parts === false) return $url;
    if (isset($parts['path'])) {
        $segments = explode('/', $parts['path']);
        $segments = array_map('rawurlencode', $segments);
        $parts['path'] = implode('/', $segments);
    }
    $result = '';
    if (isset($parts['scheme'])) $result .= $parts['scheme'] . '://';
    if (isset($parts['user'])) {
        $result .= $parts['user'];
        if (isset($parts['pass'])) $result .= ':' . $parts['pass'];
        $result .= '@';
    }
    if (isset($parts['host'])) $result .= $parts['host'];
    if (isset($parts['port'])) $result .= ':' . $parts['port'];
    if (isset($parts['path'])) $result .= $parts['path'];
    if (isset($parts['query'])) $result .= '?' . $parts['query'];
    if (isset($parts['fragment'])) $result .= '#' . $parts['fragment'];
    return $result;
}

$appSlug = trim((string)($_GET['app'] ?? ''));
if ($appSlug === '' || !preg_match('/^[a-z0-9_-]+$/', $appSlug)) {
    http_response_code(404);
    exit('invalid app');
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM apps WHERE slug = ? AND is_active = 1');
$stmt->execute([$appSlug]);
$app = $stmt->fetch();
if (!$app || empty($app['ios_ipa_url'])) {
    http_response_code(404);
    exit('app not found or no IPA configured');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
if (!preg_match('/^[a-zA-Z0-9._:-]+$/', $host)) {
    http_response_code(400);
    exit('invalid host');
}

$siteUrl = '';
$siteUrlRow = $pdo->query("SELECT setting_val FROM site_settings WHERE setting_key = 'site_url'")->fetch();
if ($siteUrlRow && !empty($siteUrlRow['setting_val'])) {
    $siteUrl = rtrim((string)$siteUrlRow['setting_val'], '/');
}
if ($siteUrl === '') $siteUrl = $scheme . '://' . $host;

function tenant_absolute_http_url(string $value, string $base, string $tenantSlug): string {
    $value = tenant_absolute_asset_url($value, $tenantSlug);
    if ($value === '') return '';
    if (preg_match('#^https?://#i', $value)) return $value;
    if (str_starts_with($value, '//')) return (($GLOBALS['scheme'] ?? 'https') . ':' . $value);
    return rtrim($base, '/') . '/' . ltrim($value, '/');
}

$ipaUrl = encode_url_path(tenant_absolute_http_url((string)$app['ios_ipa_url'], $siteUrl, $tenant['slug']));
$iconUrl = (string)($app['icon_url'] ?? '');
if ($iconUrl === '') {
    $logo = $pdo->query("SELECT setting_val FROM site_settings WHERE setting_key='logo_url'")->fetchColumn();
    $iconUrl = (string)($logo ?: '');
}
if ($iconUrl !== '') $iconUrl = encode_url_path(tenant_absolute_http_url($iconUrl, $siteUrl, $tenant['slug']));

$bundleId = $app['ios_bundle_id'] ?: 'com.app.' . $app['slug'];
$bundleVersion = $app['ios_version'] ?: '1.0.0';
$title = $app['name'];

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict><key>items</key><array><dict><key>assets</key><array>
<dict><key>kind</key><string>software-package</string><key>url</key><string><![CDATA[<?= $ipaUrl ?>]]></string></dict>
<?php if ($iconUrl): ?>
<dict><key>kind</key><string>display-image</string><key>needs-shine</key><integer>0</integer><key>url</key><string><![CDATA[<?= $iconUrl ?>]]></string></dict>
<dict><key>kind</key><string>full-size-image</string><key>needs-shine</key><true/><key>url</key><string><![CDATA[<?= $iconUrl ?>]]></string></dict>
<?php endif; ?>
</array><key>metadata</key><dict>
<key>bundle-identifier</key><string><?= htmlspecialchars($bundleId, ENT_XML1 | ENT_QUOTES, 'UTF-8') ?></string>
<key>bundle-version</key><string><![CDATA[<?= $bundleVersion ?>]]></string>
<key>kind</key><string>software</string>
<key>title</key><string><![CDATA[<?= $title ?>]]></string>
</dict></dict></array></dict></plist>
