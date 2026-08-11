from pathlib import Path


def replace(path, old, new, expected=1):
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != expected:
        raise SystemExit(f"{path}: expected {expected} matches, got {count}: {old[:100]!r}")
    p.write_text(text.replace(old, new))
    print(f"patched {path}: {count}")


old_safe = """function safe_project_file(string $root, string $relative): string {
    if ($relative === '' || str_contains($relative, '..')) return '';
    $path = realpath($root . '/' . ltrim($relative, '/'));
    return ($path && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) ? $path : '';
}"""
new_safe = """function safe_project_file(string $root, string $relative): string {
    if ($relative === '' || str_contains($relative, '..')) return '';
    $tenantRoot = realpath(appdown_upload_dir());
    if (!$tenantRoot) return '';
    $normalized = ltrim($relative, '/');
    $prefix = appdown_upload_url_prefix() . '/';
    if (!str_starts_with($normalized, $prefix)) return '';
    $path = realpath($root . '/' . $normalized);
    return ($path && str_starts_with($path, $tenantRoot . DIRECTORY_SEPARATOR) && is_file($path)) ? $path : '';
}"""
replace('tools/build-worker.php', old_safe, new_safe)
replace('tools/ios-build-worker.php', old_safe, new_safe)

replace(
    'admin/api/backup.php',
    "    'app_platforms', 'app_attachments', 'image_categories', 'image_library', 'admin_users',\n    'keystores', 'mc_certificates', 'generated_mobileconfigs', 'generated_apks', 'generated_ipas'",
    "    'app_platforms', 'app_attachments', 'image_categories', 'image_library',\n    'keystores', 'mc_certificates', 'generated_mobileconfigs', 'generated_apks', 'generated_ipas'",
)
replace(
    'admin/api/backup.php',
    "        'image_library', 'image_categories', 'feature_cards', 'feature_categories',\n        'friend_links', 'custom_code', 'site_settings', 'apps', 'admin_users'",
    "        'image_library', 'image_categories', 'feature_cards', 'feature_categories',\n        'friend_links', 'custom_code', 'site_settings', 'apps'",
)
replace(
    'admin/api/backup.php',
    "        'admin_users', 'apps', 'site_settings', 'custom_code', 'friend_links',",
    "        'apps', 'site_settings', 'custom_code', 'friend_links',",
)

replace(
    'api/track.php',
    "require_once __DIR__ . '/../includes/init.php';\nrequire_method('POST');",
    "require_once __DIR__ . '/../includes/init.php';\nrequire_tenant_context();\nrequire_method('POST');",
)

for policy in ['privacy.php', 'terms.php']:
    replace(policy, "$pdo = get_db();", "$tenant = require_tenant_context();\n$tenantHome = tenant_public_path($tenant['slug']);\n$pdo = get_db();")
    replace(policy, '<link rel="stylesheet" href="style.css">', '<link rel="stylesheet" href="/style.css">')
    replace(policy, '<a href="/" class="back-button">← 返回首页</a>', '<a href="<?= htmlspecialchars($tenantHome) ?>" class="back-button">← 返回首页</a>')

replace(
    'admin/templates.php',
    "require_auth();\n\nadmin_header('页面模板', 'templates');",
    "require_auth();\n$tenant = require_tenant_context();\n$tenantPublicPath = tenant_public_path($tenant['slug']);\n\nadmin_header('页面模板', 'templates');",
)
replace(
    'admin/templates.php',
    '<a class="btn btn-outline" href="/" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> 打开前台预览</a>',
    '<a class="btn btn-outline" href="<?= htmlspecialchars($tenantPublicPath) ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> 打开前台预览</a>',
)

replace(
    'includes/backup_guard.php',
    'data/.secret.key',
    '当前租户的 .secret.key',
    expected=1,
)

Path('tools/saas-postfix.py').unlink(missing_ok=True)
Path('tools/saas-business-postfix.py').unlink(missing_ok=True)
print('SaaS business postfix applied')
