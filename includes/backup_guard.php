<?php
/**
 * 备份导出的后端安全兜底。
 * UI 已隐藏无密码签名材料导出，但这里进一步阻止手工构造 API 请求；
 * 加密完整备份在开始打包前先验证现有签名材料都能被当前主密钥解密，避免静默产生缺失备份。
 */

function enforce_backup_export_security(): void {
    if (PHP_SAPI === 'cli' || get_request_method() !== 'POST') return;
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    if (!str_ends_with($script, '/admin/api/backup.php')) return;
    if (!empty($_FILES['file'])) return; // 导入/预览是 multipart，不属于导出。

    $data = get_json_input();
    if (($data['action'] ?? '') !== 'export') return;
    $tables = is_array($data['tables'] ?? null) ? $data['tables'] : [];
    $password = (string)($data['password'] ?? '');
    $sensitive = ['keystores', 'mc_certificates'];

    if ($password === '' && array_intersect($tables, $sensitive)) {
        json_response(['error' => '无密码 ZIP 禁止导出签名密钥或私钥，请设置至少8位备份密码'], 400);
    }

    $needsSigningMaterials = $password !== '' && (
        in_array('apps', $tables, true) ||
        (bool)array_intersect($tables, $sensitive)
    );
    if (!$needsSigningMaterials) return;

    try {
        $pdo = get_db();
        foreach ($pdo->query('SELECT store_password, key_password FROM keystores')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            decrypt_secret((string)($row['store_password'] ?? ''));
            decrypt_secret((string)($row['key_password'] ?? ''));
        }
        foreach ($pdo->query('SELECT "key" FROM mc_certificates')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            decrypt_secret((string)($row['key'] ?? ''));
        }
    } catch (Throwable $e) {
        error_log('[AppDown] backup signing-material preflight failed: ' . $e->getMessage());
        json_response(['error' => '签名材料无法使用当前主密钥解密，请先检查 data/.secret.key 或 APPDOWN_MASTER_KEY，再创建完整备份'], 500);
    }
}
