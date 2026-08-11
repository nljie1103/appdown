<?php
/**
 * AppDown SaaS / 多租户控制层。
 *
 * 中央 data/saas.db 只保存超级管理员与租户目录；
 * 每个租户继续使用原 AppDown Schema，位于 data/tenants/<slug>/app.db。
 */

function saas_root_dir(): string {
    return dirname(__DIR__);
}

function saas_control_db_path(): string {
    return saas_root_dir() . '/data/saas.db';
}

function get_saas_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dataDir = saas_root_dir() . '/data';
    if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true) && !is_dir($dataDir)) {
        throw new RuntimeException('无法创建 data 目录');
    }

    $pdo = new PDO('sqlite:' . saas_control_db_path());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS super_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            last_login TEXT
        );
        CREATE TABLE IF NOT EXISTS tenants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            display_name TEXT NOT NULL,
            password TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            last_login TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_tenants_status ON tenants(status);
    ");
    return $pdo;
}

function tenant_reserved_slugs(): array {
    return [
        'admin','super','api','install','static','uploads','data','tools','tests',
        'ios','android','ios-template','android-template','assets','images','fonts',
        'privacy','terms','index','favicon','robots','sitemap','health','status'
    ];
}

function normalize_tenant_slug(string $slug): string {
    return strtolower(trim($slug));
}

function validate_tenant_slug(string $slug): array {
    $slug = normalize_tenant_slug($slug);
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,31}$/', $slug)) {
        return ['ok' => false, 'error' => '用户名需为 3-32 位小写字母、数字、下划线或短横线，且必须以字母或数字开头'];
    }
    if (in_array($slug, tenant_reserved_slugs(), true)) {
        return ['ok' => false, 'error' => '该用户名属于系统保留路径'];
    }
    return ['ok' => true, 'slug' => $slug];
}

function find_tenant(string $slug, bool $includeDisabled = false): ?array {
    $check = validate_tenant_slug($slug);
    if (!$check['ok']) return null;
    $pdo = get_saas_db();
    $sql = 'SELECT id, slug, display_name, password, status, created_at, updated_at, last_login FROM tenants WHERE slug = ?';
    if (!$includeDisabled) $sql .= " AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$check['slug']]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function set_current_tenant(?array $tenant): void {
    $GLOBALS['APPDOWN_CURRENT_TENANT'] = $tenant;
}

function current_tenant(bool $includeDisabled = false): ?array {
    if (array_key_exists('APPDOWN_CURRENT_TENANT', $GLOBALS)) {
        $tenant = $GLOBALS['APPDOWN_CURRENT_TENANT'];
        if (!$tenant) return null;
        if (!$includeDisabled && ($tenant['status'] ?? '') !== 'active') return null;
        return $tenant;
    }

    // 已登录后台永远以 Session 租户为准，禁止 query 参数切换租户。
    $slug = '';
    if (!empty($_SESSION['tenant_slug'])) {
        $slug = (string)$_SESSION['tenant_slug'];
    } elseif (($env = getenv('APPDOWN_TENANT')) !== false && trim((string)$env) !== '') {
        $slug = (string)$env;
    } elseif (!empty($_GET['tenant'])) {
        $slug = (string)$_GET['tenant'];
    }

    if ($slug === '') return null;
    $tenant = find_tenant($slug, $includeDisabled);
    set_current_tenant($tenant);
    return $tenant;
}

function require_tenant_context(bool $includeDisabled = false): array {
    $tenant = current_tenant($includeDisabled);
    if (!$tenant) {
        if (!headers_sent()) http_response_code(404);
        if (function_exists('json_response') && (
            str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
            !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        )) {
            json_response(['error' => 'tenant_not_found'], 404);
        }
        echo '租户不存在或已停用';
        exit;
    }
    return $tenant;
}

function tenant_data_dir(?string $slug = null): string {
    if ($slug === null) {
        $tenant = require_tenant_context(true);
        $slug = $tenant['slug'];
    }
    return saas_root_dir() . '/data/tenants/' . normalize_tenant_slug($slug);
}

