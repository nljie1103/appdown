<?php
/**
 * 公共图片库 API
 * action=categories → 分类CRUD
 * action=images    → 图片CRUD（含上传）
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$method = get_request_method();
$action = $_GET['action'] ?? '';

// GET 不需要 CSRF
if ($method !== 'GET') {
    csrf_validate();
}

$pdo = get_db();

// ===== 分类 =====
if ($action === 'categories') {
    if ($method === 'GET') {
        $rows = $pdo->query("SELECT * FROM image_categories ORDER BY sort_order, id")->fetchAll();
        // 附带每个分类的图片数
        $counts = $pdo->query("SELECT category_id, COUNT(*) as cnt FROM image_library GROUP BY category_id")->fetchAll();
        $countMap = array_column($counts, 'cnt', 'category_id');
        foreach ($rows as &$r) {
            $r['image_count'] = (int)($countMap[$r['id']] ?? 0);
        }
        json_response($rows);
    }

    if ($method === 'POST') {
        $data = get_json_input();
        $name = trim($data['name'] ?? '');
        if ($name === '') json_response(['error' => '分类名称不能为空'], 400);

        $max = $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM image_categories")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO image_categories (name, sort_order) VALUES (?, ?)");
        $stmt->execute([$name, $max + 1]);
        json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    if ($method === 'PUT') {
        $data = get_json_input();
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        if (!$id || $name === '') json_response(['error' => '参数不完整'], 400);

        $stmt = $pdo->prepare("UPDATE image_categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        json_response(['ok' => true]);
    }

    if ($method === 'DELETE') {
        $data = get_json_input();
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少分类ID'], 400);

        // 删除该分类下所有图片文件
        $images = $pdo->prepare("SELECT file_url FROM image_library WHERE category_id = ?");
        $images->execute([$id]);
        foreach ($images->fetchAll() as $img) {
            delete_upload($img['file_url']);
        }

        // CASCADE 会自动删 image_library 记录
        $stmt = $pdo->prepare("DELETE FROM image_categories WHERE id = ?");
        $stmt->execute([$id]);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'method not allowed'], 405);
}

// ===== 图片 =====
if ($action === 'images') {
    if ($method === 'GET') {
        $catId = (int)($_GET['category_id'] ?? 0);
        if ($catId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM image_library WHERE category_id = ? ORDER BY sort_order, id DESC");
            $stmt->execute([$catId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM image_library ORDER BY sort_order, id DESC");
        }
        json_response($stmt->fetchAll());
    }

    if ($method === 'POST') {
        // 上传图片（multipart）
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if (!$categoryId) json_response(['error' => '请选择图片分类'], 400);

        $customName = trim($_POST['rename'] ?? '');
        $remark = trim($_POST['remark'] ?? '');

        $result = handle_upload('file', 'image', $customName);
        if (!$result['ok']) {
            json_response(['ok' => false, 'error' => $result['error']], 400);
        }

        // 获取图片尺寸
        $fullPath = __DIR__ . '/../../' . $result['url'];
        $size = @getimagesize($fullPath);
        $width = $size ? (int)$size[0] : 0;
        $height = $size ? (int)$size[1] : 0;
        $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

        // 格式化文件大小
        $fileSizeStr = '';
        if ($fileSize > 0) {
            if ($fileSize >= 1048576) {
                $fileSizeStr = round($fileSize / 1048576, 2) . ' MB';
            } else {
                $fileSizeStr = round($fileSize / 1024, 1) . ' KB';
            }
        }

        $originalName = $customName !== '' ? ($customName . '.' . pathinfo($_FILES['file']['name'] ?? '', PATHINFO_EXTENSION)) : ($_FILES['file']['name'] ?? '');

        $max = $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM image_library WHERE category_id = $categoryId")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO image_library (category_id, file_url, filename, file_size, width, height, sort_order, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$categoryId, $result['url'], $originalName, $fileSizeStr, $width, $height, $max + 1, $remark]);

        json_response([
            'ok' => true,
            'id' => (int)$pdo->lastInsertId(),
            'url' => $result['url'],
            'filename' => $originalName,
            'file_size' => $fileSizeStr,
            'width' => $width,
            'height' => $height,
        ]);
    }

    if ($method === 'PUT') {
        $data = get_json_input();
        $id = (int)($data['id'] ?? 0);
        $filename = trim($data['filename'] ?? '');
        $remark = trim($data['remark'] ?? '');

        if (!$id) json_response(['error' => '缺少图片ID'], 400);

        $stmt = $pdo->prepare("UPDATE image_library SET filename = ?, remark = ? WHERE id = ?");
        $stmt->execute([$filename, $remark, $id]);
        json_response(['ok' => true]);
    }

    if ($method === 'DELETE') {
        $data = get_json_input();
        $id = (int)($data['id'] ?? 0);
        if (!$id) json_response(['error' => '缺少图片ID'], 400);

        $stmt = $pdo->prepare("SELECT file_url FROM image_library WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetch();
        if ($img) {
            delete_upload($img['file_url']);
        }

        $stmt = $pdo->prepare("DELETE FROM image_library WHERE id = ?");
        $stmt->execute([$id]);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'method not allowed'], 405);
}

json_response(['error' => '未知操作'], 400);
