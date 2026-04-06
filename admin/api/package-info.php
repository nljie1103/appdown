<?php
/**
 * 安装包信息解析API
 * 支持 APK (Android) 和 IPA (iOS) 安装包的签名、版本等详细信息解析
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();
require_method('GET');

$fileUrl = $_GET['file'] ?? '';
if (!$fileUrl) {
    json_response(['error' => '缺少 file 参数'], 400);
}

// 安全检查：只允许读取 uploads/ 下的文件
if (!str_starts_with($fileUrl, 'uploads/')) {
    json_response(['error' => '非法文件路径'], 403);
}

$filePath = realpath(__DIR__ . '/../../' . $fileUrl);
$basePath = realpath(__DIR__ . '/../../uploads');

if (!$filePath || !$basePath || !str_starts_with($filePath, $basePath)) {
    json_response(['error' => '非法文件路径'], 403);
}

if (!file_exists($filePath)) {
    json_response(['error' => '文件不存在'], 404);
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

try {
    if ($ext === 'apk') {
        $info = parseApk($filePath);
    } elseif ($ext === 'ipa') {
        $info = parseIpa($filePath);
    } else {
        json_response(['error' => '不支持的文件类型，仅支持 APK 和 IPA', 'file_type' => $ext], 400);
    }

    $info['file_name'] = basename($filePath);
    $info['file_size_bytes'] = filesize($filePath);
    $info['file_size'] = formatSize(filesize($filePath));
    $info['file_md5'] = md5_file($filePath);
    $info['file_sha1'] = sha1_file($filePath);
    $info['file_sha256'] = hash_file('sha256', $filePath);
    $info['file_type'] = $ext;

    json_response(['ok' => true, 'info' => $info]);
} catch (Throwable $e) {
    json_response(['error' => '解析失败: ' . $e->getMessage()], 500);
}

// ==================== APK 解析 ====================

function parseApk(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('无法打开 APK 文件（非有效 ZIP 格式）');
    }

    $info = ['platform' => 'Android'];

    // 解析 AndroidManifest.xml (二进制XML)
    $manifestData = $zip->getFromName('AndroidManifest.xml');
    if ($manifestData !== false) {
        $manifest = parseAndroidBinaryXml($manifestData);
        $info['package_name'] = $manifest['package'] ?? '未知';
        $info['version_code'] = $manifest['versionCode'] ?? '未知';
        $info['version_name'] = $manifest['versionName'] ?? '未知';
        $info['min_sdk'] = $manifest['minSdkVersion'] ?? '未知';
        $info['target_sdk'] = $manifest['targetSdkVersion'] ?? '未知';
        $info['compile_sdk'] = $manifest['compileSdkVersion'] ?? '未知';

        if (isset($manifest['minSdkVersion']) && is_numeric($manifest['minSdkVersion'])) {
            $info['min_android_version'] = sdkToAndroidVersion((int)$manifest['minSdkVersion']);
        }
        if (isset($manifest['targetSdkVersion']) && is_numeric($manifest['targetSdkVersion'])) {
            $info['target_android_version'] = sdkToAndroidVersion((int)$manifest['targetSdkVersion']);
        }

        // 权限
        if (!empty($manifest['permissions'])) {
            $info['permissions'] = $manifest['permissions'];
            $info['permissions_count'] = count($manifest['permissions']);
        }

        // 主Activity
        if (!empty($manifest['mainActivity'])) {
            $info['main_activity'] = $manifest['mainActivity'];
        }

        // 应用类名
        if (!empty($manifest['application'])) {
            $info['application_class'] = $manifest['application'];
        }

        // features
        if (!empty($manifest['features'])) {
            $info['features'] = $manifest['features'];
        }
    }

    // 解析签名信息
    $signInfo = parseApkSignature($zip);
    if ($signInfo) {
        $info['signature'] = $signInfo;
    }

    // 检查 DEX 文件数量（multidex）
    $dexCount = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('/^classes\d*\.dex$/', $name)) {
            $dexCount++;
        }
    }
    $info['dex_count'] = $dexCount;
    $info['multidex'] = $dexCount > 1;

    // 检查 native libraries
    $nativeLibs = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('#^lib/([^/]+)/#', $name, $m)) {
            $nativeLibs[$m[1]] = true;
        }
    }
    if ($nativeLibs) {
        $info['native_architectures'] = array_keys($nativeLibs);
    }

    // 检查是否包含 Kotlin
    $hasKotlin = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_contains($name, 'kotlin') || $name === 'kotlin-tooling-metadata.json') {
            $hasKotlin = true;
            break;
        }
    }
    $info['uses_kotlin'] = $hasKotlin;

    // 文件数量
    $info['total_files_in_package'] = $zip->numFiles;

    $zip->close();
    return $info;
}

// 解析Android二进制XML (AndroidManifest.xml)
function parseAndroidBinaryXml(string $data): array {
    $result = [
        'package' => null,
        'versionCode' => null,
        'versionName' => null,
        'minSdkVersion' => null,
        'targetSdkVersion' => null,
        'compileSdkVersion' => null,
        'permissions' => [],
        'features' => [],
        'mainActivity' => null,
        'application' => null,
    ];

    $len = strlen($data);
    if ($len < 8) return $result;

    // Android binary XML format
    $magic = unpack('v', substr($data, 0, 2))[1];
    if ($magic !== 0x0003) return $result; // CHUNK_AXML_FILE

    // String pool starts at offset 8
    $stringPoolOffset = 8;
    if ($stringPoolOffset + 8 > $len) return $result;

    $spHeader = unpack('vtype/vheaderSize/Vsize/VstringCount/VstyleCount/Vflags/VstringsStart/VstylesStart',
        substr($data, $stringPoolOffset, 28));

    if (!$spHeader || $spHeader['type'] !== 0x0001) return $result;

    $stringCount = $spHeader['stringCount'];
    $isUtf8 = ($spHeader['flags'] & (1 << 8)) !== 0;
    $stringsDataStart = $stringPoolOffset + $spHeader['stringsStart'];

    // Read string offsets
    $offsetsStart = $stringPoolOffset + $spHeader['headerSize'];
    $stringOffsets = [];
    for ($i = 0; $i < $stringCount; $i++) {
        $off = $offsetsStart + $i * 4;
        if ($off + 4 > $len) break;
        $stringOffsets[] = unpack('V', substr($data, $off, 4))[1];
    }

    // Read strings
    $strings = [];
    for ($i = 0; $i < count($stringOffsets); $i++) {
        $pos = $stringsDataStart + $stringOffsets[$i];
        if ($pos >= $len) { $strings[] = ''; continue; }

        if ($isUtf8) {
            // UTF-8: skip char count, read byte count
            $pos += (ord($data[$pos]) & 0x80) ? 2 : 1; // skip char len
            $byteLen = ord($data[$pos]);
            if ($byteLen & 0x80) {
                $byteLen = (($byteLen & 0x7F) << 8) | ord($data[$pos + 1]);
                $pos += 2;
            } else {
                $pos += 1;
            }
            $strings[] = substr($data, $pos, $byteLen);
        } else {
            // UTF-16
            $charLen = unpack('v', substr($data, $pos, 2))[1];
            if ($charLen & 0x8000) {
                $charLen = (($charLen & 0x7FFF) << 16) | unpack('v', substr($data, $pos + 2, 2))[1];
                $pos += 4;
            } else {
                $pos += 2;
            }
            $raw = substr($data, $pos, $charLen * 2);
            $strings[] = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        }
    }

    // Helper to get string by index
    $getString = function(int $idx) use ($strings): ?string {
        return ($idx >= 0 && $idx < count($strings)) ? $strings[$idx] : null;
    };

    // Now parse the XML resource map and tree
    $pos = $stringPoolOffset + $spHeader['size'];

    // Skip resource map if present
    if ($pos + 8 <= $len) {
        $chunkType = unpack('v', substr($data, $pos, 2))[1];
        if ($chunkType === 0x0180) { // XML_RESOURCE_MAP
            $chunkSize = unpack('V', substr($data, $pos + 4, 4))[1];
            $pos += $chunkSize;
        }
    }

    // Parse XML tree nodes
    $currentNamespace = [];
    $inApplication = false;
    $inActivity = false;
    $activityName = null;
    $hasMainAction = false;
    $hasLauncherCategory = false;

    while ($pos + 16 <= $len) {
        $nodeType = unpack('v', substr($data, $pos, 2))[1];
        $headerSize = unpack('v', substr($data, $pos + 2, 2))[1];
        $chunkSize = unpack('V', substr($data, $pos + 4, 4))[1];

        if ($chunkSize < 8 || $pos + $chunkSize > $len) break;

        if ($nodeType === 0x0100) {
            // START_NAMESPACE
            if ($pos + 24 <= $len) {
                $prefix = unpack('V', substr($data, $pos + 16, 4))[1];
                $uri = unpack('V', substr($data, $pos + 20, 4))[1];
                $nsPrefix = $getString($prefix);
                $nsUri = $getString($uri);
                if ($nsPrefix !== null) $currentNamespace[$nsUri ?? ''] = $nsPrefix;
            }
        } elseif ($nodeType === 0x0102) {
            // START_TAG
            if ($pos + 28 <= $len) {
                $nameIdx = unpack('V', substr($data, $pos + 20, 4))[1];
                $attrStart = unpack('v', substr($data, $pos + 24, 2))[1];
                $attrSize = unpack('v', substr($data, $pos + 26, 2))[1];
                $attrCount = unpack('v', substr($data, $pos + 28, 2))[1];

                $tagName = $getString($nameIdx);

                // Read attributes
                $attrs = [];
                for ($a = 0; $a < $attrCount; $a++) {
                    $attrOff = $pos + 16 + $attrStart + $a * ($attrSize ?: 20);
                    if ($attrOff + 20 > $len) break;

                    $attrNsIdx = unpack('V', substr($data, $attrOff, 4))[1];
                    $attrNameIdx = unpack('V', substr($data, $attrOff + 4, 4))[1];
                    $attrValueStrIdx = unpack('V', substr($data, $attrOff + 8, 4))[1];
                    $attrType = unpack('v', substr($data, $attrOff + 14, 2))[1]; // type byte at +15 actually
                    $attrTypedValue = unpack('V', substr($data, $attrOff + 16, 4))[1];

                    $aName = $getString($attrNameIdx);
                    // Get value: prefer typed value for ints, string value for strings
                    if ($attrValueStrIdx !== 0xFFFFFFFF) {
                        $aValue = $getString($attrValueStrIdx);
                    } else {
                        $aValue = (string)$attrTypedValue;
                    }

                    if ($aName !== null) {
                        $attrs[$aName] = $aValue;
                    }
                }

                if ($tagName === 'manifest') {
                    $result['package'] = $attrs['package'] ?? null;
                    $result['versionCode'] = $attrs['versionCode'] ?? null;
                    $result['versionName'] = $attrs['versionName'] ?? null;
                    $result['compileSdkVersion'] = $attrs['compileSdkVersion'] ?? null;
                }

                if ($tagName === 'uses-sdk') {
                    $result['minSdkVersion'] = $attrs['minSdkVersion'] ?? null;
                    $result['targetSdkVersion'] = $attrs['targetSdkVersion'] ?? null;
                }

                if ($tagName === 'uses-permission') {
                    $perm = $attrs['name'] ?? null;
                    if ($perm) $result['permissions'][] = $perm;
                }

                if ($tagName === 'uses-feature') {
                    $feat = $attrs['name'] ?? null;
                    if ($feat) $result['features'][] = $feat;
                }

                if ($tagName === 'application') {
                    $inApplication = true;
                    $result['application'] = $attrs['name'] ?? null;
                }

                if ($tagName === 'activity' || $tagName === 'activity-alias') {
                    $inActivity = true;
                    $activityName = $attrs['name'] ?? null;
                    $hasMainAction = false;
                    $hasLauncherCategory = false;
                }

                if ($tagName === 'action' && $inActivity) {
                    if (($attrs['name'] ?? '') === 'android.intent.action.MAIN') {
                        $hasMainAction = true;
                    }
                }

                if ($tagName === 'category' && $inActivity) {
                    if (($attrs['name'] ?? '') === 'android.intent.category.LAUNCHER') {
                        $hasLauncherCategory = true;
                    }
                }
            }
        } elseif ($nodeType === 0x0103) {
            // END_TAG
            if ($pos + 24 <= $len) {
                $nameIdx = unpack('V', substr($data, $pos + 20, 4))[1];
                $closingTag = $getString($nameIdx);

                if (($closingTag === 'activity' || $closingTag === 'activity-alias') && $inActivity) {
                    if ($hasMainAction && $hasLauncherCategory && $activityName) {
                        $result['mainActivity'] = $activityName;
                    }
                    $inActivity = false;
                    $activityName = null;
                    $hasMainAction = false;
                    $hasLauncherCategory = false;
                }

                if ($closingTag === 'application') {
                    $inApplication = false;
                }
            }
        }

        $pos += $chunkSize;
    }

    return $result;
}

// 解析 APK 签名信息 (META-INF/*.RSA or *.DSA or *.EC)
function parseApkSignature(ZipArchive $zip): ?array {
    $certFile = null;
    $certName = '';

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('#^META-INF/[^/]+\.(RSA|DSA|EC)$#i', $name)) {
            $certFile = $zip->getFromIndex($i);
            $certName = $name;
            break;
        }
    }

    if (!$certFile) return null;

    $result = ['cert_file' => $certName];

    // The file is PKCS#7 DER format. Try to parse using openssl
    $tmpFile = tempnam(sys_get_temp_dir(), 'apk_cert_');
    file_put_contents($tmpFile, $certFile);

    // Try to extract certificate from PKCS7
    $pkcs7Pem = tempnam(sys_get_temp_dir(), 'apk_p7_');

    // Convert DER to PEM PKCS7
    $derContent = $certFile;
    $pemContent = "-----BEGIN PKCS7-----\n" . chunk_split(base64_encode($derContent), 64) . "-----END PKCS7-----\n";
    file_put_contents($pkcs7Pem, $pemContent);

    $certs = [];
    $parsed = openssl_pkcs7_read($pemContent, $certs);

    if ($parsed && !empty($certs)) {
        $certData = openssl_x509_parse($certs[0]);
        if ($certData) {
            $result['subject'] = formatX509Name($certData['subject'] ?? []);
            $result['issuer'] = formatX509Name($certData['issuer'] ?? []);
            $result['serial_number'] = $certData['serialNumberHex'] ?? ($certData['serialNumber'] ?? '未知');
            $result['valid_from'] = date('Y-m-d H:i:s', $certData['validFrom_time_t'] ?? 0);
            $result['valid_to'] = date('Y-m-d H:i:s', $certData['validTo_time_t'] ?? 0);
            $result['signature_algorithm'] = $certData['signatureTypeSN'] ?? '未知';

            // Check if certificate is currently valid
            $now = time();
            $validFrom = $certData['validFrom_time_t'] ?? 0;
            $validTo = $certData['validTo_time_t'] ?? 0;
            if ($now < $validFrom) {
                $result['is_valid'] = false;
                $result['validity_status'] = '证书尚未生效';
            } elseif ($now > $validTo) {
                $result['is_valid'] = false;
                $result['validity_status'] = '证书已过期';
            } else {
                $result['is_valid'] = true;
                $result['validity_status'] = '证书有效';
                $result['days_remaining'] = (int)ceil(($validTo - $now) / 86400);
            }

            // Fingerprints
            if (!empty($certs[0])) {
                $certDer = null;
                openssl_x509_export($certs[0], $certPem);
                $certDer = base64_decode(
                    str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"], '', $certPem)
                );
                if ($certDer) {
                    $result['fingerprint_md5'] = strtoupper(implode(':', str_split(md5($certDer), 2)));
                    $result['fingerprint_sha1'] = strtoupper(implode(':', str_split(sha1($certDer), 2)));
                    $result['fingerprint_sha256'] = strtoupper(implode(':', str_split(hash('sha256', $certDer), 2)));
                }
            }

            // Version
            $result['cert_version'] = 'V' . (($certData['version'] ?? 0) + 1);
        }
    }

    // Check which signature scheme versions are present
    $result['v1_signature'] = true; // We found META-INF certs
    // V2/V3 signatures are in APK Signing Block (before central directory)
    $result['v2_signature'] = checkApkV2Signature($zip);

    @unlink($tmpFile);
    @unlink($pkcs7Pem);

    return $result;
}

// 检查 APK v2 签名块
function checkApkV2Signature(ZipArchive $zip): bool {
    // APK Signing Block is located before the Central Directory
    // We need to read the raw file to check for it
    $path = $zip->filename;
    $fh = fopen($path, 'rb');
    if (!$fh) return false;

    // Read end of central directory record (last 22 bytes min)
    fseek($fh, -22, SEEK_END);
    $eocd = fread($fh, 22);

    if (strlen($eocd) < 22 || unpack('V', substr($eocd, 0, 4))[1] !== 0x06054b50) {
        fclose($fh);
        return false;
    }

    $cdOffset = unpack('V', substr($eocd, 16, 4))[1];

    // The APK Signing Block is located just before the Central Directory
    // It ends with a 16-byte magic: "APK Sig Block 42"
    if ($cdOffset < 24) { fclose($fh); return false; }

    fseek($fh, $cdOffset - 16, SEEK_SET);
    $magic = fread($fh, 16);
    fclose($fh);

    return $magic === "APK Sig Block 42";
}

// ==================== IPA 解析 ====================

function parseIpa(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('无法打开 IPA 文件（非有效 ZIP 格式）');
    }

    $info = ['platform' => 'iOS'];

    // Find Info.plist inside Payload/*.app/
    $plistData = null;
    $appDir = null;
    $provisionData = null;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (preg_match('#^Payload/([^/]+\.app)/Info\.plist$#', $name, $m)) {
            $plistData = $zip->getFromIndex($i);
            $appDir = 'Payload/' . $m[1] . '/';
        }

        if (preg_match('#^Payload/[^/]+\.app/embedded\.mobileprovision$#', $name)) {
            $provisionData = $zip->getFromIndex($i);
        }
    }

    if ($plistData) {
        $plistInfo = parsePlist($plistData);

        $info['bundle_id'] = $plistInfo['CFBundleIdentifier'] ?? '未知';
        $info['bundle_name'] = $plistInfo['CFBundleName'] ?? '未知';
        $info['display_name'] = $plistInfo['CFBundleDisplayName'] ?? ($plistInfo['CFBundleName'] ?? '未知');
        $info['version'] = $plistInfo['CFBundleShortVersionString'] ?? '未知';
        $info['build_version'] = $plistInfo['CFBundleVersion'] ?? '未知';
        $info['min_ios_version'] = $plistInfo['MinimumOSVersion'] ?? '未知';
        $info['bundle_executable'] = $plistInfo['CFBundleExecutable'] ?? '未知';
        $info['sdk_name'] = $plistInfo['DTSDKName'] ?? null;
        $info['build_machine_os'] = $plistInfo['DTSDKBuild'] ?? null;
        $info['xcode_version'] = $plistInfo['DTXcode'] ?? null;
        $info['xcode_build'] = $plistInfo['DTXcodeBuild'] ?? null;
        $info['compiler'] = $plistInfo['DTCompiler'] ?? null;
        $info['platform_name'] = $plistInfo['DTPlatformName'] ?? null;
        $info['platform_version'] = $plistInfo['DTPlatformVersion'] ?? null;
        $info['platform_build'] = $plistInfo['DTPlatformBuild'] ?? null;

        // Supported device architectures
        if (isset($plistInfo['UIRequiredDeviceCapabilities'])) {
            $info['required_capabilities'] = $plistInfo['UIRequiredDeviceCapabilities'];
        }

        // Supported orientations
        if (isset($plistInfo['UISupportedInterfaceOrientations'])) {
            $info['supported_orientations'] = $plistInfo['UISupportedInterfaceOrientations'];
        }

        // Supported devices
        if (isset($plistInfo['UIDeviceFamily'])) {
            $families = is_array($plistInfo['UIDeviceFamily']) ? $plistInfo['UIDeviceFamily'] : [$plistInfo['UIDeviceFamily']];
            $deviceMap = [1 => 'iPhone/iPod', 2 => 'iPad', 3 => 'Apple TV', 4 => 'Apple Watch'];
            $info['supported_devices'] = array_map(fn($f) => $deviceMap[(int)$f] ?? "Unknown($f)", $families);
        }

        // ATS (App Transport Security)
        if (isset($plistInfo['NSAppTransportSecurity'])) {
            $info['allows_arbitrary_loads'] = !empty($plistInfo['NSAppTransportSecurity']['NSAllowsArbitraryLoads']);
        }

        // URL schemes
        if (isset($plistInfo['CFBundleURLTypes'])) {
            $schemes = [];
            foreach ($plistInfo['CFBundleURLTypes'] as $urlType) {
                if (isset($urlType['CFBundleURLSchemes'])) {
                    foreach ($urlType['CFBundleURLSchemes'] as $s) {
                        $schemes[] = $s;
                    }
                }
            }
            if ($schemes) $info['url_schemes'] = $schemes;
        }

        // Remove null values
        $info = array_filter($info, fn($v) => $v !== null);
    }

    // 解析 embedded.mobileprovision（签名配置信息）
    if ($provisionData) {
        $provInfo = parseMobileProvision($provisionData);
        if ($provInfo) {
            $info['provisioning'] = $provInfo;
        }
    }

    // 检查是否包含 Frameworks
    $frameworks = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($appDir && preg_match('#^' . preg_quote($appDir, '#') . 'Frameworks/([^/]+\.framework)/#', $name, $m)) {
            $frameworks[$m[1]] = true;
        }
    }
    if ($frameworks) {
        $info['embedded_frameworks'] = array_keys($frameworks);
        $info['frameworks_count'] = count($frameworks);
    }

    // 检查架构 (通过 Frameworks 或 executable)
    $info['total_files_in_package'] = $zip->numFiles;

    $zip->close();
    return $info;
}

// 解析 plist（支持 XML 和 Binary 格式）
function parsePlist(string $data): array {
    // Check if it's binary plist
    if (str_starts_with($data, 'bplist')) {
        return parseBinaryPlist($data);
    }

    // Try XML plist
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($data);
    if ($xml === false) {
        return [];
    }

    return plistXmlToArray($xml->dict);
}

function plistXmlToArray(SimpleXMLElement $dict): array {
    $result = [];
    $children = $dict->children();
    $count = count($children);

    for ($i = 0; $i < $count; $i += 2) {
        $key = (string)$children[$i];
        $valueNode = $children[$i + 1];

        if ($valueNode === null) break;

        $result[$key] = plistXmlValueToPhp($valueNode);
    }

    return $result;
}

function plistXmlValueToPhp(SimpleXMLElement $node): mixed {
    $name = $node->getName();
    return match($name) {
        'string' => (string)$node,
        'integer' => (int)(string)$node,
        'real' => (float)(string)$node,
        'true' => true,
        'false' => false,
        'dict' => plistXmlToArray($node),
        'array' => array_map(fn($child) => plistXmlValueToPhp($child), iterator_to_array($node->children())),
        'data' => base64_decode((string)$node),
        'date' => (string)$node,
        default => (string)$node,
    };
}

// 解析 Binary Plist
function parseBinaryPlist(string $data): array {
    $len = strlen($data);
    if ($len < 40) return [];

    // Trailer: last 32 bytes
    // offset  0:  6 unused bytes
    // offset  6:  1 byte offsetSize
    // offset  7:  1 byte objectRefSize
    // offset  8:  8 bytes numObjects
    // offset 16:  8 bytes topObject
    // offset 24:  8 bytes offsetTableOffset
    $trailer = substr($data, $len - 32, 32);
    $offsetSize = ord($trailer[6]);
    $objectRefSize = ord($trailer[7]);
    $numObjects = unpack('J', substr($trailer, 8, 8))[1];
    $topObject = unpack('J', substr($trailer, 16, 8))[1];
    $offsetTableOffset = unpack('J', substr($trailer, 24, 8))[1];

    if ($numObjects > 1000000 || $offsetSize > 8 || $objectRefSize > 8) return [];

    // Read offset table
    $offsets = [];
    for ($i = 0; $i < $numObjects; $i++) {
        $pos = $offsetTableOffset + $i * $offsetSize;
        $offsets[] = readBplistInt($data, $pos, $offsetSize);
    }

    // Parse object at index
    $parseObject = function(int $idx) use (&$parseObject, $data, $offsets, $objectRefSize, $len): mixed {
        if ($idx >= count($offsets)) return null;
        $offset = $offsets[$idx];
        if ($offset >= $len) return null;

        $marker = ord($data[$offset]);
        $objectType = ($marker & 0xF0) >> 4;
        $objectInfo = $marker & 0x0F;

        return match($objectType) {
            // null/bool/fill
            0 => match($objectInfo) {
                0 => null,
                8 => false,
                9 => true,
                default => null,
            },
            // int
            1 => (function() use ($data, $offset, $objectInfo) {
                $byteCount = 1 << $objectInfo;
                $val = readBplistInt($data, $offset + 1, $byteCount);
                return $val;
            })(),
            // real
            2 => (function() use ($data, $offset, $objectInfo) {
                $byteCount = 1 << $objectInfo;
                if ($byteCount === 4) return unpack('G', substr($data, $offset + 1, 4))[1];
                if ($byteCount === 8) return unpack('E', substr($data, $offset + 1, 8))[1];
                return 0.0;
            })(),
            // date
            3 => (function() use ($data, $offset) {
                $ts = unpack('E', substr($data, $offset + 1, 8))[1];
                return date('Y-m-d\TH:i:s\Z', (int)($ts + 978307200));
            })(),
            // data
            4 => (function() use ($data, $offset, $objectInfo, $len) {
                $size = $objectInfo;
                $dataOffset = $offset + 1;
                if ($objectInfo === 0x0F) {
                    $sizeInfo = ord($data[$offset + 1]);
                    $sizeBytes = 1 << ($sizeInfo & 0x0F);
                    $size = readBplistInt($data, $offset + 2, $sizeBytes);
                    $dataOffset = $offset + 2 + $sizeBytes;
                }
                return substr($data, $dataOffset, $size);
            })(),
            // ASCII string
            5 => (function() use ($data, $offset, $objectInfo) {
                $size = $objectInfo;
                $strOffset = $offset + 1;
                if ($objectInfo === 0x0F) {
                    $sizeInfo = ord($data[$offset + 1]);
                    $sizeBytes = 1 << ($sizeInfo & 0x0F);
                    $size = readBplistInt($data, $offset + 2, $sizeBytes);
                    $strOffset = $offset + 2 + $sizeBytes;
                }
                return substr($data, $strOffset, $size);
            })(),
            // Unicode string
            6 => (function() use ($data, $offset, $objectInfo) {
                $size = $objectInfo;
                $strOffset = $offset + 1;
                if ($objectInfo === 0x0F) {
                    $sizeInfo = ord($data[$offset + 1]);
                    $sizeBytes = 1 << ($sizeInfo & 0x0F);
                    $size = readBplistInt($data, $offset + 2, $sizeBytes);
                    $strOffset = $offset + 2 + $sizeBytes;
                }
                $raw = substr($data, $strOffset, $size * 2);
                return mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
            })(),
            // array
            0xA => (function() use ($data, $offset, $objectInfo, $objectRefSize, &$parseObject) {
                $count = $objectInfo;
                $refsOffset = $offset + 1;
                if ($objectInfo === 0x0F) {
                    $sizeInfo = ord($data[$offset + 1]);
                    $sizeBytes = 1 << ($sizeInfo & 0x0F);
                    $count = readBplistInt($data, $offset + 2, $sizeBytes);
                    $refsOffset = $offset + 2 + $sizeBytes;
                }
                $result = [];
                for ($i = 0; $i < $count; $i++) {
                    $ref = readBplistInt($data, $refsOffset + $i * $objectRefSize, $objectRefSize);
                    $result[] = $parseObject($ref);
                }
                return $result;
            })(),
            // dict
            0xD => (function() use ($data, $offset, $objectInfo, $objectRefSize, &$parseObject) {
                $count = $objectInfo;
                $refsOffset = $offset + 1;
                if ($objectInfo === 0x0F) {
                    $sizeInfo = ord($data[$offset + 1]);
                    $sizeBytes = 1 << ($sizeInfo & 0x0F);
                    $count = readBplistInt($data, $offset + 2, $sizeBytes);
                    $refsOffset = $offset + 2 + $sizeBytes;
                }
                $result = [];
                $valuesOffset = $refsOffset + $count * $objectRefSize;
                for ($i = 0; $i < $count; $i++) {
                    $keyRef = readBplistInt($data, $refsOffset + $i * $objectRefSize, $objectRefSize);
                    $valRef = readBplistInt($data, $valuesOffset + $i * $objectRefSize, $objectRefSize);
                    $key = $parseObject($keyRef);
                    $val = $parseObject($valRef);
                    if (is_string($key)) $result[$key] = $val;
                }
                return $result;
            })(),
            default => null,
        };
    };

    $root = $parseObject($topObject);
    return is_array($root) ? $root : [];
}

function readBplistInt(string $data, int $offset, int $size): int {
    $val = 0;
    for ($i = 0; $i < $size; $i++) {
        $val = ($val << 8) | ord($data[$offset + $i]);
    }
    return $val;
}

// 解析 embedded.mobileprovision (PKCS7/CMS signed plist)
function parseMobileProvision(string $data): ?array {
    // mobileprovision is a CMS/PKCS7 signed file
    // We need to extract the embedded plist XML from it
    // The plist XML is contained between <?xml and </plist>

    $xmlStart = strpos($data, '<?xml');
    $xmlEnd = strpos($data, '</plist>');

    if ($xmlStart === false || $xmlEnd === false) return null;

    $xmlData = substr($data, $xmlStart, $xmlEnd - $xmlStart + 8);
    $plist = parsePlist($xmlData);

    if (empty($plist)) return null;

    $result = [];

    $result['name'] = $plist['Name'] ?? '未知';
    $result['app_id_name'] = $plist['AppIDName'] ?? null;
    $result['team_name'] = $plist['TeamName'] ?? null;
    $result['team_id'] = $plist['TeamIdentifier'][0] ?? null;

    if (isset($plist['CreationDate'])) {
        $result['creation_date'] = $plist['CreationDate'];
    }
    if (isset($plist['ExpirationDate'])) {
        $result['expiration_date'] = $plist['ExpirationDate'];
        // Check if expired
        $expTime = strtotime($plist['ExpirationDate']);
        if ($expTime) {
            $now = time();
            if ($now > $expTime) {
                $result['is_expired'] = true;
                $result['expiry_status'] = '描述文件已过期';
            } else {
                $result['is_expired'] = false;
                $result['expiry_status'] = '描述文件有效';
                $result['days_remaining'] = (int)ceil(($expTime - $now) / 86400);
            }
        }
    }

    // Provisioning type
    if (isset($plist['ProvisionsAllDevices']) && $plist['ProvisionsAllDevices']) {
        $result['provision_type'] = '企业签名 (Enterprise / In-House)';
    } elseif (!empty($plist['ProvisionedDevices'])) {
        $result['provision_type'] = '开发/Ad Hoc 签名';
        $result['provisioned_devices_count'] = count($plist['ProvisionedDevices']);
        $result['provisioned_devices'] = $plist['ProvisionedDevices'];
    } else {
        $result['provision_type'] = 'App Store 签名';
    }

    // Entitlements
    if (isset($plist['Entitlements'])) {
        $ent = $plist['Entitlements'];
        $result['entitlements'] = [];
        if (isset($ent['application-identifier'])) {
            $result['entitlements']['application_identifier'] = $ent['application-identifier'];
        }
        if (isset($ent['com.apple.developer.team-identifier'])) {
            $result['entitlements']['team_identifier'] = $ent['com.apple.developer.team-identifier'];
        }
        if (isset($ent['get-task-allow'])) {
            $result['entitlements']['get_task_allow'] = $ent['get-task-allow'];
        }
        if (isset($ent['aps-environment'])) {
            $result['entitlements']['push_environment'] = $ent['aps-environment'];
        }
        if (isset($ent['keychain-access-groups'])) {
            $result['entitlements']['keychain_groups'] = $ent['keychain-access-groups'];
        }
    }

    // Developer certificates info
    if (isset($plist['DeveloperCertificates']) && is_array($plist['DeveloperCertificates'])) {
        $certs = [];
        foreach ($plist['DeveloperCertificates'] as $certDer) {
            if (!is_string($certDer) || strlen($certDer) < 10) continue;
            $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($certDer), 64) . "-----END CERTIFICATE-----\n";
            $certInfo = openssl_x509_parse($certPem);
            if ($certInfo) {
                $cert = [];
                $cert['subject'] = formatX509Name($certInfo['subject'] ?? []);
                $cert['issuer'] = formatX509Name($certInfo['issuer'] ?? []);
                $cert['serial'] = $certInfo['serialNumberHex'] ?? '未知';
                $cert['valid_from'] = date('Y-m-d H:i:s', $certInfo['validFrom_time_t'] ?? 0);
                $cert['valid_to'] = date('Y-m-d H:i:s', $certInfo['validTo_time_t'] ?? 0);
                $cert['fingerprint_sha1'] = strtoupper(implode(':', str_split(sha1($certDer), 2)));

                $now = time();
                $validTo = $certInfo['validTo_time_t'] ?? 0;
                $cert['is_valid'] = ($now >= ($certInfo['validFrom_time_t'] ?? 0) && $now <= $validTo);
                if ($cert['is_valid']) {
                    $cert['days_remaining'] = (int)ceil(($validTo - $now) / 86400);
                }

                // OCSP 证书吊销检测
                $ocspResult = checkCertOcsp($certDer, $data);
                if ($ocspResult) {
                    $cert['ocsp_status'] = $ocspResult['status'];
                    $cert['ocsp_detail'] = $ocspResult['detail'];
                    $cert['is_revoked'] = $ocspResult['is_revoked'] ?? null;
                    if (isset($ocspResult['revocation_time'])) {
                        $cert['revocation_time'] = $ocspResult['revocation_time'];
                    }
                    // 如果证书时间上有效但已被吊销，覆盖有效性
                    if ($cert['is_valid'] && ($ocspResult['is_revoked'] ?? false)) {
                        $cert['is_valid'] = false;
                    }
                }

                $certs[] = $cert;
            }
        }
        if ($certs) $result['certificates'] = $certs;
    }

    return array_filter($result, fn($v) => $v !== null);
}

// ==================== 工具函数 ====================

function formatX509Name(array $name): string {
    $parts = [];
    foreach (['CN', 'O', 'OU', 'L', 'ST', 'C'] as $key) {
        if (isset($name[$key])) {
            $val = is_array($name[$key]) ? implode(', ', $name[$key]) : $name[$key];
            $parts[] = "$key=$val";
        }
    }
    return implode(', ', $parts) ?: '未知';
}

function sdkToAndroidVersion(int $sdk): string {
    $map = [
        1 => '1.0', 2 => '1.1', 3 => '1.5', 4 => '1.6',
        5 => '2.0', 6 => '2.0.1', 7 => '2.1', 8 => '2.2',
        9 => '2.3', 10 => '2.3.3', 11 => '3.0', 12 => '3.1',
        13 => '3.2', 14 => '4.0', 15 => '4.0.3', 16 => '4.1',
        17 => '4.2', 18 => '4.3', 19 => '4.4', 20 => '4.4W',
        21 => '5.0', 22 => '5.1', 23 => '6.0', 24 => '7.0',
        25 => '7.1', 26 => '8.0', 27 => '8.1', 28 => '9',
        29 => '10', 30 => '11', 31 => '12', 32 => '12L',
        33 => '13', 34 => '14', 35 => '15', 36 => '16',
    ];
    return 'Android ' . ($map[$sdk] ?? "API $sdk");
}

function formatSize(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// ==================== OCSP 证书吊销检测 ====================

/**
 * 检查证书是否被苹果吊销（通过 OCSP 协议实时查询）
 */
