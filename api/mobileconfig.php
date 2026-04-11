<?php
/**
 * Mobileconfig动态生成 - 根据应用配置生成iOS WebClip描述文件
 * 支持SSL证书签名（应用级 > 全局 > 无签名）
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
    $globalSettings = [];
    foreach ($rows as $r) {
        $globalSettings[$r['setting_key']] = $r['setting_val'];
        if ($r['setting_key'] === 'logo_url') $logoUrl = $r['setting_val'];
    }
    if ($logoUrl) {
        $logoPath = __DIR__ . '/../' . ltrim($logoUrl, '/');
        if (file_exists($logoPath)) {
            $iconData = base64_encode(file_get_contents($logoPath));
        }
    }
} else {
    // 也加载全局设置（签名可能用到）
    $rows = $pdo->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
    $globalSettings = [];
    foreach ($rows as $r) {
        $globalSettings[$r['setting_key']] = $r['setting_val'];
    }
}

$displayName = $app['name'];
$bundleId = $app['mc_bundle_id'] ?: 'com.webclip.' . $app['slug'];
$url = $app['mc_url'];
$fullscreen = !empty($app['mc_fullscreen']);
$description = $app['mc_description'] ?: $displayName;

// 组织名称: 应用级 > 全局 > 站点标题
$organization = $app['mc_payload_org'] ?? '';
if (empty($organization)) {
    $organization = $globalSettings['mc_payload_org'] ?? '';
}
if (empty($organization)) {
    $organization = $globalSettings['site_title'] ?? '';
}

// 生成确定性UUID（基于slug）
$hash1 = md5($slug . '_webclip_payload');
$uuid1 = strtoupper(substr($hash1, 0, 8) . '-' . substr($hash1, 8, 4) . '-' . substr($hash1, 12, 4) . '-' . substr($hash1, 16, 4) . '-' . substr($hash1, 20, 12));
$hash2 = md5($slug . '_webclip_config');
$uuid2 = strtoupper(substr($hash2, 0, 8) . '-' . substr($hash2, 8, 4) . '-' . substr($hash2, 12, 4) . '-' . substr($hash2, 16, 4) . '-' . substr($hash2, 20, 12));

// ========== 生成未签名的 plist XML ==========
ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">' . "\n";
echo '<plist version="1.0">' . "\n";
echo '<dict>' . "\n";
echo '  <key>PayloadContent</key>' . "\n";
echo '  <array>' . "\n";
echo '    <dict>' . "\n";
echo '      <key>FullScreen</key>' . "\n";
echo '      ' . ($fullscreen ? '<true/>' : '<false/>') . "\n";
if (!empty($iconData)) {
    echo '      <key>Icon</key>' . "\n";
    echo '      <data>' . $iconData . '</data>' . "\n";
}
echo '      <key>IgnoreManifestScope</key>' . "\n";
echo '      <true/>' . "\n";
echo '      <key>IsRemovable</key>' . "\n";
echo '      <true/>' . "\n";
echo '      <key>Label</key>' . "\n";
echo '      <string>' . htmlspecialchars($displayName) . '</string>' . "\n";
echo '      <key>PayloadDescription</key>' . "\n";
echo '      <string>' . htmlspecialchars($description) . '</string>' . "\n";
echo '      <key>PayloadDisplayName</key>' . "\n";
echo '      <string>' . htmlspecialchars($displayName) . '</string>' . "\n";
echo '      <key>PayloadIdentifier</key>' . "\n";
echo '      <string>' . htmlspecialchars($bundleId) . '</string>' . "\n";
echo '      <key>PayloadType</key>' . "\n";
echo '      <string>com.apple.webClip.managed</string>' . "\n";
echo '      <key>PayloadUUID</key>' . "\n";
echo '      <string>' . $uuid1 . '</string>' . "\n";
echo '      <key>PayloadVersion</key>' . "\n";
echo '      <integer>1</integer>' . "\n";
echo '      <key>Precomposed</key>' . "\n";
echo '      <true/>' . "\n";
echo '      <key>URL</key>' . "\n";
echo '      <string>' . htmlspecialchars($url) . '</string>' . "\n";
echo '    </dict>' . "\n";
echo '  </array>' . "\n";
echo '  <key>PayloadDescription</key>' . "\n";
echo '  <string>' . htmlspecialchars($description) . '</string>' . "\n";
echo '  <key>PayloadDisplayName</key>' . "\n";
echo '  <string>' . htmlspecialchars($displayName) . '</string>' . "\n";
echo '  <key>PayloadIdentifier</key>' . "\n";
echo '  <string>' . htmlspecialchars($bundleId) . '</string>' . "\n";
if (!empty($organization)) {
    echo '  <key>PayloadOrganization</key>' . "\n";
    echo '  <string>' . htmlspecialchars($organization) . '</string>' . "\n";
}
echo '  <key>PayloadRemovalDisallowed</key>' . "\n";
echo '  <false/>' . "\n";
echo '  <key>PayloadType</key>' . "\n";
echo '  <string>Configuration</string>' . "\n";
echo '  <key>PayloadUUID</key>' . "\n";
echo '  <string>' . $uuid2 . '</string>' . "\n";
echo '  <key>PayloadVersion</key>' . "\n";
echo '  <integer>1</integer>' . "\n";
echo '</dict>' . "\n";
echo '</plist>' . "\n";
$unsignedXml = ob_get_clean();

// ========== 签名逻辑 ==========
$certPem = '';
$keyPem = '';
$chainPem = '';

// 优先使用应用级证书
$appSignMode = $app['mc_sign_mode'] ?? '';
if (!empty($appSignMode)) {
    $certPem = resolve_cert_content($appSignMode, $app['mc_sign_cert'] ?? '');
    $keyPem = resolve_cert_content($appSignMode, $app['mc_sign_key'] ?? '');
    $chainPem = resolve_cert_content($appSignMode, $app['mc_sign_chain'] ?? '');
}

// Fallback到全局证书
if (empty($certPem) || empty($keyPem)) {
    $globalMode = $globalSettings['mc_sign_mode'] ?? '';
    if (!empty($globalMode)) {
        $globalCert = $globalSettings['mc_sign_cert'] ?? '';
        $globalKey = $globalSettings['mc_sign_key'] ?? '';
        $globalChain = $globalSettings['mc_sign_chain'] ?? '';
        $certPem = resolve_cert_content($globalMode, $globalCert);
        $keyPem = resolve_cert_content($globalMode, $globalKey);
        $chainPem = resolve_cert_content($globalMode, $globalChain);
    }
}

// 尝试签名
if (!empty($certPem) && !empty($keyPem) && function_exists('openssl_pkcs7_sign')) {
    $signed = sign_mobileconfig($unsignedXml, $certPem, $keyPem, $chainPem);
    if ($signed !== false) {
        header('Content-Type: application/x-apple-aspen-config');
        header('Content-Disposition: attachment; filename="' . $slug . '.mobileconfig"');
        header('Content-Length: ' . strlen($signed));
        echo $signed;
        exit;
    }
    // 签名失败则回退到未签名输出
}

// 未签名输出
header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename="' . $slug . '.mobileconfig"');
echo $unsignedXml;
exit;

// ========== 辅助函数 ==========

/**
 * 根据模式获取证书/私钥内容
 */
