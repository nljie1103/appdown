<?php
/**
 * 数据导入导出API — 带密码加密 + 选择性导入导出
 * 使用 AES-256-GCM 加密
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();
csrf_validate();
require_method('POST');

$pdo = get_db();
$data = get_json_input();
$action = $data['action'] ?? '';

// 所有可导出的表及其依赖顺序（删除时先删子表）
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

    if (strlen($password) < 4) {
        json_response(['error' => '加密密码至少4位'], 400);
    }
    if (empty($selectedTables)) {
        json_response(['error' => '请选择要导出的数据'], 400);
    }

    // 只导出用户选择的表（过滤非法表名）
    $export = [
        'meta' => [
            'version' => '1.1',
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

    $json = json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // AES-256-GCM 加密
    $key = hash('sha256', $password, true);
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($json, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($encrypted === false) {
        json_response(['error' => '加密失败'], 500);
    }

    $packed = base64_encode($iv . $tag . $encrypted);

    json_response([
        'ok' => true,
        'data' => $packed,
        'filename' => 'appdown_backup_' . date('Ymd_His') . '.enc'
    ]);
}

// ========== 解密预览（不导入，只返回备份中有哪些表及记录数） ==========
if ($action === 'decrypt_preview') {
    $password = $data['password'] ?? '';
    $encData = $data['data'] ?? '';

    if (!$password || !$encData) {
        json_response(['error' => '请提供加密数据和密码'], 400);
    }

    $import = decrypt_backup($encData, $password);
    if ($import === null) return; // decrypt_backup已输出错误

    // 返回每个表的记录数
    $tables = [];
    foreach ($import as $key => $rows) {
        if ($key === 'meta' || !is_array($rows)) continue;
        $tables[$key] = count($rows);
    }

    json_response([
        'ok' => true,
        'meta' => $import['meta'] ?? [],
        'tables' => $tables
    ]);
}

// ========== 导入 ==========
if ($action === 'import') {
    $password = $data['password'] ?? '';
    $encData = $data['data'] ?? '';
    $selectedTables = $data['tables'] ?? [];

    if (!$password || !$encData) {
        json_response(['error' => '请提供加密数据和密码'], 400);
    }
    if (empty($selectedTables)) {
        json_response(['error' => '请选择要导入的数据'], 400);
    }

    $import = decrypt_backup($encData, $password);
    if ($import === null) return;

    // 按依赖顺序清除（子表先删）
    $clearOrder = [
        'app_attachments', 'app_platforms', 'app_images', 'app_downloads',
        'image_library', 'image_categories',
        'feature_cards', 'feature_categories',
        'friend_links', 'custom_code', 'site_settings',
        'apps', 'admin_users'
    ];

    // 插入顺序（父表先插）
    $insertOrder = [
        'admin_users', 'apps', 'site_settings', 'custom_code', 'friend_links',
        'feature_categories', 'feature_cards',
        'image_categories', 'image_library',
        'app_downloads', 'app_images', 'app_platforms', 'app_attachments'
    ];

    $pdo->beginTransaction();
    try {
        // 只清除用户选择的表
        foreach ($clearOrder as $table) {
            if (in_array($table, $selectedTables, true)) {
                $pdo->exec("DELETE FROM \"$table\"");
            }
        }

        // 只导入用户选择的表
        $imported = 0;
        foreach ($insertOrder as $table) {
            if (!in_array($table, $selectedTables, true)) continue;
            $rows = $import[$table] ?? [];
            if (empty($rows)) continue;

            foreach ($rows as $row) {
                $cols = array_keys($row);
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $colList = implode(',', array_map(fn($c) => "\"$c\"", $cols));
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO \"$table\" ($colList) VALUES ($placeholders)");
                $stmt->execute(array_values($row));
                $imported++;
            }
        }

        $pdo->commit();
        clear_config_cache();

        $tableCount = count(array_filter($selectedTables, fn($t) => !empty($import[$t])));
        json_response(['ok' => true, 'message' => "导入成功，共恢复 {$tableCount} 类数据 ({$imported} 条记录)"]);

    } catch (\Exception $e) {
        $pdo->rollBack();
        json_response(['error' => '导入失败: ' . $e->getMessage()], 500);
    }
}

json_response(['error' => '无效操作'], 400);

// ===== 解密辅助函数 =====
function decrypt_backup(string $encData, string $password): ?array {
    $raw = base64_decode($encData);
    if ($raw === false || strlen($raw) < 28) {
        json_response(['error' => '数据格式无效'], 400);
        return null;
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);

    $key = hash('sha256', $password, true);
    $json = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($json === false) {
        json_response(['error' => '解密失败：密码错误或数据损坏'], 400);
        return null;
    }

    $import = json_decode($json, true);
    if (!$import || !isset($import['meta'])) {
        json_response(['error' => '数据格式无效，不是有效的AppDown备份'], 400);
        return null;
    }

    return $import;
}
