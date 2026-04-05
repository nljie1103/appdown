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

    $stmt = $pdo->prepare('INSERT INTO apps (slug, name, icon, theme_color, sort_order) VALUES (?, ?, ?, ?, ?)');
    try {
        $stmt->execute([$slug, $name, $icon, $color, $max + 1]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
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
    foreach (['name', 'icon', 'theme_color', 'ios_plist_url', 'ios_cert_name', 'ios_description', 'ios_version', 'ios_size'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = trim($data[$f]);
        }
    }
    if (isset($data['is_active'])) {
        $fields[] = "is_active = ?";
        $params[] = $data['is_active'] ? 1 : 0;
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
    $pdo->prepare('DELETE FROM apps WHERE id = ?')->execute([$id]);
    clear_config_cache();
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