function checkCertOcsp(string $certDer, string $provisionData): ?array {
    if (!function_exists('curl_init')) {
        return ['status' => 'error', 'detail' => 'cURL 扩展未启用', 'is_revoked' => null];
    }
    if (!extension_loaded('openssl')) {
        return ['status' => 'error', 'detail' => 'OpenSSL 扩展未启用', 'is_revoked' => null];
    }

    // 1. 解析证书，提取 OCSP URL
    $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($certDer), 64) . "-----END CERTIFICATE-----\n";
    $certInfo = openssl_x509_parse($certPem);
    if (!$certInfo) return null;

    $ocspUrl = null;
    $extensions = $certInfo['extensions'] ?? [];
    if (isset($extensions['authorityInfoAccess'])) {
        if (preg_match('/OCSP\s*-\s*URI:(\S+)/i', $extensions['authorityInfoAccess'], $m)) {
            $ocspUrl = trim($m[1]);
        }
    }
    if (!$ocspUrl) {
        return ['status' => 'unknown', 'detail' => '证书中未包含 OCSP 地址', 'is_revoked' => null];
    }

    // 2. 从 mobileprovision PKCS7 链中查找颁发者证书
    $issuerCertDer = findIssuerCertFromPkcs7($certPem, $provisionData);
    if (!$issuerCertDer) {
        return ['status' => 'unknown', 'detail' => '无法提取颁发者证书', 'is_revoked' => null];
    }

    // 3. 提取 OCSP 请求所需字段
    $issuerNameDer = derExtractIssuerName($certDer);
    $issuerKeyBits = derExtractSubjectPubKeyBits($issuerCertDer);
    $serialRaw = derExtractSerialNumber($certDer);

    if (!$issuerNameDer || !$issuerKeyBits || !$serialRaw) {
        return ['status' => 'unknown', 'detail' => '证书字段提取失败', 'is_revoked' => null];
    }

    $issuerNameHash = sha1($issuerNameDer, true);
    $issuerKeyHash = sha1($issuerKeyBits, true);

    // 4. 构建并发送 OCSP 请求
    $ocspReq = ocspBuildRequest($issuerNameHash, $issuerKeyHash, $serialRaw);
    $ocspResp = ocspSendRequest($ocspUrl, $ocspReq);

    if (!$ocspResp) {
        return ['status' => 'unknown', 'detail' => 'OCSP 服务器无响应（网络不通或超时）', 'is_revoked' => null];
    }

    // 5. 解析响应
    return ocspParseResponse($ocspResp);
}

