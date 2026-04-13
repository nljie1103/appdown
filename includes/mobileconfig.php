<?php
/**
 * Mobileconfig 生成与签名共享库
 * 提供 plist XML 生成、证书签名、文件保存等核心功能
 */

/**
 * 生成未签名的 mobileconfig plist XML
 */
function build_mobileconfig_xml(array $params): string {
    $displayName  = $params['display_name'] ?? 'App';
    $url          = $params['target_url'] ?? '';
    $bundleId     = $params['bundle_id'] ?: 'com.webclip.' . preg_replace('/[^a-z0-9]/', '', strtolower($displayName));
    $version      = $params['version'] ?? '1';
    $fullscreen   = !empty($params['fullscreen']);
    $iconData     = $params['icon_data'] ?? '';
    $description  = $params['description'] ?: $displayName;
    $organization = $params['payload_org'] ?? '';

    // 确定性UUID
    $seed = $bundleId ?: $displayName;
    $hash1 = md5($seed . '_webclip_payload');
    $uuid1 = strtoupper(substr($hash1,0,8).'-'.substr($hash1,8,4).'-'.substr($hash1,12,4).'-'.substr($hash1,16,4).'-'.substr($hash1,20,12));
    $hash2 = md5($seed . '_webclip_config');
    $uuid2 = strtoupper(substr($hash2,0,8).'-'.substr($hash2,8,4).'-'.substr($hash2,12,4).'-'.substr($hash2,16,4).'-'.substr($hash2,20,12));

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
    echo '      <integer>' . (int)$version . '</integer>' . "\n";
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
    return ob_get_clean();
}

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
            $realPath = realpath($value);
            $projectRoot = realpath(__DIR__ . '/..');
            if ($realPath && is_readable($realPath) && (
                substr($realPath, 0, strlen($projectRoot)) === $projectRoot ||
                substr($realPath, 0, 8) === '/etc/ssl' ||
                substr($realPath, 0, 8) === '/etc/pki'
            )) {
                return file_get_contents($realPath);
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
 * 验证证书并提取信息（颁发者品牌、到期时间）
 * @param string $mode 证书模式
 * @param string $certRaw 证书原始值
 * @param string $keyRaw 私钥原始值
 * @return array ['valid'=>bool, 'error'=>string, 'issuer'=>string, 'expires'=>string]
 */
function validate_and_parse_cert(string $mode, string $certRaw, string $keyRaw): array {
    $result = ['valid' => false, 'error' => '', 'issuer' => '', 'expires' => ''];

    $certPem = resolve_cert_content($mode, $certRaw);
    if (empty($certPem)) {
        $result['error'] = '证书内容为空或无法读取';
        return $result;
    }

    // 验证证书
    $certRes = @openssl_x509_read($certPem);
    if (!$certRes) {
        $result['error'] = '证书格式无效: ' . (openssl_error_string() ?: '无法解析PEM');
        return $result;
    }

    // 解析证书信息
    $info = openssl_x509_parse($certRes);
    if (!$info) {
        $result['error'] = '无法解析证书信息';
        return $result;
    }

    // 提取颁发者（品牌）
    $issuerParts = [];
    if (!empty($info['issuer']['O'])) $issuerParts[] = $info['issuer']['O'];
    if (!empty($info['issuer']['CN'])) $issuerParts[] = $info['issuer']['CN'];
    $result['issuer'] = implode(' - ', $issuerParts) ?: ($info['issuer']['OU'] ?? 'Unknown');

    // 提取到期时间
    if (!empty($info['validTo_time_t'])) {
        $result['expires'] = date('Y-m-d H:i:s', $info['validTo_time_t']);
    }

    // 验证私钥（如果有的话）
    if (!empty($keyRaw)) {
        $keyPem = resolve_cert_content($mode, $keyRaw);
        if (empty($keyPem)) {
            $result['error'] = '私钥内容为空或无法读取';
            return $result;
        }
        $keyRes = @openssl_pkey_get_private($keyPem);
        if (!$keyRes) {
            $result['error'] = '私钥格式无效: ' . (openssl_error_string() ?: '无法解析PEM');
            return $result;
        }
        // 验证证书与私钥匹配
        if (!@openssl_x509_check_private_key($certRes, $keyRes)) {
            $result['error'] = '证书与私钥不匹配';
            return $result;
        }
    }

    $result['valid'] = true;
    return $result;
}

/**
 * 使用 OpenSSL PKCS#7 签名 mobileconfig
 */
function sign_mobileconfig(string $xml, string $certPem, string $keyPem, string $chainPem = '') {
    // 验证签名证书
    $certRes = @openssl_x509_read($certPem);
    if (!$certRes) {
        return ['error' => '签名证书无效: ' . openssl_error_string()];
    }

    // 验证私钥
    $keyRes = @openssl_pkey_get_private($keyPem);
    if (!$keyRes) {
        return ['error' => '私钥无效: ' . openssl_error_string()];
    }

    $tmpDir = sys_get_temp_dir();
    $tmpIn = tempnam($tmpDir, 'mc_in_');
    $tmpOut = tempnam($tmpDir, 'mc_out_');
    $tmpCert = tempnam($tmpDir, 'mc_cert_');
    $tmpKey = tempnam($tmpDir, 'mc_key_');

    file_put_contents($tmpIn, $xml);
    file_put_contents($tmpCert, $certPem);
    file_put_contents($tmpKey, $keyPem);

    // 验证 chain 中包含有效的 PEM 证书，无效则跳过 chain
    $extraCertsFile = null;
    if (!empty($chainPem)) {
        // 检查 chain 是否包含至少一个有效的 PEM 证书块
        if (preg_match('/-----BEGIN CERTIFICATE-----/', $chainPem) && @openssl_x509_read($chainPem)) {
            $extraCertsFile = tempnam($tmpDir, 'mc_chain_');
            file_put_contents($extraCertsFile, $chainPem);
        }
        // chain 无效时不传，仅用 cert+key 签名
    }

    $result = @openssl_pkcs7_sign(
        $tmpIn, $tmpOut,
        'file://' . $tmpCert, 'file://' . $tmpKey,
        [], PKCS7_BINARY | PKCS7_NOATTR,
        $extraCertsFile
    );

    @unlink($tmpIn);
    @unlink($tmpCert);
    @unlink($tmpKey);
    if ($extraCertsFile) @unlink($extraCertsFile);

    if (!$result) {
        @unlink($tmpOut);
        $err = '';
        while ($msg = openssl_error_string()) $err .= $msg . '; ';
        return ['error' => '签名失败: ' . ($err ?: '未知OpenSSL错误')];
    }

    $smime = file_get_contents($tmpOut);
    @unlink($tmpOut);
    if (empty($smime)) return false;

    // S/MIME -> DER
    $tmpSmime = tempnam($tmpDir, 'mc_smime_');
    $tmpDer = tempnam($tmpDir, 'mc_der_');
    file_put_contents($tmpSmime, $smime);

    $cmd = sprintf(
        'openssl smime -inform S/MIME -outform DER -in %s -out %s 2>/dev/null',
        escapeshellarg($tmpSmime), escapeshellarg($tmpDer)
    );
    exec($cmd, $output, $retCode);
    @unlink($tmpSmime);

    if ($retCode !== 0 || !file_exists($tmpDer)) {
        @unlink($tmpDer);
        return extract_der_from_smime($smime);
    }

    $der = file_get_contents($tmpDer);
    @unlink($tmpDer);
    return (!empty($der)) ? $der : false;
}

/**
 * 从 S/MIME 输出中提取 DER 格式的签名数据（降级方案）
 */
function extract_der_from_smime(string $smime) {
    $lines = explode("\n", $smime);
    $boundary = '';
    foreach ($lines as $line) {
        if (preg_match('/boundary="?([^";\s]+)"?/', $line, $m)) {
            $boundary = $m[1];
            break;
        }
    }

    if (empty($boundary)) {
        $posLF = strpos($smime, "\n\n");
        $posCRLF = strpos($smime, "\r\n\r\n");
        if ($posLF !== false) {
            $base64Data = substr($smime, $posLF + 2);
        } elseif ($posCRLF !== false) {
            $base64Data = substr($smime, $posCRLF + 4);
        } else {
            return false;
        }
        $base64Data = trim(str_replace(["\r", "\n"], '', $base64Data));
        $der = base64_decode($base64Data);
        return $der !== false ? $der : false;
    }

    $parts = preg_split('/--' . preg_quote($boundary, '/') . '(--)?\s*/', $smime);
    foreach ($parts as $part) {
        if (stripos($part, 'pkcs7-signature') !== false || stripos($part, 'application/pkcs7') !== false) {
            $posLF = strpos($part, "\n\n");
            $posCRLF = strpos($part, "\r\n\r\n");
            if ($posLF !== false) {
                $base64Data = substr($part, $posLF + 2);
            } elseif ($posCRLF !== false) {
                $base64Data = substr($part, $posCRLF + 4);
            } else {
                continue;
            }
            $base64Data = trim(str_replace(["\r", "\n", " "], '', $base64Data));
            $der = base64_decode($base64Data);
            return $der !== false ? $der : false;
        }
    }
    return false;
}

/**
 * 生成 mobileconfig 文件并保存到磁盘
 * @param array $params 生成参数 (display_name, target_url, bundle_id, version, fullscreen, icon_data, description, payload_org)
 * @param array|null $cert 证书信息 (mode, cert, key, chain) 或 null 表示不签名
 * @param string $destDir 目标目录绝对路径
 * @return array ['ok'=>true, 'file_path'=>相对路径, 'file_size'=>'12.3 KB'] 或 ['ok'=>false, 'error'=>'...']
 */
function generate_and_save_mobileconfig(array $params, ?array $cert, string $destDir): array {
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }
    if (!is_dir($destDir)) {
        return ['ok' => false, 'error' => '无法创建目录: ' . $destDir];
    }

    $xml = build_mobileconfig_xml($params);

    // 尝试签名
    $output = $xml;
    $signed = false;
    if ($cert && function_exists('openssl_pkcs7_sign')) {
        $certPem = resolve_cert_content($cert['mode'] ?? '', $cert['cert'] ?? '');
        $keyPem = resolve_cert_content($cert['mode'] ?? '', $cert['key'] ?? '');
        $chainPem = resolve_cert_content($cert['mode'] ?? '', $cert['chain'] ?? '');
        if (!empty($certPem) && !empty($keyPem)) {
            $signResult = sign_mobileconfig($xml, $certPem, $keyPem, $chainPem);
            if (is_array($signResult) && isset($signResult['error'])) {
                return ['ok' => false, 'error' => $signResult['error']];
            }
            if ($signResult !== false && !empty($signResult)) {
                $output = $signResult;
                $signed = true;
            }
        }
    }

    // 生成文件名
    $safeName = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fff}_-]/u', '_', $params['display_name'] ?? 'app');
    $safeName = trim(preg_replace('/_+/', '_', $safeName), '_') ?: 'app';
    $filename = $safeName . '_' . time() . '.mobileconfig';
    $fullPath = $destDir . '/' . $filename;

    if (file_put_contents($fullPath, $output) === false) {
        return ['ok' => false, 'error' => '写入文件失败'];
    }

    $size = filesize($fullPath);
    $sizeStr = $size < 1024 ? $size . ' B'
        : ($size < 1048576 ? number_format($size / 1024, 1) . ' KB'
        : number_format($size / 1048576, 1) . ' MB');

    // 计算相对路径（相对于项目根目录，兼容 Windows 反斜杠）
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..')) . '/';
    $relativePath = str_replace($projectRoot, '', str_replace('\\', '/', realpath($fullPath)));

    return ['ok' => true, 'file_path' => $relativePath, 'file_size' => $sizeStr, 'signed' => $signed];
}
