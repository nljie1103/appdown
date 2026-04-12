<?php
/**
 * 数据导入导出API — ZIP打包 + AES-256-GCM加密
 * 支持选择性表导出/导入 + uploads/ 文件目录打包
 * 兼容旧版（纯JSON加密）备份格式
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();
csrf_validate();
require_method('POST');

$pdo = get_db();

// 检测请求类型：JSON body（导出）或 multipart（导入/预览）
$isMultipart = !empty($_FILES['file']);
if ($isMultipart) {
    $action = $_POST['action'] ?? '';
} else {
    $data = get_json_input();
    $action = $data['action'] ?? '';
}

$allTables = [
    'site_settings', 'apps', 'app_downloads', 'app_images',
    'feature_categories', 'feature_cards', 'friend_links', 'custom_code',
    'app_platforms', 'app_attachments',
    'image_categories', 'image_library',
    'admin_users'
];

// ========== 导出 ==========
if ($action === 'export') {
    $password = $data['password'] ?? '';
    $selectedTables = $data['tables'] ?? [];
    $includeUploads = !empty($data['include_uploads']);

    if (strlen($password) > 0 && strlen($password) < 4) json_response(['error' => '加密密码至少4位'], 400);
    if (empty($selectedTables) && !$includeUploads) json_response(['error' => '请选择要导出的数据'], 400);
    if (!class_exists('ZipArchive')) json_response(['error' => '服务器未安装PHP zip扩展，请联系服务商启用'], 500);

    // 提高限制以支持大文件打包
    @set_time_limit(600);
    @ini_set('memory_limit', '512M');

    $tmpZip = tempnam(sys_get_temp_dir(), 'appdown_export_');
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        json_response(['error' => '创建备份文件失败'], 500);
    }

    // 添加 data.json
    $export = [
        'meta' => [
            'version' => '2.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'app_name' => 'AppDown',
        ],
    ];
    foreach ($selectedTables as $table) {
        if (!in_array($table, $allTables, true)) continue;
        try {
            $export[$table] = $pdo->query("SELECT * FROM \"$table\"")->fetchAll();
        } catch (\Exception $e) {
            $export[$table] = [];
        }
    }
    $zip->addFromString('data.json', json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // 添加 uploads/ 目录
    if ($includeUploads) {
        $uploadsBase = realpath(__DIR__ . '/../../uploads');
        if ($uploadsBase && is_dir($uploadsBase)) {
            addDirToZip($zip, $uploadsBase, 'uploads');
        }
    }

    $zip->close();

    // 无密码：流式输出ZIP文件，不加载到内存
    if ($password === '') {
        $fileSize = filesize($tmpZip);
        $filename = 'appdown_backup_' . date('Ymd_His') . '.zip';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $fileSize);
        header('X-Filename: ' . $filename);
        header('Access-Control-Expose-Headers: X-Filename');
        readfile($tmpZip);
        unlink($tmpZip);
        exit;
    }

    // 有密码：分块读取加密
    $zipData = file_get_contents($tmpZip);
    unlink($tmpZip);

    $key = hash('sha256', $password, true);
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($zipData, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    unset($zipData); // 尽快释放内存
    if ($encrypted === false) json_response(['error' => '加密失败'], 500);
    $packed = $iv . $tag . $encrypted;
    unset($encrypted);

    $filename = 'appdown_backup_' . date('Ymd_His') . '.enc';

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($packed));
    header('X-Filename: ' . $filename);
    header('Access-Control-Expose-Headers: X-Filename');
    echo $packed;
    exit;
}

// ========== 解密预览 ==========
if ($action === 'decrypt_preview') {
    $password = $_POST['password'] ?? '';
    if (!isset($_FILES['file']['tmp_name'])) json_response(['error' => '未收到文件'], 400);

    $raw = file_get_contents($_FILES['file']['tmp_name']);
    $result = decryptAndParse($raw, $password);
    if ($result === null) return;

    $import = $result['data'];
    $isZip = $result['is_zip'];

    // 统计各表记录数
    $tables = [];
    foreach ($import as $key => $rows) {
        if ($key === 'meta' || !is_array($rows)) continue;
        $tables[$key] = count($rows);
    }

    // 统计 uploads 信息
    $hasUploads = false;
    $uploadsCount = 0;
    $uploadsSize = 0;

    if ($isZip && $result['zip_path']) {
        $zip = new ZipArchive();
        if ($zip->open($result['zip_path']) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if (substr($name, 0, strlen('uploads/')) === 'uploads/' && substr($name, -1) !== '/') {
                    $hasUploads = true;
                    $uploadsCount++;
                    $uploadsSize += $stat['size'];
                }
            }
            $zip->close();
        }
        unlink($result['zip_path']);
    }

    $sizeStr = '';
    if ($uploadsSize >= 1073741824) {
        $sizeStr = round($uploadsSize / 1073741824, 1) . ' GB';
    } elseif ($uploadsSize >= 1048576) {
        $sizeStr = round($uploadsSize / 1048576, 1) . ' MB';
    } else {
        $sizeStr = round($uploadsSize / 1024, 1) . ' KB';
    }

    json_response([
        'ok' => true,
        'meta' => $import['meta'] ?? [],
        'tables' => $tables,
        'has_uploads' => $hasUploads,
        'uploads_count' => $uploadsCount,
        'uploads_size' => $sizeStr,
    ]);
}

// ========== 导入 ==========
if ($action === 'import') {
    $password = $_POST['password'] ?? '';
    $selectedTables = json_decode($_POST['tables'] ?? '[]', true) ?: [];
    $includeUploads = ($_POST['include_uploads'] ?? '0') === '1';

    if (empty($selectedTables) && !$includeUploads) json_response(['error' => '请选择要导入的数据'], 400);
    if (!isset($_FILES['file']['tmp_name'])) json_response(['error' => '未收到文件'], 400);

    $raw = file_get_contents($_FILES['file']['tmp_name']);
    $result = decryptAndParse($raw, $password);
    if ($result === null) return;

    $import = $result['data'];
    $isZip = $result['is_zip'];
    $zipPath = $result['zip_path'] ?? null;

    // 按依赖顺序清除
    $clearOrder = [
        'app_attachments', 'app_platforms', 'app_images', 'app_downloads',
        'image_library', 'image_categories',
        'feature_cards', 'feature_categories',
        'friend_links', 'custom_code', 'site_settings',
        'apps', 'admin_users'
    ];
    $insertOrder = [
        'admin_users', 'apps', 'site_settings', 'custom_code', 'friend_links',
        'feature_categories', 'feature_cards',
        'image_categories', 'image_library',
        'app_downloads', 'app_images', 'app_platforms', 'app_attachments'
    ];

    $pdo->beginTransaction();
    try {
        foreach ($clearOrder as $table) {
            if (in_array($table, $selectedTables, true)) {
                $pdo->exec("DELETE FROM \"$table\"");
            }
        }

        $imported = 0;
        foreach ($insertOrder as $table) {
            if (!in_array($table, $selectedTables, true)) continue;
            $rows = $import[$table] ?? [];
            if (empty($rows)) continue;

            // 获取表的合法列名
            $tableInfo = $pdo->query("PRAGMA table_info(\"$table\")")->fetchAll();
            $validCols = array_column($tableInfo, 'name');

            foreach ($rows as $row) {
                // 只保留合法列名，防止注入
                $row = array_intersect_key($row, array_flip($validCols));
                if (empty($row)) continue;
                $cols = array_keys($row);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $colList = implode(',', array_map(function($c) { return "\"$c\""; }, $cols));
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO \"$table\" ($colList) VALUES ($placeholders)");
                $stmt->execute(array_values($row));
                $imported++;
            }
        }

        $pdo->commit();
        clear_config_cache();
    } catch (\Exception $e) {
        $pdo->rollBack();
        if ($zipPath && file_exists($zipPath)) unlink($zipPath);
        json_response(['error' => '导入失败: ' . $e->getMessage()], 500);
    }

    // 恢复 uploads 文件
    $filesRestored = 0;
    if ($includeUploads && $isZip && $zipPath) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $uploadsBase = str_replace('\\', '/', realpath(__DIR__ . '/../../')) . '/';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (substr($name, 0, strlen('uploads/')) !== 'uploads/' || substr($name, -1) === '/') continue;
                // 防止路径遍历
                if (strpos($name, '..') !== false) continue;
                $destPath = $uploadsBase . $name;
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    file_put_contents($destPath, $content);
                    $filesRestored++;
                }
            }
            $zip->close();
        }
    }

    if ($zipPath && file_exists($zipPath)) unlink($zipPath);

    $tableCount = count(array_filter($selectedTables, function($t) use ($import) { return !empty($import[$t]); }));
    $msg = "导入成功，共恢复 {$tableCount} 类数据（{$imported} 条记录）";
    if ($filesRestored > 0) $msg .= "，{$filesRestored} 个上传文件";

    json_response(['ok' => true, 'message' => $msg]);
}

json_response(['error' => '无效操作'], 400);

// ===== 辅助函数 =====

/**
 * 解密备份数据，兼容新版（ZIP）和旧版（纯JSON）格式
 * 返回 ['data' => array, 'is_zip' => bool, 'zip_path' => ?string]
 */