/**
 * 从 mobileprovision 的 PKCS7 证书链中查找颁发者证书
 */
function findIssuerCertFromPkcs7(string $leafCertPem, string $provisionData): ?string {
    $p7pem = "-----BEGIN PKCS7-----\n" . chunk_split(base64_encode($provisionData), 64) . "-----END PKCS7-----\n";
    $certs = [];
    if (!openssl_pkcs7_read($p7pem, $certs)) return null;

    $leafInfo = openssl_x509_parse($leafCertPem);
    if (!$leafInfo) return null;
    $wantIssuer = $leafInfo['issuer'] ?? [];

    foreach ($certs as $pem) {
        $info = openssl_x509_parse($pem);
        if (!$info) continue;
        if (($info['subject'] ?? []) == $wantIssuer) {
            // 转回 DER
            $clean = preg_replace('/-----[^-]+-----/', '', $pem);
            return base64_decode(str_replace(["\n", "\r", " "], '', $clean));
        }
    }
    return null;
}

// ===== DER 解析工具 =====

function derParseEl(string $data, int $offset): ?array {
    $len = strlen($data);
    if ($offset >= $len) return null;
    $start = $offset;
    $tag = ord($data[$offset++]);
    if ($offset >= $len) return null;
    $lb = ord($data[$offset++]);
    if ($lb < 128) {
        $cLen = $lb;
    } elseif ($lb === 0x80) {
        return null; // indefinite length not supported
    } else {
        $nb = $lb & 0x7F;
        if ($offset + $nb > $len) return null;
        $cLen = 0;
        for ($i = 0; $i < $nb; $i++) $cLen = ($cLen << 8) | ord($data[$offset++]);
    }
    return ['tag' => $tag, 'start' => $start, 'vOff' => $offset, 'vLen' => $cLen, 'total' => $offset - $start + $cLen];
}

