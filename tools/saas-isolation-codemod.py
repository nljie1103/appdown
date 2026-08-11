from pathlib import Path


def replace(path, old, new, expected=1):
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != expected:
        raise SystemExit(f"{path}: expected {expected} matches, got {count}: {old[:100]!r}")
    p.write_text(text.replace(old, new))
    print(f"patched {path}: {count}")


# Worker launch: immutable tenant context and tenant-local logs.
replace('admin/api/generate.php', "$pdo = get_db();\n$method = get_request_method();", "$tenant = require_tenant_context();\n$tenantSlug = $tenant['slug'];\n$pdo = get_db();\n$method = get_request_method();")
replace('admin/api/generate.php', "        $dataDir = realpath(__DIR__ . '/../../data') ?: (__DIR__ . '/../../data');\n        $debugLog = $dataDir . '/build_worker_' . $taskId . '.log';\n        $cmd = sprintf('nohup %s %s %d > %s 2>&1 & echo $!', escapeshellarg($phpBin), escapeshellarg($workerScript), $taskId, escapeshellarg($debugLog));", "        $dataDir = appdown_data_dir();\n        if (!is_dir($dataDir)) @mkdir($dataDir, 0750, true);\n        $debugLog = $dataDir . '/build_worker_' . $taskId . '.log';\n        $cmd = sprintf('nohup env APPDOWN_TENANT=%s %s %s %d > %s 2>&1 & echo $!', escapeshellarg($tenantSlug), escapeshellarg($phpBin), escapeshellarg($workerScript), $taskId, escapeshellarg($debugLog));")
replace('admin/api/generate.php', "        $dataDir = realpath(__DIR__ . '/../../data') ?: (__DIR__ . '/../../data');\n        $debugLog = $dataDir . '/ipa_build_worker_' . $taskId . '.log';\n        $cmd = sprintf('nohup %s %s %d > %s 2>&1 & echo $!', escapeshellarg($phpBin), escapeshellarg($workerScript), $taskId, escapeshellarg($debugLog));", "        $dataDir = appdown_data_dir();\n        if (!is_dir($dataDir)) @mkdir($dataDir, 0750, true);\n        $debugLog = $dataDir . '/ipa_build_worker_' . $taskId . '.log';\n        $cmd = sprintf('nohup env APPDOWN_TENANT=%s %s %s %d > %s 2>&1 & echo $!', escapeshellarg($tenantSlug), escapeshellarg($phpBin), escapeshellarg($workerScript), $taskId, escapeshellarg($debugLog));")

# APK worker.
replace('tools/build-worker.php', "require_once __DIR__ . '/../includes/db.php';", "require_once __DIR__ . '/../includes/saas.php';\nrequire_once __DIR__ . '/../includes/db.php';")
replace('tools/build-worker.php', "$keystoreRoot = realpath(__DIR__ . '/../uploads/keystores');", "$keystoreRoot = realpath(appdown_upload_dir() . '/keystores');")
replace('tools/build-worker.php', "$apkDir = __DIR__ . '/../uploads/apks';", "$apkDir = appdown_upload_dir() . '/apks';")
replace('tools/build-worker.php', "$apkUrl = 'uploads/apks/' . $apkFilename;", "$apkUrl = appdown_upload_url_prefix() . '/apks/' . $apkFilename;")

# IPA worker.
replace('tools/ios-build-worker.php', "require_once __DIR__ . '/../includes/db.php';", "require_once __DIR__ . '/../includes/saas.php';\nrequire_once __DIR__ . '/../includes/db.php';")
replace('tools/ios-build-worker.php', "$pdo = get_db();\n$localBuildDir = '';\n$projectRoot = realpath(__DIR__ . '/..');", "$pdo = get_db();\n$tenant = require_tenant_context();\n$tenantSlug = $tenant['slug'];\n$localBuildDir = '';\n$remoteBuildDir = '';\n$projectRoot = realpath(__DIR__ . '/..');")
replace('tools/ios-build-worker.php', "$localBuildDir = $projectRoot . '/data/ios-build/task_' . $taskId;", "$localBuildDir = $projectRoot . '/data/ios-build/' . $tenantSlug . '_task_' . $taskId;")
replace('tools/ios-build-worker.php', "$remoteBuildDir = '/mnt/build/task_' . $taskId;", "$remoteBuildDir = '/mnt/build/' . $tenantSlug . '_task_' . $taskId;")
replace('tools/ios-build-worker.php', "$ipaDir = $projectRoot . '/uploads/ipas';", "$ipaDir = appdown_upload_dir() . '/ipas';")
replace('tools/ios-build-worker.php', "$ipaUrl = 'uploads/ipas/' . $ipaFilename;", "$ipaUrl = appdown_upload_url_prefix() . '/ipas/' . $ipaFilename;")
replace('tools/ios-build-worker.php', "    if ($taskId) ssh_exec('rm -rf /mnt/build/task_' . (int)$taskId);", "    if ($remoteBuildDir !== '') ssh_exec('rm -rf ' . escapeshellarg($remoteBuildDir));")