function tenant_db_path(?string $slug = null): string {
    return tenant_data_dir($slug) . '/app.db';
}

function tenant_secret_key_path(?string $slug = null): string {
    return tenant_data_dir($slug) . '/.secret.key';
}

function tenant_config_cache_path(?string $slug = null): string {
    return tenant_data_dir($slug) . '/config_cache.json';
}

function tenant_upload_dir(?string $slug = null): string {
    if ($slug === null) {
        $tenant = require_tenant_context(true);
        $slug = $tenant['slug'];
    }
    return saas_root_dir() . '/uploads/tenants/' . normalize_tenant_slug($slug);
}

function tenant_upload_url_prefix(?string $slug = null): string {
    if ($slug === null) {
        $tenant = require_tenant_context(true);
        $slug = $tenant['slug'];
    }
    return 'uploads/tenants/' . normalize_tenant_slug($slug);
}

function appdown_db_path(): string {
    return tenant_db_path();
}

function appdown_data_dir(): string {
    return tenant_data_dir();
}

function appdown_config_cache_path(): string {
    return tenant_config_cache_path();
}

function appdown_upload_dir(): string {
    return tenant_upload_dir();
}

function appdown_upload_url_prefix(): string {
    return tenant_upload_url_prefix();
}

function tenant_public_path(?string $slug = null): string {
    if ($slug === null) {
        $tenant = require_tenant_context(true);
        $slug = $tenant['slug'];
    }
    return '/' . rawurlencode(normalize_tenant_slug($slug)) . '/';
}

function tenant_public_url(?string $slug = null): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . tenant_public_path($slug);
}

function tenant_absolute_asset_url(string $value, ?string $slug = null): string {
    $value = trim($value);
    if ($value === '' || $value === '#' || preg_match('#^(?:https?:|itms-services:|data:)#i', $value)) return $value;
    if (str_starts_with($value, '//')) return $value;

    // 旧的租户本地数据可能仍保存 uploads/<category>/...，输出时映射到租户目录。
    $plain = ltrim($value, '/');
    if (str_starts_with($plain, 'uploads/') && !str_starts_with($plain, 'uploads/tenants/')) {
        if ($slug === null) $slug = require_tenant_context(true)['slug'];
        $plain = 'uploads/tenants/' . normalize_tenant_slug($slug) . '/' . substr($plain, strlen('uploads/'));
    }
    return '/' . $plain;
}

function tenant_route_api_url(string $value, ?string $slug = null): string {
    $value = trim($value);
    if ($value === '' || $value === '#') return $value;
    if ($slug === null) $slug = require_tenant_context(true)['slug'];
    if (preg_match('#^/api/(config|track|plist|mobileconfig)\.php(.*)$#', $value, $m)) {
        return '/' . rawurlencode($slug) . '/api/' . $m[1] . '.php' . $m[2];
    }
    return tenant_absolute_asset_url($value, $slug);
}

function ensure_tenant_directories(string $slug): void {
    $dataDir = tenant_data_dir($slug);
    $uploadDir = tenant_upload_dir($slug);
    foreach ([$dataDir, $uploadDir] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('无法创建租户目录: ' . $dir);
        }
    }
    foreach (['images','fonts','apps','certs','keystores'] as $sub) {
        $dir = $uploadDir . '/' . $sub;
        if (!is_dir($dir)) mkdir($dir, in_array($sub, ['certs','keystores'], true) ? 0700 : 0755, true);
    }
}