function resolve_cert_content(string $mode, string $value): string {
    $value = trim($value);
    if (empty($value)) return '';

    switch ($mode) {
        case 'text':
            return $value;
        case 'path':
            if (file_exists($value) && is_readable($value)) {
                return file_get_contents($value);
            }
            return '';
        case 'upload':
            $path = __DIR__ . '/../' . ltrim($value, '/');
            if (file_exists($path) && is_readable($path)) {
                return file_get_contents($path);
            }
            return '';
        default:
            return '';
    }
}

/**
 * 使用 OpenSSL PKCS#7 签名 mobileconfig
 * 返回 DER 格式的完整签名数据（包含原始内容+签名），失败返回 false
 */
function sign_mobileconfig(string $xml, string $certPem, string $keyPem, string $chainPem = ''): string|false {
    $tmpDir = sys_get_temp_dir();
    $tmpIn = tempnam($tmpDir, 'mc_in_');
    $tmpOut = tempnam($tmpDir, 'mc_out_');
    $tmpCert = tempnam($tmpDir, 'mc_cert_');
    $tmpKey = tempnam($tmpDir, 'mc_key_');

    file_put_contents($tmpIn, $xml);
    file_put_contents($tmpCert, $certPem);
    file_put_contents($tmpKey, $keyPem);

    $extraCertsFile = null;
    if (!empty($chainPem)) {
        $extraCertsFile = tempnam($tmpDir, 'mc_chain_');
        file_put_contents($extraCertsFile, $chainPem);
    }

    // PKCS7_BINARY: 不做 MIME 文本转换
    // 不使用 PKCS7_DETACHED: 签名包含原始数据（iOS 要求）
    $result = openssl_pkcs7_sign(
        $tmpIn,
        $tmpOut,
        'file://' . $tmpCert,
        'file://' . $tmpKey,
        [],
        PKCS7_BINARY | PKCS7_NOATTR,
        $extraCertsFile
    );

    @unlink($tmpIn);
    @unlink($tmpCert);
    @unlink($tmpKey);
    if ($extraCertsFile) @unlink($extraCertsFile);

    if (!$result) {
        @unlink($tmpOut);
        return false;
    }

    // openssl_pkcs7_sign 输出 S/MIME (PEM) 格式
    // 需要用 openssl 将其转换为 DER 格式
    $smime = file_get_contents($tmpOut);
    @unlink($tmpOut);

    if (empty($smime)) return false;

    // 使用 openssl smime 命令转换 S/MIME -> DER
    $tmpSmime = tempnam($tmpDir, 'mc_smime_');
    $tmpDer = tempnam($tmpDir, 'mc_der_');
    file_put_contents($tmpSmime, $smime);

    $cmd = sprintf(
        'openssl smime -inform S/MIME -outform DER -in %s -out %s 2>/dev/null',
        escapeshellarg($tmpSmime),
        escapeshellarg($tmpDer)
    );
    exec($cmd, $output, $retCode);

    @unlink($tmpSmime);

    if ($retCode !== 0 || !file_exists($tmpDer)) {
        @unlink($tmpDer);
        // 降级: 尝试手动从 S/MIME 提取 base64 并转 DER
        return extract_der_from_smime($smime);
    }

    $der = file_get_contents($tmpDer);
    @unlink($tmpDer);
    return (!empty($der)) ? $der : false;
}