function derParseChildren(string $data, int $offset, int $length): array {
    $children = [];
    $end = $offset + $length;
    while ($offset < $end) {
        $el = derParseEl($data, $offset);
        if (!$el) break;
        $children[] = $el;
        $offset = $el['vOff'] + $el['vLen'];
    }
    return $children;
}

/**
 * 从证书 DER 中提取 issuer Name 的完整 DER 编码
 */
function derExtractIssuerName(string $certDer): ?string {
    $cert = derParseEl($certDer, 0);
    if (!$cert || $cert['tag'] !== 0x30) return null;
    $kids = derParseChildren($certDer, $cert['vOff'], $cert['vLen']);
    if (empty($kids)) return null;

    // TBSCertificate
    $tbs = $kids[0];
    $tbsKids = derParseChildren($certDer, $tbs['vOff'], $tbs['vLen']);
    $idx = 0;
    if (!empty($tbsKids) && ($tbsKids[0]['tag'] & 0xE0) === 0xA0) $idx++; // skip version [0]
    $idx++; // serial
    $idx++; // sigAlg
    // issuer Name
    if (!isset($tbsKids[$idx])) return null;
    $n = $tbsKids[$idx];
    return substr($certDer, $n['start'], $n['total']);
}

/**
 * 从证书 DER 中提取序列号原始字节
 */