function decryptAndParse(string $raw, string $password): ?array {
    $decrypted = null;

    // 如果是纯ZIP（无加密），直接使用
    if (substr($raw, 0, 2) === 'PK') {
        $decrypted = $raw;
    }

    // 尝试纯JSON（旧版无加密）
    if ($decrypted === null && $raw[0] === '{') {
        $testJson = json_decode($raw, true);
        if ($testJson && isset($testJson['meta'])) {
            return ['data' => $testJson, 'is_zip' => false, 'zip_path' => null];
        }
    }

    // 有密码时尝试解密
    if ($decrypted === null && $password !== '') {
        $decrypted = tryDecrypt($raw, $password);

        // 尝试 base64（旧版兼容）
        if ($decrypted === null) {
            $decoded = base64_decode($raw, true);
            if ($decoded !== false && strlen($decoded) >= 28) {
                $decrypted = tryDecrypt($decoded, $password);
            }
        }
    }

    if ($decrypted === null) {
        json_response(['error' => '解密失败：密码错误或数据损坏'], 400);
        return null;
    }

    // 判断是 ZIP 还是纯 JSON
    if (substr($decrypted, 0, 2) === 'PK') {
        // ZIP 格式（v2.0）
        $tmpFile = tempnam(sys_get_temp_dir(), 'appdown_import_');
        file_put_contents($tmpFile, $decrypted);

        if (!class_exists('ZipArchive')) {
            unlink($tmpFile);
            json_response(['error' => '服务器未安装PHP zip扩展'], 500);
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            unlink($tmpFile);
            json_response(['error' => '备份文件损坏，无法打开ZIP'], 400);
            return null;
        }

        $jsonStr = $zip->getFromName('data.json');
        $zip->close();

        if ($jsonStr === false) {
            unlink($tmpFile);
            json_response(['error' => '备份文件中缺少data.json'], 400);
            return null;
        }

        $data = json_decode($jsonStr, true);
        if (!$data || !isset($data['meta'])) {
            unlink($tmpFile);
            json_response(['error' => '备份数据格式无效'], 400);
            return null;
        }

        return ['data' => $data, 'is_zip' => true, 'zip_path' => $tmpFile];
    } else {
        // 纯 JSON 格式（v1.x 旧版）
        $data = json_decode($decrypted, true);
        if (!$data || !isset($data['meta'])) {
            json_response(['error' => '数据格式无效，不是有效的AppDown备份'], 400);
            return null;
        }
        return ['data' => $data, 'is_zip' => false, 'zip_path' => null];
    }
}

function tryDecrypt(string $raw, string $password): ?string {
    if (strlen($raw) < 28) return null;
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $key = hash('sha256', $password, true);
    $result = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $result === false ? null : $result;
}

function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filePath = $file->getRealPath();
            $relativePath = $prefix . '/' . substr(str_replace('\\', '/', $filePath), strlen(str_replace('\\', '/', $dir)) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
}
