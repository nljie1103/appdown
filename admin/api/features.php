<?php
/**
 * 特色卡片 + 分类 CRUD API
 * action=categories → 分类CRUD
 * 默认(无action)  → 卡片CRUD（支持 icon_url, category_id）
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();
$action = $_GET['action'] ?? '';

// ===== 分类 =====
if ($action === 'categories') {
    if ($method === 'GET') {
        $rows = $pdo->query("SELECT * FROM feature_categories ORDER BY sort_order, id")->fetchAll();
        // 附带每个分类的卡片数
        $counts = $pdo->query("SELECT category_id, COUNT(*) as cnt FROM feature_cards WHERE category_id > 0 GROUP BY category_id")->fetchAll();
        $countMap = array_column($counts, 'cnt', 'category_id');
        foreach ($rows as &$r) {
            $r['card_count'] = (int)($countMap[$r['id']] ?? 0);
        }
        json_response($rows);
    }

    csrf_validate();

    if ($method === 'POST') {
        $data = get_json_input();
        $name = trim($data['name'] ?? '');
        if ($name === '') json_response(['error' => '分类名称不能为空'], 400);

        $max = $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM feature_categories")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO feature_categories (name, sort_order) VALUES (?, ?)");
        $stmt->execute([$name, $max + 1]);
        clear_config_cache();
        json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    if ($method === 'PUT') {
        $data = get_json_input();
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        if (!$id || $name === '') json_response(['error' => '参数不完整'], 400);

        $stmt = $pdo->prepare("UPDATE feature_categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        clear_config_cache();
        json_response(['ok' => true]);
    }

    if ($method === 'DELETE') {
        $data = get_json_input();
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少分类ID'], 400);

        // 该分类下的卡片变为未分类
        $pdo->prepare("UPDATE feature_cards SET category_id = 0 WHERE category_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM feature_categories WHERE id = ?")->execute([$id]);
        clear_config_cache();
        json_response(['ok' => true]);
    }

    json_response(['error' => 'method not allowed'], 405);
}

// ===== 卡片 =====
if ($method === 'GET') {
    $catId = $_GET['category_id'] ?? null;
    if ($catId !== null) {
        $catId = (int)$catId;
        $stmt = $pdo->prepare('SELECT * FROM feature_cards WHERE category_id = ? ORDER BY sort_order ASC');
        $stmt->execute([$catId]);
    } else {
        $stmt = $pdo->query('SELECT * FROM feature_cards ORDER BY sort_order ASC');
    }
    json_response($stmt->fetchAll());
}

csrf_validate();

if ($method === 'POST') {
    $data = get_json_input();
    $catId = (int)($data['category_id'] ?? 0);
    $max = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM feature_cards')->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO feature_cards (title, description, icon, icon_url, category_id, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        trim($data['title'] ?? ''),
        trim($data['description'] ?? ''),
        trim($data['icon'] ?? ''),
        trim($data['icon_url'] ?? ''),
        $catId,
        $max + 1,
    ]);
    clear_config_cache();
    json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
}

if ($method === 'PUT') {
    $data = get_json_input();
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_response(['error' => '缺少卡片ID'], 400);

    $fields = [];
    $params = [];
    $allowed = ['title', 'description', 'icon', 'icon_url', 'category_id', 'is_active'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $val = $data[$f];
            if ($f === 'is_active') $val = $val ? 1 : 0;
            if ($f === 'category_id') $val = (int)$val;
            if (is_string($val)) $val = trim($val);
            $params[] = $val;
        }
    }
    if (empty($fields)) json_response(['error' => '无更新字段'], 400);

    $params[] = $id;
    $sql = 'UPDATE feature_cards SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $pdo->prepare($sql)->execute($params);
    clear_config_cache();
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    $pdo->prepare('DELETE FROM feature_cards WHERE id = ?')->execute([get_json_input()['id'] ?? 0]);
    clear_config_cache();
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