function derExtractSerialNumber(string $certDer): ?string {
    $cert = derParseEl($certDer, 0);
    if (!$cert || $cert['tag'] !== 0x30) return null;
    $kids = derParseChildren($certDer, $cert['vOff'], $cert['vLen']);
    if (empty($kids)) return null;

    $tbs = $kids[0];
    $tbsKids = derParseChildren($certDer, $tbs['vOff'], $tbs['vLen']);
    $idx = 0;
    if (!empty($tbsKids) && ($tbsKids[0]['tag'] & 0xE0) === 0xA0) $idx++;
    // serial INTEGER
    if (!isset($tbsKids[$idx])) return null;
    $s = $tbsKids[$idx];
    return substr($certDer, $s['vOff'], $s['vLen']);
}

/**
 * 从证书 DER 中提取 SubjectPublicKey 比特串内容（去掉 unused bits 字节）
 */
function derExtractSubjectPubKeyBits(string $certDer): ?string {
    $cert = derParseEl($certDer, 0);
    if (!$cert || $cert['tag'] !== 0x30) return null;
    $kids = derParseChildren($certDer, $cert['vOff'], $cert['vLen']);
    if (empty($kids)) return null;

    $tbs = $kids[0];
    $tbsKids = derParseChildren($certDer, $tbs['vOff'], $tbs['vLen']);
    $idx = 0;
    if (!empty($tbsKids) && ($tbsKids[0]['tag'] & 0xE0) === 0xA0) $idx++;
    $idx++; // serial
    $idx++; // sigAlg
    $idx++; // issuer
    $idx++; // validity
    $idx++; // subject
    // SubjectPublicKeyInfo SEQUENCE { algorithm, subjectPublicKey BIT STRING }
    if (!isset($tbsKids[$idx])) return null;
    $spki = $tbsKids[$idx];
    $spkiKids = derParseChildren($certDer, $spki['vOff'], $spki['vLen']);
    if (count($spkiKids) < 2) return null;
    $bs = $spkiKids[1]; // BIT STRING
    if ($bs['vLen'] < 2) return null;
    // skip unused-bits byte
    return substr($certDer, $bs['vOff'] + 1, $bs['vLen'] - 1);
}

