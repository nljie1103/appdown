<?php
/**
 * 数据库连接单例 + Schema初始化
 */

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $db_dir = __DIR__ . '/../data';
    $db_path = $db_dir . '/app.db';

    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0750, true);
    }

    $is_new = !file_exists($db_path);

    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('PRAGMA busy_timeout=5000');

    if ($is_new) {
        init_schema($pdo);
    } else {
        migrate_schema($pdo);
    }

    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            username    TEXT NOT NULL UNIQUE,
            password    TEXT NOT NULL,
            created_at  TEXT NOT NULL DEFAULT (datetime('now')),
            last_login  TEXT
        );

        CREATE TABLE IF NOT EXISTS apps (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            slug            TEXT NOT NULL UNIQUE,
            name            TEXT NOT NULL,
            icon            TEXT NOT NULL DEFAULT 'fas fa-tv',
            icon_url        TEXT NOT NULL DEFAULT '',
            theme_color     TEXT NOT NULL DEFAULT '#007AFF',
            ios_plist_url   TEXT NOT NULL DEFAULT '',
            ios_ipa_url     TEXT NOT NULL DEFAULT '',
            ios_bundle_id   TEXT NOT NULL DEFAULT '',
            ios_cert_name   TEXT NOT NULL DEFAULT '',
            ios_description TEXT NOT NULL DEFAULT '',
            ios_version     TEXT NOT NULL DEFAULT '',
            ios_size        TEXT NOT NULL DEFAULT '',
            ios_template    TEXT NOT NULL DEFAULT 'modern',
            feature_category_id INTEGER NOT NULL DEFAULT 0,
            sort_order      INTEGER NOT NULL DEFAULT 0,
            is_active       INTEGER NOT NULL DEFAULT 1,
            created_at      TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS app_downloads (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            app_id      INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
            btn_type    TEXT NOT NULL DEFAULT 'android',
            btn_icon    TEXT NOT NULL DEFAULT '',
            btn_text    TEXT NOT NULL,
            btn_subtext TEXT NOT NULL DEFAULT '',
            href        TEXT NOT NULL DEFAULT '#',
            sort_order  INTEGER NOT NULL DEFAULT 0,
            is_active   INTEGER NOT NULL DEFAULT 1,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS app_images (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            app_id      INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
            image_url   TEXT NOT NULL,
            alt_text    TEXT NOT NULL DEFAULT '',
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS site_settings (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT NOT NULL UNIQUE,
            setting_val TEXT NOT NULL DEFAULT '',
            updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS feature_cards (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT NOT NULL,
            description TEXT NOT NULL,
            icon        TEXT NOT NULL DEFAULT '',
            icon_url    TEXT NOT NULL DEFAULT '',
            category_id INTEGER NOT NULL DEFAULT 0,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            is_active   INTEGER NOT NULL DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS friend_links (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            url         TEXT NOT NULL DEFAULT '#',
            icon        TEXT NOT NULL DEFAULT '',
            icon_url    TEXT NOT NULL DEFAULT '',
            show_icon   INTEGER NOT NULL DEFAULT 0,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            is_active   INTEGER NOT NULL DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS custom_code (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            position    TEXT NOT NULL UNIQUE,
            code        TEXT NOT NULL DEFAULT '',
            updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS page_visits (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            ip          TEXT NOT NULL,
            user_agent  TEXT NOT NULL DEFAULT '',
            referer     TEXT NOT NULL DEFAULT '',
            page_url    TEXT NOT NULL DEFAULT '/',
            visit_date  TEXT NOT NULL,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS download_clicks (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            app_slug    TEXT NOT NULL,
            btn_type    TEXT NOT NULL,
            href        TEXT NOT NULL DEFAULT '',
            ip          TEXT NOT NULL,
            user_agent  TEXT NOT NULL DEFAULT '',
            click_date  TEXT NOT NULL,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE INDEX IF NOT EXISTS idx_visits_date ON page_visits(visit_date);
        CREATE INDEX IF NOT EXISTS idx_visits_referer ON page_visits(referer);
        CREATE INDEX IF NOT EXISTS idx_clicks_date ON download_clicks(click_date);
        CREATE INDEX IF NOT EXISTS idx_clicks_app ON download_clicks(app_slug);

        CREATE TABLE IF NOT EXISTS app_platforms (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            app_id      INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
            name        TEXT NOT NULL,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS app_attachments (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            app_id        INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
            platform_id   INTEGER NOT NULL REFERENCES app_platforms(id) ON DELETE CASCADE,
            version       TEXT NOT NULL,
            file_url      TEXT NOT NULL,
            file_size     TEXT NOT NULL DEFAULT '',
            changelog     TEXT NOT NULL DEFAULT '',
            sort_order    INTEGER NOT NULL DEFAULT 0,
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS image_categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS image_library (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL REFERENCES image_categories(id) ON DELETE CASCADE,
            file_url    TEXT NOT NULL,
            filename    TEXT NOT NULL DEFAULT '',
            file_size   TEXT NOT NULL DEFAULT '',
            width       INTEGER NOT NULL DEFAULT 0,
            height      INTEGER NOT NULL DEFAULT 0,
            remark      TEXT NOT NULL DEFAULT '',
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS feature_categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    // 默认自定义代码位置
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO custom_code (position, code) VALUES (?, '')");
    foreach (['head_css', 'head_js', 'footer_css', 'footer_js'] as $pos) {
        $stmt->execute([$pos]);
    }
}

function clear_config_cache(): void {
    $path = __DIR__ . '/../data/config_cache.json';
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * 已有数据库的增量迁移
 */
function migrate_schema(PDO $pdo): void {
    // 检测 apps 表是否有 icon_url 列
    $cols = $pdo->query("PRAGMA table_info(apps)")->fetchAll();
    $colNames = array_column($cols, 'name');

    if (!in_array('icon_url', $colNames)) {
        $pdo->exec("ALTER TABLE apps ADD COLUMN icon_url TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('ios_template', $colNames)) {
        $pdo->exec("ALTER TABLE apps ADD COLUMN ios_template TEXT NOT NULL DEFAULT 'modern'");
    }
    if (!in_array('ios_ipa_url', $colNames)) {
        $pdo->exec("ALTER TABLE apps ADD COLUMN ios_ipa_url TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('ios_bundle_id', $colNames)) {
        $pdo->exec("ALTER TABLE apps ADD COLUMN ios_bundle_id TEXT NOT NULL DEFAULT ''");
    }

    if (!in_array('feature_category_id', $colNames)) {
        $pdo->exec("ALTER TABLE apps ADD COLUMN feature_category_id INTEGER NOT NULL DEFAULT 0");
    }

    // app_downloads 增加 btn_icon 列
    $dlCols = $pdo->query("PRAGMA table_info(app_downloads)")->fetchAll();
    $dlColNames = array_column($dlCols, 'name');
    if (!in_array('btn_icon', $dlColNames)) {
        $pdo->exec("ALTER TABLE app_downloads ADD COLUMN btn_icon TEXT NOT NULL DEFAULT ''");
    }

    // 新增附件管理表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_platforms (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            app_id      INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
            name        TEXT NOT NULL,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS app_attachments (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            app_id        INTEGER NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
            platform_id   INTEGER NOT NULL REFERENCES app_platforms(id) ON DELETE CASCADE,
            version       TEXT NOT NULL,
            file_url      TEXT NOT NULL,
            file_size     TEXT NOT NULL DEFAULT '',
            changelog     TEXT NOT NULL DEFAULT '',
            sort_order    INTEGER NOT NULL DEFAULT 0,
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

    // 图片库表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS image_categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS image_library (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL REFERENCES image_categories(id) ON DELETE CASCADE,
            file_url    TEXT NOT NULL,
            filename    TEXT NOT NULL DEFAULT '',
            file_size   TEXT NOT NULL DEFAULT '',
            width       INTEGER NOT NULL DEFAULT 0,
            height      INTEGER NOT NULL DEFAULT 0,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    // 给已有的image_library表补sort_order列
    $ilCols = $pdo->query("PRAGMA table_info(image_library)")->fetchAll();
    $ilColNames = array_column($ilCols, 'name');
    if (!in_array('sort_order', $ilColNames)) {
        $pdo->exec("ALTER TABLE image_library ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('remark', $ilColNames)) {
        $pdo->exec("ALTER TABLE image_library ADD COLUMN remark TEXT NOT NULL DEFAULT ''");
    }

    // 特色卡片分类表 + feature_cards 新字段
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feature_categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");
    $fcCols = $pdo->query("PRAGMA table_info(feature_cards)")->fetchAll();
    $fcColNames = array_column($fcCols, 'name');
    if (!in_array('icon_url', $fcColNames)) {
        $pdo->exec("ALTER TABLE feature_cards ADD COLUMN icon_url TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('category_id', $fcColNames)) {
        $pdo->exec("ALTER TABLE feature_cards ADD COLUMN category_id INTEGER NOT NULL DEFAULT 0");
    }

    // 友情链接新增图标字段
    $flCols = $pdo->query("PRAGMA table_info(friend_links)")->fetchAll();
    $flColNames = array_column($flCols, 'name');
    if (!in_array('icon', $flColNames)) {
        $pdo->exec("ALTER TABLE friend_links ADD COLUMN icon TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('icon_url', $flColNames)) {
        $pdo->exec("ALTER TABLE friend_links ADD COLUMN icon_url TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('show_icon', $flColNames)) {
        $pdo->exec("ALTER TABLE friend_links ADD COLUMN show_icon INTEGER NOT NULL DEFAULT 0");
    }

    // 登录尝试记录表（基于IP的防爆破）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            ip          TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip, attempted_at);
    ");
}