function seed_tenant_defaults(PDO $pdo, string $slug, string $displayName): void {
    $settings = [
        'site_title' => $displayName,
        'site_heading' => $displayName,
        'logo_url' => '',
        'favicon_url' => '',
        'notice_text' => '',
        'notice_enabled' => '0',
        'copyright' => '© ' . date('Y') . ' ' . $displayName . '. All rights reserved.',
        'carousel_interval' => '4000',
        'stats_downloads' => '0',
        'stats_rating' => '0',
        'stats_daily_active' => '0',
        'font_url' => '',
        'font_family' => 'system-ui',
        'landing_template' => 'classic',
        'tenant_slug' => $slug,
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO site_settings (setting_key, setting_val) VALUES (?, ?)');
    foreach ($settings as $key => $value) $stmt->execute([$key, $value]);
}

function create_tenant(string $slug, string $displayName, string $password): array {
    $valid = validate_tenant_slug($slug);
    if (!$valid['ok']) return $valid;
    $slug = $valid['slug'];
    $displayName = trim($displayName);
    if ($displayName === '') $displayName = $slug;
    if (mb_strlen($displayName) > 80) return ['ok' => false, 'error' => '站点名称不能超过 80 个字符'];
    if (strlen($password) < 8) return ['ok' => false, 'error' => '密码至少 8 位'];

    $control = get_saas_db();
    $stmt = $control->prepare('SELECT id FROM tenants WHERE slug = ?');
    $stmt->execute([$slug]);
    if ($stmt->fetch()) return ['ok' => false, 'error' => '用户名已存在'];

    ensure_tenant_directories($slug);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $control->beginTransaction();
    try {
        $stmt = $control->prepare("INSERT INTO tenants (slug, display_name, password, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$slug, $displayName, $hash]);

        $tenant = find_tenant($slug, true);
        set_current_tenant($tenant);
        $pdo = get_db();
        seed_tenant_defaults($pdo, $slug, $displayName);
        set_current_tenant(null);

        $control->commit();
    } catch (Throwable $e) {
        if ($control->inTransaction()) $control->rollBack();
        set_current_tenant(null);
        throw $e;
    }
    return ['ok' => true, 'slug' => $slug];
}

function update_tenant_record(string $slug, array $changes): array {
    $tenant = find_tenant($slug, true);
    if (!$tenant) return ['ok' => false, 'error' => '租户不存在'];
    $control = get_saas_db();

    if (array_key_exists('display_name', $changes)) {
        $name = trim((string)$changes['display_name']);
        if ($name === '' || mb_strlen($name) > 80) return ['ok' => false, 'error' => '站点名称需为 1-80 个字符'];
        $control->prepare("UPDATE tenants SET display_name = ?, updated_at = datetime('now') WHERE slug = ?")->execute([$name, $tenant['slug']]);
        set_current_tenant($tenant);
        $pdo = get_db();
        if (function_exists('set_setting')) {
            set_setting($pdo, 'site_title', $name);
            set_setting($pdo, 'site_heading', $name);
            if (function_exists('clear_config_cache')) clear_config_cache();
        }
        set_current_tenant(null);
    }
    if (array_key_exists('status', $changes)) {
        $status = (string)$changes['status'];
        if (!in_array($status, ['active','disabled'], true)) return ['ok' => false, 'error' => '无效状态'];
        $control->prepare("UPDATE tenants SET status = ?, updated_at = datetime('now') WHERE slug = ?")->execute([$status, $tenant['slug']]);
    }
    if (!empty($changes['password'])) {
        $password = (string)$changes['password'];
        if (strlen($password) < 8) return ['ok' => false, 'error' => '密码至少 8 位'];
        $control->prepare("UPDATE tenants SET password = ?, updated_at = datetime('now') WHERE slug = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $tenant['slug']]);
    }
    return ['ok' => true];
}

function remove_tree(string $path): void {
    if (!is_dir($path)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        if ($file->isDir() && !$file->isLink()) @rmdir($file->getPathname());
        else @unlink($file->getPathname());
    }
    @rmdir($path);
}

function delete_tenant_permanently(string $slug): array {
    $tenant = find_tenant($slug, true);
    if (!$tenant) return ['ok' => false, 'error' => '租户不存在'];
    $control = get_saas_db();
    $control->prepare('DELETE FROM tenants WHERE slug = ?')->execute([$tenant['slug']]);
    remove_tree(tenant_data_dir($tenant['slug']));
    remove_tree(tenant_upload_dir($tenant['slug']));
    return ['ok' => true];
}

function list_tenants(): array {
    return get_saas_db()->query(
        "SELECT id, slug, display_name, status, created_at, updated_at, last_login FROM tenants ORDER BY id DESC"
    )->fetchAll();
}