# Mobileconfig generated files + certificate modes.
replace('admin/api/mobileconfig.php', "$destDir = __DIR__ . '/../../uploads/mobileconfigs';", "$destDir = appdown_upload_dir() . '/mobileconfigs';", expected=2)
replace('admin/api/mobileconfig.php', "$mcRoot = realpath(__DIR__ . '/../../uploads/mobileconfigs');", "$mcRoot = realpath(appdown_upload_dir() . '/mobileconfigs');", expected=2)
replace('admin/api/mobileconfig.php', "if (!in_array($mode, ['text', 'path', 'upload'], true))", "if (!in_array($mode, ['text', 'upload'], true))", expected=2)

old_cert = """        case 'path':
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
            return '';"""
new_cert = """        case 'path':
        case 'upload':
            // SaaS 租户只能读取自己 uploads 目录中的证书文件。
            if (!function_exists('appdown_upload_dir') || !current_tenant(true)) return '';
            if ($mode === 'upload') {
                $clean = ltrim(str_replace('\\\\', '/', $value), '/');
                if (!str_starts_with($clean, appdown_upload_url_prefix() . '/') || strpos($clean, '..') !== false) return '';
                $realPath = realpath(__DIR__ . '/../' . $clean);
            } else {
                if (!preg_match('#^[a-zA-Z0-9_./\\\\-]+$#', $value)) return '';
                $realPath = realpath($value);
            }
            $tenantUploadRoot = realpath(appdown_upload_dir());
            if (!$realPath || !$tenantUploadRoot || !str_starts_with($realPath, $tenantUploadRoot . DIRECTORY_SEPARATOR)) return '';
            return is_file($realPath) && is_readable($realPath) ? (string)file_get_contents($realPath) : '';"""
replace('includes/mobileconfig.php', old_cert, new_cert)

# Keystore generation.
replace('admin/api/keystores.php', "$dir = __DIR__ . '/../../uploads/keystores';", "$dir = appdown_upload_dir() . '/keystores';")
replace('admin/api/keystores.php', "$fileUrl = 'uploads/keystores/' . $filename;", "$fileUrl = appdown_upload_url_prefix() . '/keystores/' . $filename;")

# Image library and attachment rename.
replace('admin/api/image-library.php', "$result['url'] = 'uploads/images/' . basename($newPath);", "$result['url'] = appdown_upload_url_prefix() . '/images/' . basename($newPath);")
replace('admin/api/image-library.php', "$newUrl = 'uploads/images/' . $safeName;", "$newUrl = appdown_upload_url_prefix() . '/images/' . $safeName;")
replace('admin/api/attachment-files.php', "$newUrl = 'uploads/apps/' . $safeName;", "$newUrl = appdown_upload_url_prefix() . '/apps/' . $safeName;")

# Package inspector.
replace('admin/api/package-info.php', "// 安全检查：只允许读取 uploads/ 下的文件\nif (substr($fileUrl, 0, strlen('uploads/')) !== 'uploads/') {\n    json_response(['error' => '非法文件路径'], 403);\n}\n\n$filePath = realpath(__DIR__ . '/../../' . $fileUrl);\n$basePath = realpath(__DIR__ . '/../../uploads');", "// 安全检查：只允许读取当前租户 uploads/tenants/<slug>/ 下的文件。\n$tenantPrefix = appdown_upload_url_prefix() . '/';\nif (!str_starts_with(ltrim($fileUrl, '/'), $tenantPrefix)) {\n    json_response(['error' => '非法文件路径'], 403);\n}\n\n$filePath = realpath(__DIR__ . '/../../' . ltrim($fileUrl, '/'));\n$basePath = realpath(appdown_upload_dir());")