/**
 * 从 S/MIME 输出中提取 DER 格式的签名数据
 */
function extract_der_from_smime(string $smime): string|false {
    // S/MIME 格式包含多个 MIME 部分
    // 我们需要找到完整的 PKCS#7 签名并转换为 DER
    // 方法：去掉 S/MIME 头部，提取 base64 编码的证书数据

    // 查找 base64 编码的签名块
    // 格式通常是：boundary 分隔的多部分，最后一部分是签名
    $lines = explode("\n", $smime);
    $inBase64 = false;
    $base64Data = '';
    $boundary = '';

    // 找 boundary
    foreach ($lines as $line) {
        if (preg_match('/boundary="?([^";\s]+)"?/', $line, $m)) {
            $boundary = $m[1];
            break;
        }
    }

    if (empty($boundary)) {
        // 非 multipart，可能是直接的 PKCS#7
        $pos = strpos($smime, "\n\n");
        if ($pos === false) $pos = strpos($smime, "\r\n\r\n");
        if ($pos !== false) {
            $base64Data = substr($smime, $pos + 2);
            $base64Data = trim(str_replace(["\r", "\n"], '', $base64Data));
            $der = base64_decode($base64Data);
            return $der !== false ? $der : false;
        }
        return false;
    }

    // 找到 pkcs7-signature 部分
    $parts = preg_split('/--' . preg_quote($boundary, '/') . '(--)?\s*/', $smime);
    foreach ($parts as $part) {
        if (stripos($part, 'pkcs7-signature') !== false || stripos($part, 'application/pkcs7') !== false) {
            // 提取空行后的 base64 数据
            $pos = strpos($part, "\n\n");
            if ($pos === false) $pos = strpos($part, "\r\n\r\n");
            if ($pos !== false) {
                $base64Data = substr($part, $pos + 2);
                $base64Data = trim(str_replace(["\r", "\n", " "], '', $base64Data));
                $der = base64_decode($base64Data);
                return $der !== false ? $der : false;
            }
        }
    }

    return false;
}