// ===== DER 编码工具 =====

function derEncLen(int $len): string {
    if ($len < 128) return chr($len);
    $b = '';
    $t = $len;
    while ($t > 0) { $b = chr($t & 0xFF) . $b; $t >>= 8; }
    return chr(0x80 | strlen($b)) . $b;
}

function derSeq(string $content): string {
    return "\x30" . derEncLen(strlen($content)) . $content;
}

function derOctet(string $data): string {
    return "\x04" . derEncLen(strlen($data)) . $data;
}

function derInt(string $raw): string {
    return "\x02" . derEncLen(strlen($raw)) . $raw;
}

function derOid(string $oid): string {
    $p = array_map('intval', explode('.', $oid));
    $enc = chr($p[0] * 40 + $p[1]);
    for ($i = 2; $i < count($p); $i++) {
        $v = $p[$i];
        if ($v < 128) { $enc .= chr($v); }
        else {
            $bytes = [];
            while ($v > 0) { $bytes[] = $v & 0x7F; $v >>= 7; }
            $bytes = array_reverse($bytes);
            for ($j = 0; $j < count($bytes); $j++) {
                $enc .= chr($bytes[$j] | ($j < count($bytes) - 1 ? 0x80 : 0));
            }
        }
    }
    return "\x06" . derEncLen(strlen($enc)) . $enc;
}

