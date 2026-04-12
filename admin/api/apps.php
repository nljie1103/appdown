<?php
/**
 * 应用CRUD API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM apps WHERE id = ?');
        $stmt->execute([$id]);
        $app = $stmt->fetch();
        if (!$app) json_response(['error' => 'not found'], 404);

        $dl = $pdo->prepare('SELECT * FROM app_downloads WHERE app_id = ? ORDER BY sort_order');
        $dl->execute([$id]);
        $app['downloads'] = $dl->fetchAll();

        $img = $pdo->prepare('SELECT * FROM app_images WHERE app_id = ? ORDER BY sort_order');
        $img->execute([$id]);
        $app['images'] = $img->fetchAll();

        json_response($app);
    }

    $apps = $pdo->query('SELECT a.*, (SELECT COUNT(*) FROM app_downloads WHERE app_id=a.id) as dl_count, (SELECT COUNT(*) FROM app_images WHERE app_id=a.id) as img_count FROM apps a ORDER BY sort_order ASC')->fetchAll();
    json_response($apps);
}

csrf_validate();

if ($method === 'POST') {
    $data = get_json_input();
    $slug = trim($data['slug'] ?? '');
    $name = trim($data['name'] ?? '');
    $icon = trim($data['icon'] ?? 'fas fa-tv');
    $color = trim($data['theme_color'] ?? '#007AFF');

    if (empty($slug) || empty($name)) {
        json_response(['error' => '标识和名称不能为空'], 400);
    }

    if (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
        json_response(['error' => '标识只能包含小写字母、数字、下划线和横线'], 400);
    }

    $max = $pdo->query('SELECT COALESCE(MAX(sort_order),0) as m FROM apps')->fetch()['m'];

    $stmt = $pdo->prepare('INSERT INTO apps (slug, name, icon, icon_url, theme_color, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    try {
        $stmt->execute([$slug, $name, $icon, trim($data['icon_url'] ?? ''), $color, $max + 1]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            json_response(['error' => '标识已存在'], 409);
        }
        throw $e;
    }

    clear_config_cache();
    json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

if ($method === 'PUT') {
    $data = get_json_input();
    $id = $data['id'] ?? 0;

    $fields = [];
    $params = [];
    foreach (['name', 'icon', 'icon_url', 'theme_color', 'ios_plist_url', 'ios_ipa_url', 'ios_bundle_id', 'ios_cert_name', 'ios_description', 'ios_version', 'ios_size', 'ios_template', 'mc_url', 'mc_icon_data', 'mc_bundle_id', 'mc_version', 'mc_description', 'mc_template', 'mc_sign_cert', 'mc_sign_key', 'mc_sign_chain', 'mc_sign_mode', 'mc_payload_org', 'android_template', 'mc_file_url'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = trim($data[$f]);
        }
    }
    if (isset($data['mc_file_id'])) {
        $fields[] = "mc_file_id = ?";
        $params[] = $data['mc_file_id'] ? (int)$data['mc_file_id'] : null;
    }
    if (isset($data['is_active'])) {
        $fields[] = "is_active = ?";
        $params[] = $data['is_active'] ? 1 : 0;
    }
    if (isset($data['mc_fullscreen'])) {
        $fields[] = "mc_fullscreen = ?";
        $params[] = $data['mc_fullscreen'] ? 1 : 0;
    }
    if (isset($data['feature_category_id'])) {
        $fields[] = "feature_category_id = ?";
        $params[] = (int)$data['feature_category_id'];
    }

    if (empty($fields)) json_response(['error' => '没有要更新的字段'], 400);

    $fields[] = "updated_at = datetime('now')";
    $params[] = $id;

    $sql = 'UPDATE apps SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $pdo->prepare($sql)->execute($params);

    clear_config_cache();
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $data = get_json_input();
    $id = $data['id'] ?? 0;

    // 删除应用图标图片
    $iconUrl = $pdo->prepare('SELECT icon_url FROM apps WHERE id = ?');
    $iconUrl->execute([$id]);
    $row = $iconUrl->fetch();
    if ($row && $row['icon_url']) delete_upload($row['icon_url']);

    // 删除关联的轮播图文件
    $imgs = $pdo->prepare('SELECT image_url FROM app_images WHERE app_id = ?');
    $imgs->execute([$id]);
    foreach ($imgs->fetchAll() as $img) {
        delete_upload($img['image_url']);
    }

    // 删除关联的附件文件
    $atts = $pdo->prepare('SELECT file_url FROM app_attachments WHERE app_id = ?');
    $atts->execute([$id]);
    foreach ($atts->fetchAll() as $att) {
        delete_upload($att['file_url']);
    }

    $pdo->prepare('DELETE FROM apps WHERE id = ?')->execute([$id]);
    clear_config_cache();
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
