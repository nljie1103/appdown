<?php
/**
 * 数据导入导出 API — ZIP + AES-256-GCM
 * v3 加密格式使用 Argon2id(sodium) KDF，兼容旧 v1/v2 备份。
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();
csrf_validate();
require_method('POST');

$tenant = require_tenant_context();
$tenantSlug = $tenant['slug'];
$pdo = get_db();
$isMultipart = !empty($_FILES['file']);
if ($isMultipart) $action = $_POST['action'] ?? '';
else { $data = get_json_input(); $action = $data['action'] ?? ''; }

$allTables = [
    'site_settings', 'apps', 'app_downloads', 'app_images',
    'feature_categories', 'feature_cards', 'friend_links', 'custom_code',
    'app_platforms', 'app_attachments', 'image_categories', 'image_library',
    'keystores', 'mc_certificates', 'generated_mobileconfigs', 'generated_apks', 'generated_ipas'
];

if ($action === 'export') {
    $password = $data['password'] ?? '';
    $selectedTables = $data['tables'] ?? [];
    $includeUploads = !empty($data['include_uploads']);

    if ($password !== '' && strlen($password) < 8) json_response(['error' => '加密密码至少8位'], 400);
    if (empty($selectedTables) && !$includeUploads) json_response(['error' => '请选择要导出的数据'], 400);
    if (!class_exists('ZipArchive')) json_response(['error' => '服务器未安装PHP zip扩展，请联系服务商启用'], 500);

    @set_time_limit(600);
    @ini_set('memory_limit', '512M');
    $tmpZip = tempnam(sys_get_temp_dir(), 'appdown_export_');
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) json_response(['error' => '创建备份文件失败'], 500);

    $selectedTables = array_values(array_unique(array_intersect($selectedTables, $allTables)));
    $appsSelected = in_array('apps', $selectedTables, true);
    // 选择应用数据时自动带上版本/构建历史。签名材料只进入“有密码”的备份，避免私钥落入明文 ZIP。
    if ($appsSelected) {
        foreach (['generated_mobileconfigs', 'generated_apks', 'generated_ipas'] as $related) {
            if (!in_array($related, $selectedTables, true)) $selectedTables[] = $related;
        }
        if ($password !== '') {
            foreach (['keystores', 'mc_certificates'] as $related) {
                if (!in_array($related, $selectedTables, true)) $selectedTables[] = $related;
            }
        }
    }

    $export = ['meta' => [
        'version' => '3.0',
        'exported_at' => date('Y-m-d H:i:s'),
        'app_name' => 'AppDown SaaS',
        'tenant_slug' => $tenantSlug,
        'auto_included_app_history' => $appsSelected,
        'signing_materials_included' => $appsSelected && $password !== '',
        'kdf' => $password === '' ? 'none' : (function_exists('sodium_crypto_pwhash') ? 'argon2id' : 'pbkdf2-sha256'),
    ]];
    foreach ($selectedTables as $table) {
        try {
            $rows = $pdo->query('SELECT * FROM "' . $table . '"')->fetchAll();
            // Keystore 密码在内层 ZIP 中恢复为明文，外层 .enc 负责保护；导入时再用目标服务器主密钥加密。
            // 这样加密备份可跨服务器恢复，不依赖原服务器 data/.secret.key。
            if ($table === 'keystores') {
                foreach ($rows as &$row) {
                    $row['store_password'] = decrypt_secret((string)($row['store_password'] ?? ''));
                    $row['key_password'] = decrypt_secret((string)($row['key_password'] ?? ''));
                }
                unset($row);
            }
            if ($table === 'mc_certificates') {
                foreach ($rows as &$row) {
                    if ($password !== '') $row['key'] = decrypt_secret((string)($row['key'] ?? ''));
                    else $row['key'] = '';
                }
                unset($row);
            }
            // 明文 ZIP 不携带旧版应用级/全局 Mobileconfig 私钥。
            if ($password === '' && $table === 'apps') {
                foreach ($rows as &$row) {
                    foreach (['mc_sign_cert', 'mc_sign_key', 'mc_sign_chain'] as $field) if (array_key_exists($field, $row)) $row[$field] = '';
                }
                unset($row);
            }
            if ($password === '' && $table === 'site_settings') {
                foreach ($rows as &$row) {
                    if (in_array((string)($row['setting_key'] ?? ''), ['mc_sign_cert', 'mc_sign_key', 'mc_sign_chain'], true)) $row['setting_val'] = '';
                }
                unset($row);
            }
            $export[$table] = $rows;
        } catch (\Throwable $e) {
            if ($table === 'keystores') {
                $zip->close(); @unlink($tmpZip);
                json_response(['error' => '签名密钥备份失败：' . $e->getMessage()], 500);
            }
            $export[$table] = [];
        }
    }
    $zip->addFromString('data.json', json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    if ($appsSelected && $password === '') {
        $zip->addFromString('SECURITY-NOTICE.txt', "This unencrypted AppDown backup intentionally omits signing private keys and keystore passwords.\nCreate a password-protected .enc backup when you need full signing-material migration.\n");
    }

    if ($includeUploads) {
        $uploadsBase = realpath(appdown_upload_dir());
        if ($uploadsBase && is_dir($uploadsBase)) addDirToZip($zip, $uploadsBase, 'uploads', $password !== '');
    }
    $zip->close();

    if ($password === '') {
        stream_download_file($tmpZip, 'appdown_' . $tenantSlug . '_backup_' . date('Ymd_His') . '.zip');
    }

    $zipData = file_get_contents($tmpZip);
    @unlink($tmpZip);
    if ($zipData === false) json_response(['error' => '读取临时备份失败'], 500);
    $packed = encryptBackupV3($zipData, $password);
    unset($zipData);
    $filename = 'appdown_' . $tenantSlug . '_backup_' . date('Ymd_His') . '.enc';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($packed));
    header('X-Filename: ' . $filename);
    header('Access-Control-Expose-Headers: X-Filename');
    echo $packed;
    exit;
}

if ($action === 'decrypt_preview') {
    $password = $_POST['password'] ?? '';
    if (!isset($_FILES['file']['tmp_name'])) json_response(['error' => '未收到文件'], 400);
    $raw = file_get_contents($_FILES['file']['tmp_name']);
    if ($raw === false) json_response(['error' => '读取文件失败'], 400);
    $result = decryptAndParse($raw, $password);
    if ($result === null) return;

    $import = $result['data'];
    $tables = [];
    foreach ($import as $key => $rows) if ($key !== 'meta' && is_array($rows)) $tables[$key] = count($rows);

    $hasUploads = false; $uploadsCount = 0; $uploadsSize = 0;
    if ($result['is_zip'] && $result['zip_path']) {
        $zip = new ZipArchive();
        if ($zip->open($result['zip_path']) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i); if (!$stat) continue;
                $name = (string)$stat['name'];
                if (str_starts_with($name, 'uploads/') && !str_ends_with($name, '/')) {
                    $hasUploads = true; $uploadsCount++; $uploadsSize += (int)$stat['size'];
                }
            }
            $zip->close();
        }
        @unlink($result['zip_path']);
    }

    $sizeStr = $uploadsSize >= 1073741824 ? round($uploadsSize / 1073741824, 1) . ' GB'
        : ($uploadsSize >= 1048576 ? round($uploadsSize / 1048576, 1) . ' MB' : round($uploadsSize / 1024, 1) . ' KB');
    json_response(['ok' => true, 'meta' => $import['meta'] ?? [], 'tables' => $tables, 'has_uploads' => $hasUploads, 'uploads_count' => $uploadsCount, 'uploads_size' => $sizeStr]);
}

if ($action === 'import') {
    $password = $_POST['password'] ?? '';
    $selectedTables = json_decode($_POST['tables'] ?? '[]', true) ?: [];
    $includeUploads = ($_POST['include_uploads'] ?? '0') === '1';
    if (empty($selectedTables) && !$includeUploads) json_response(['error' => '请选择要导入的数据'], 400);
    if (!isset($_FILES['file']['tmp_name'])) json_response(['error' => '未收到文件'], 400);

    $raw = file_get_contents($_FILES['file']['tmp_name']);
    if ($raw === false) json_response(['error' => '读取文件失败'], 400);
    $result = decryptAndParse($raw, $password);
    if ($result === null) return;
    $import = $result['data']; $isZip = $result['is_zip']; $zipPath = $result['zip_path'] ?? null;

    // 只接受白名单表。
    $selectedTables = array_values(array_intersect($selectedTables, $allTables));
    $clearOrder = [
        'app_attachments', 'generated_mobileconfigs', 'generated_apks', 'generated_ipas',
        'mc_certificates', 'keystores', 'app_platforms', 'app_images', 'app_downloads',
        'image_library', 'image_categories', 'feature_cards', 'feature_categories',
        'friend_links', 'custom_code', 'site_settings', 'apps'
    ];
    $insertOrder = [
        'apps', 'site_settings', 'custom_code', 'friend_links',
        'feature_categories', 'feature_cards', 'image_categories', 'image_library',
        'app_downloads', 'app_images', 'app_platforms', 'app_attachments',
        'keystores', 'mc_certificates', 'generated_mobileconfigs', 'generated_apks', 'generated_ipas'
    ];

    $sourceTenantSlug = normalize_tenant_slug((string)($import['meta']['tenant_slug'] ?? ''));
    $currentUploadPrefix = appdown_upload_url_prefix();

    $pdo->beginTransaction();
    try {
        foreach ($clearOrder as $table) if (in_array($table, $selectedTables, true)) $pdo->exec("DELETE FROM \"$table\"");
        $imported = 0;
        foreach ($insertOrder as $table) {
            if (!in_array($table, $selectedTables, true)) continue;
            $rows = $import[$table] ?? [];
            if (!is_array($rows)) continue;
            $tableInfo = $pdo->query("PRAGMA table_info(\"$table\")")->fetchAll();
            $validCols = array_column($tableInfo, 'name');
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $row = array_intersect_key($row, array_flip($validCols));
                if (!$row) continue;
                // SaaS 备份恢复到不同用户名时，所有租户本地文件路径改写为当前租户前缀。
                foreach ($row as $field => $value) {
                    if (!is_string($value) || $value === '') continue;
                    if ($sourceTenantSlug !== '') {
                        $value = str_replace(
                            ['uploads/tenants/' . $sourceTenantSlug . '/', '/uploads/tenants/' . $sourceTenantSlug . '/'],
                            [$currentUploadPrefix . '/', '/' . $currentUploadPrefix . '/'],
                            $value
                        );
                    }
                    if (preg_match('#^(/?)uploads/(?!tenants/)(.+)$#', $value, $m)) {
                        $value = $m[1] . $currentUploadPrefix . '/' . $m[2];
                    }
                    $row[$field] = $value;
                }
                // v3 备份中的 keystore 密码使用目标服务器主密钥重新加密。
                if ($table === 'keystores') {
                    foreach (['store_password', 'key_password'] as $secretField) {
                        if (array_key_exists($secretField, $row) && !is_encrypted_secret((string)$row[$secretField])) {
                            $row[$secretField] = encrypt_secret((string)$row[$secretField]);
                        }
                    }
                }
                if ($table === 'mc_certificates' && array_key_exists('key', $row) && !is_encrypted_secret((string)$row['key'])) {
                    $row['key'] = encrypt_secret((string)$row['key']);
                }
                $cols = array_keys($row);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $colList = implode(',', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $cols));
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO \"$table\" ($colList) VALUES ($placeholders)");
                $stmt->execute(array_values($row)); $imported++;
            }
        }
        $pdo->commit();
        clear_config_cache();
    } catch (\Exception $e) {
        $pdo->rollBack();
        if ($zipPath && file_exists($zipPath)) @unlink($zipPath);
        json_response(['error' => '导入失败: ' . $e->getMessage()], 500);
    }

    $filesRestored = 0;
    if ($includeUploads && $isZip && $zipPath) $filesRestored = restoreUploadsFromZip($zipPath);
    if ($zipPath && file_exists($zipPath)) @unlink($zipPath);
    $tableCount = count(array_filter($selectedTables, fn($t) => !empty($import[$t])));
    $msg = "导入成功，共恢复 {$tableCount} 类数据（{$imported} 条记录）";
    if ($filesRestored > 0) $msg .= "，{$filesRestored} 个上传文件";
    json_response(['ok' => true, 'message' => $msg]);
}

json_response(['error' => '无效操作'], 400);

function stream_download_file(string $path, string $filename): void {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Filename: ' . $filename);
    header('Access-Control-Expose-Headers: X-Filename');
    readfile($path); @unlink($path); exit;
}

function encryptBackupV3(string $data, string $password): string {
    $magic = "ADBK3\0";
    $algorithm = function_exists('sodium_crypto_pwhash') ? 'argon2id' : 'pbkdf2';
    $kdfId = $algorithm === 'argon2id' ? "\x01" : "\x02";
    $salt = random_bytes(16); $iv = random_bytes(12); $tag = '';
    $key = appdown_password_kdf($password, $salt, $algorithm);
    $aad = $magic . $kdfId;
    $cipher = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
    if ($cipher === false) json_response(['error' => '加密失败'], 500);
    return $magic . $kdfId . $salt . $iv . $tag . $cipher;
}

function decryptAndParse(string $raw, string $password): ?array {
    $decrypted = null;
    if (str_starts_with($raw, 'PK')) $decrypted = $raw;
    if ($decrypted === null && str_starts_with($raw, '{')) {
        $test = json_decode($raw, true);
        if ($test && isset($test['meta'])) return ['data' => $test, 'is_zip' => false, 'zip_path' => null];
    }

    if ($decrypted === null && $password !== '') {
        $decrypted = tryDecrypt($raw, $password);
        if ($decrypted === null) {
            $decoded = base64_decode($raw, true);
            if ($decoded !== false) $decrypted = tryDecrypt($decoded, $password);
        }
    }
    if ($decrypted === null) {
        json_response(['error' => '解密失败：密码错误或数据损坏'], 400);
        return null;
    }

    if (str_starts_with($decrypted, 'PK')) {
        $tmpFile = tempnam(sys_get_temp_dir(), 'appdown_import_');
        file_put_contents($tmpFile, $decrypted);
        if (!class_exists('ZipArchive')) { @unlink($tmpFile); json_response(['error' => '服务器未安装PHP zip扩展'], 500); return null; }
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) { @unlink($tmpFile); json_response(['error' => '备份文件损坏，无法打开ZIP'], 400); return null; }
        $safe = validate_zip_safety($zip);
        if (!$safe['ok']) { $zip->close(); @unlink($tmpFile); json_response(['error' => $safe['error']], 400); return null; }
        $jsonStr = $zip->getFromName('data.json');
        $zip->close();
        if ($jsonStr === false) { @unlink($tmpFile); json_response(['error' => '备份文件中缺少data.json'], 400); return null; }
        $data = json_decode($jsonStr, true);
        if (!$data || !isset($data['meta'])) { @unlink($tmpFile); json_response(['error' => '备份数据格式无效'], 400); return null; }
        return ['data' => $data, 'is_zip' => true, 'zip_path' => $tmpFile];
    }

    $data = json_decode($decrypted, true);
    if (!$data || !isset($data['meta'])) { json_response(['error' => '数据格式无效，不是有效的AppDown备份'], 400); return null; }
    return ['data' => $data, 'is_zip' => false, 'zip_path' => null];
}

function tryDecrypt(string $raw, string $password): ?string {
    $magic = "ADBK3\0";
    if (str_starts_with($raw, $magic)) {
        $offset = strlen($magic);
        if (strlen($raw) < $offset + 45) return null;
        $kdfId = substr($raw, $offset, 1); $offset += 1;
        $algorithm = $kdfId === "\x01" ? 'argon2id' : ($kdfId === "\x02" ? 'pbkdf2' : '');
        if ($algorithm === '') return null;
        $salt = substr($raw, $offset, 16); $offset += 16;
        $iv = substr($raw, $offset, 12); $offset += 12;
        $tag = substr($raw, $offset, 16); $offset += 16;
        $ciphertext = substr($raw, $offset);
        try {
            $key = appdown_password_kdf($password, $salt, $algorithm);
        } catch (RuntimeException $e) {
            json_response(['error' => $e->getMessage()], 500);
            return null;
        }
        $aad = $magic . $kdfId;
        $result = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        return $result === false ? null : $result;
    }

    // 旧 v1/v2：12B IV + 16B tag + AES-GCM(SHA256(password))
    if (strlen($raw) < 28) return null;
    $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $ciphertext = substr($raw, 28);
    $key = hash('sha256', $password, true);
    $result = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $result === false ? null : $result;
}

function restoreUploadsFromZip(string $zipPath): int {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return 0;
    $safe = validate_zip_safety($zip);
    if (!$safe['ok']) { $zip->close(); json_response(['error' => $safe['error']], 400); }
    $tenantUploadDir = appdown_upload_dir();
    $uploadsRoot = realpath($tenantUploadDir);
    if (!$uploadsRoot) { @mkdir($tenantUploadDir, 0755, true); $uploadsRoot = realpath($tenantUploadDir); }
    if (!$uploadsRoot) { $zip->close(); json_response(['error' => '无法创建租户 uploads 目录'], 500); }

    $restored = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if (!str_starts_with($name, 'uploads/') || str_ends_with($name, '/')) continue;
        $relative = substr($name, strlen('uploads/'));
        if ($relative === '') continue;
        $destPath = $uploadsRoot . '/' . $relative;
        $destDir = dirname($destPath);
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) continue;
        $realDir = realpath($destDir);
        if (!$realDir || !str_starts_with($realDir, $uploadsRoot)) continue;
        $in = $zip->getStream($name);
        if (!$in) continue;
        $out = @fopen($destPath, 'wb');
        if (!$out) { fclose($in); continue; }
        stream_copy_to_stream($in, $out); fclose($in); fclose($out); $restored++;
    }
    $zip->close();
    return $restored;
}

function addDirToZip(ZipArchive $zip, string $dir, string $prefix, bool $includeSensitive = false): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $filePath = $file->getRealPath();
        $relative = substr(str_replace(DIRECTORY_SEPARATOR, '/', $filePath), strlen(str_replace(DIRECTORY_SEPARATOR, '/', $dir)) + 1);
        if (!$includeSensitive && (str_starts_with($relative, 'keystores/') || str_starts_with($relative, 'certs/'))) continue;
        $relativePath = $prefix . '/' . $relative;
        $zip->addFile($filePath, $relativePath);
    }
}