// ===== OCSP 请求构建与响应解析 =====

function ocspBuildRequest(string $issuerNameHash, string $issuerKeyHash, string $serialRaw): string {
    // SHA-1 AlgorithmIdentifier: OID 1.3.14.3.2.26 + NULL
    $sha1AlgId = derSeq(derOid('1.3.14.3.2.26') . "\x05\x00");
    // CertID
    $certId = derSeq($sha1AlgId . derOctet($issuerNameHash) . derOctet($issuerKeyHash) . derInt($serialRaw));
    // Request -> SEQUENCE { certID }
    $request = derSeq($certId);
    // RequestList -> SEQUENCE OF Request
    $requestList = derSeq($request);
    // TBSRequest -> SEQUENCE { requestList }
    $tbsRequest = derSeq($requestList);
    // OCSPRequest -> SEQUENCE { tbsRequest }
    return derSeq($tbsRequest);
}

function ocspSendRequest(string $url, string $requestDer): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $requestDer,
        CURLOPT_HTTPHEADER => ['Content-Type: application/ocsp-request', 'Accept: application/ocsp-response'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && $resp !== false && $resp !== '') ? $resp : null;
}

function ocspParseResponse(string $data): ?array {
    $len = strlen($data);
    if ($len < 3) return null;

    // OCSPResponse -> SEQUENCE { responseStatus ENUMERATED, responseBytes [0]? }
    $resp = derParseEl($data, 0);
    if (!$resp || $resp['tag'] !== 0x30) return null;
    $respKids = derParseChildren($data, $resp['vOff'], $resp['vLen']);
    if (empty($respKids)) return null;

    // responseStatus
    $status = ord(substr($data, $respKids[0]['vOff'], 1));
    if ($status !== 0) {
        $names = [0 => 'successful', 1 => 'malformedRequest', 2 => 'internalError', 3 => 'tryLater', 5 => 'sigRequired', 6 => 'unauthorized'];
        return ['status' => 'error', 'detail' => 'OCSP 错误: ' . ($names[$status] ?? "code $status"), 'is_revoked' => null];
    }
    if (count($respKids) < 2) return null;

    // responseBytes [0] -> SEQUENCE { responseType OID, response OCTET STRING }
    $rbCtx = $respKids[1];
    $rbSeq = derParseEl($data, $rbCtx['vOff']);
    if (!$rbSeq) return null;
    $rbKids = derParseChildren($data, $rbSeq['vOff'], $rbSeq['vLen']);
    if (count($rbKids) < 2) return null;

    // BasicOCSPResponse inside the OCTET STRING
    $basicRaw = substr($data, $rbKids[1]['vOff'], $rbKids[1]['vLen']);

    // BasicOCSPResponse -> SEQUENCE { tbsResponseData, sigAlg, sig, ... }
    $basic = derParseEl($basicRaw, 0);
    if (!$basic) return null;
    $basicKids = derParseChildren($basicRaw, $basic['vOff'], $basic['vLen']);
    if (empty($basicKids)) return null;

    // tbsResponseData -> SEQUENCE { version?, responderID, producedAt, responses SEQUENCE }
    $tbsResp = $basicKids[0];
    $tbsKids = derParseChildren($basicRaw, $tbsResp['vOff'], $tbsResp['vLen']);

    // 找到 responses：第一个 tag=0x30 的子元素，其内部也包含 SEQUENCE 子元素
    $responsesSeq = null;
    foreach ($tbsKids as $kid) {
        if ($kid['tag'] === 0x30) {
            $inner = derParseChildren($basicRaw, $kid['vOff'], $kid['vLen']);
            if (!empty($inner) && $inner[0]['tag'] === 0x30) {
                $responsesSeq = $kid;
                break;
            }
        }
    }
    if (!$responsesSeq) return null;

    $singles = derParseChildren($basicRaw, $responsesSeq['vOff'], $responsesSeq['vLen']);
    if (empty($singles)) return null;

    // SingleResponse -> SEQUENCE { certID, certStatus, thisUpdate, ... }
    $sr = $singles[0];
    $srKids = derParseChildren($basicRaw, $sr['vOff'], $sr['vLen']);
    if (count($srKids) < 2) return null;

    $csEl = $srKids[1];
    $csTag = $csEl['tag'] & 0x1F;

    // [0] good, [1] revoked, [2] unknown
    if ($csTag === 0) {
        return ['status' => 'good', 'detail' => '证书有效（未被吊销）', 'is_revoked' => false];
    }
    if ($csTag === 1) {
        $revokedTime = '未知';
        if ($csEl['vLen'] > 0) {
            $rvKids = derParseChildren($basicRaw, $csEl['vOff'], $csEl['vLen']);
            if (!empty($rvKids)) {
                $timeRaw = substr($basicRaw, $rvKids[0]['vOff'], $rvKids[0]['vLen']);
                $clean = rtrim($timeRaw, 'Z');
                if (strlen($clean) >= 14) {
                    $revokedTime = substr($clean, 0, 4) . '-' . substr($clean, 4, 2) . '-' . substr($clean, 6, 2) . ' ' .
                                   substr($clean, 8, 2) . ':' . substr($clean, 10, 2) . ':' . substr($clean, 12, 2) . ' UTC';
                }
            }
        }
        return ['status' => 'revoked', 'detail' => '证书已被吊销（掉签）', 'is_revoked' => true, 'revocation_time' => $revokedTime];
    }
    return ['status' => 'unknown', 'detail' => '证书状态未知', 'is_revoked' => null];
}