# Tenant-only backup + portable path remapping.
replace('admin/api/backup.php', "$pdo = get_db();\n$isMultipart = !empty($_FILES['file']);", "$tenant = require_tenant_context();\n$tenantSlug = $tenant['slug'];\n$pdo = get_db();\n$isMultipart = !empty($_FILES['file']);")
replace('admin/api/backup.php', "        'app_name' => 'AppDown',", "        'app_name' => 'AppDown SaaS',\n        'tenant_slug' => $tenantSlug,")
replace('admin/api/backup.php', "$uploadsBase = realpath(__DIR__ . '/../../uploads');", "$uploadsBase = realpath(appdown_upload_dir());")
replace('admin/api/backup.php', "stream_download_file($tmpZip, 'appdown_backup_' . date('Ymd_His') . '.zip');", "stream_download_file($tmpZip, 'appdown_' . $tenantSlug . '_backup_' . date('Ymd_His') . '.zip');")
replace('admin/api/backup.php', "$filename = 'appdown_backup_' . date('Ymd_His') . '.enc';", "$filename = 'appdown_' . $tenantSlug . '_backup_' . date('Ymd_His') . '.enc';")
replace('admin/api/backup.php', "    $pdo->beginTransaction();\n    try {", "    $sourceTenantSlug = normalize_tenant_slug((string)($import['meta']['tenant_slug'] ?? ''));\n    $currentUploadPrefix = appdown_upload_url_prefix();\n\n    $pdo->beginTransaction();\n    try {")
replace('admin/api/backup.php', "                $row = array_intersect_key($row, array_flip($validCols));\n                if (!$row) continue;", "                $row = array_intersect_key($row, array_flip($validCols));\n                if (!$row) continue;\n                // SaaS 备份恢复到不同用户名时，所有租户本地文件路径改写为当前租户前缀。\n                foreach ($row as $field => $value) {\n                    if (!is_string($value) || $value === '') continue;\n                    if ($sourceTenantSlug !== '') {\n                        $value = str_replace(\n                            ['uploads/tenants/' . $sourceTenantSlug . '/', '/uploads/tenants/' . $sourceTenantSlug . '/'],\n                            [$currentUploadPrefix . '/', '/' . $currentUploadPrefix . '/'],\n                            $value\n                        );\n                    }\n                    if (preg_match('#^(/?)uploads/(?!tenants/)(.+)$#', $value, $m)) {\n                        $value = $m[1] . $currentUploadPrefix . '/' . $m[2];\n                    }\n                    $row[$field] = $value;\n                }")
old_restore = """    $uploadsRoot = realpath(__DIR__ . '/../../uploads');
    if (!$uploadsRoot) { @mkdir(__DIR__ . '/../../uploads', 0755, true); $uploadsRoot = realpath(__DIR__ . '/../../uploads'); }
    if (!$uploadsRoot) { $zip->close(); json_response(['error' => '无法创建 uploads 目录'], 500); }"""
new_restore = """    $tenantUploadDir = appdown_upload_dir();
    $uploadsRoot = realpath($tenantUploadDir);
    if (!$uploadsRoot) { @mkdir($tenantUploadDir, 0755, true); $uploadsRoot = realpath($tenantUploadDir); }
    if (!$uploadsRoot) { $zip->close(); json_response(['error' => '无法创建租户 uploads 目录'], 500); }"""
replace('admin/api/backup.php', old_restore, new_restore)

# Maintenance.
replace('tools/maintenance-core.php', "require_once __DIR__ . '/../includes/db.php';", "require_once __DIR__ . '/../includes/saas.php';\nrequire_once __DIR__ . '/../includes/db.php';")
replace('tools/maintenance-core.php', "$dataDir = $projectRoot . '/data';\n$uploadsRoot = realpath($projectRoot . '/uploads') ?: ($projectRoot . '/uploads');", "$tenant = require_tenant_context();\n$dataDir = appdown_data_dir();\n$uploadsRoot = realpath(appdown_upload_dir()) ?: appdown_upload_dir();")
replace('tools/maintenance-core.php', "$dbPath = $dataDir . '/app.db';", "$dbPath = appdown_db_path();")
replace('tools/maintenance-core.php', "$rel = 'uploads/' . ltrim(str_replace('\\\\', '/', substr($real, strlen($uploadsRoot))), '/');", "$rel = appdown_upload_url_prefix() . '/' . ltrim(str_replace('\\\\', '/', substr($real, strlen($uploadsRoot))), '/');")
replace('tools/maintenance.php', "$uploadsRoot = realpath($projectRoot . '/uploads');", "$uploadsRoot = realpath(appdown_upload_dir());\n    $tenantUploadPrefix = appdown_upload_url_prefix();")
replace('tools/maintenance.php', "if (str_starts_with($rel, 'uploads/keystores/') || str_starts_with($rel, 'uploads/certs/')) continue;", "if (str_starts_with($rel, $tenantUploadPrefix . '/keystores/') || str_starts_with($rel, $tenantUploadPrefix . '/certs/')) continue;")

# Remove staging helpers from resulting SaaS branch.
Path('.github/workflows/saas-isolation-codemod.yml').unlink(missing_ok=True)
Path('tools/saas-isolation-codemod.py').unlink(missing_ok=True)
print('all asserted SaaS isolation patches applied')
