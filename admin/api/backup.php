<?php
/**
 * 数据导入导出API — 带密码加密
 * 使用 AES-256-GCM 加密
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

// ========== 导出 ==========
if ($method === 'POST') {
    csrf_validate();
    $data = get_json_input();
    $action = $data['action'] ?? '';

    if ($action === 'export') {
        $password = $data['password'] ?? '';
        if (strlen($password) < 4) {
            json_response(['error' => '加密密码至少4位'], 400);
        }

        // 收集所有数据
        $export = [
            'meta' => [
                'version' => '1.0',
                'exported_at' => date('Y-m-d H:i:s'),
                'app_name' => 'AppDown',
            ],
            'admin_users' => [],
            'apps' => [],
            'app_downloads' => [],
            'app_images' => [],
            'site_settings' => [],
            'feature_cards' => [],
            'friend_links' => [],
            'custom_code' => [],
            'app_platforms' => [],
            'app_attachments' => [],
        ];

        $tables = [
            'admin_users', 'apps', 'app_downloads', 'app_images',
            'site_settings', 'feature_cards', 'friend_links', 'custom_code',
            'app_platforms', 'app_attachments'
        ];

        foreach ($tables as $table) {
            try {
                $export[$table] = $pdo->query("SELECT * FROM $table")->fetchAll();
            } catch (\Exception $e) {
                $export[$table] = [];
            }
        }

        $json = json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // AES-256-GCM 加密
        $key = hash('sha256', $password, true); // 32 bytes
        $iv = random_bytes(12); // GCM推荐12字节IV
        $tag = '';
        $encrypted = openssl_encrypt($json, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            json_response(['error' => '加密失败'], 500);
        }

        // 格式: base64( IV(12) + TAG(16) + CIPHERTEXT )
        $packed = base64_encode($iv . $tag . $encrypted);

        json_response([
            'ok' => true,
            'data' => $packed,
            'filename' => 'appdown_backup_' . date('Ymd_His') . '.enc'
        ]);
    }

    // ========== 导入 ==========
    if ($action === 'import') {
        $password = $data['password'] ?? '';
        $encData = $data['data'] ?? '';

        if (!$password || !$encData) {
            json_response(['error' => '请提供加密数据和密码'], 400);
        }

        // 解密
        $raw = base64_decode($encData);
        if ($raw === false || strlen($raw) < 28) {
            json_response(['error' => '数据格式无效'], 400);
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $key = hash('sha256', $password, true);
        $json = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($json === false) {
            json_response(['error' => '解密失败：密码错误或数据损坏'], 400);
        }

        $import = json_decode($json, true);
        if (!$import || !isset($import['meta'])) {
            json_response(['error' => '数据格式无效，不是有效的AppDown备份'], 400);
        }

        // 开始导入 — 清除旧数据并插入新数据
        $pdo->beginTransaction();
        try {
            // 清空表（按依赖顺序）
            $clearOrder = [
                'app_attachments', 'app_platforms', 'app_images',
                'app_downloads', 'apps', 'site_settings', 'feature_cards',
                'friend_links', 'custom_code'
            ];
            foreach ($clearOrder as $table) {
                $pdo->exec("DELETE FROM $table");
            }

            // 导入数据（不导入admin_users，保留当前管理员账户）
            $importTables = [
                'apps', 'app_downloads', 'app_images', 'site_settings',
                'feature_cards', 'friend_links', 'custom_code',
                'app_platforms', 'app_attachments'
            ];

            $imported = 0;
            foreach ($importTables as $table) {
                $rows = $import[$table] ?? [];
                if (empty($rows)) continue;

                foreach ($rows as $row) {
                    $cols = array_keys($row);
                    $placeholders = implode(',', array_fill(0, count($cols), '?'));
                    $colList = implode(',', array_map(fn($c) => "\"$c\"", $cols));
                    $stmt = $pdo->prepare("INSERT OR REPLACE INTO $table ($colList) VALUES ($placeholders)");
                    $stmt->execute(array_values($row));
                    $imported++;
                }
            }

            $pdo->commit();
            clear_config_cache();

            // 同时更新admin_users如果用户选择了
            if (!empty($data['include_accounts']) && !empty($import['admin_users'])) {
                foreach ($import['admin_users'] as $u) {
                    $cols = array_keys($u);
                    $placeholders = implode(',', array_fill(0, count($cols), '?'));
                    $colList = implode(',', array_map(fn($c) => "\"$c\"", $cols));
                    $pdo->prepare("INSERT OR REPLACE INTO admin_users ($colList) VALUES ($placeholders)")
                        ->execute(array_values($u));
                }
            }

            json_response(['ok' => true, 'message' => "导入成功，共恢复 {$imported} 条记录"]);

        } catch (\Exception $e) {
            $pdo->rollBack();
            json_response(['error' => '导入失败: ' . $e->getMessage()], 500);
        }
    }

    json_response(['error' => '无效操作'], 400);
}

json_response(['error' => 'method not allowed'], 405);
