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
        $targetFormat = strtolower(trim($_POST['format'] ?? 'webp'));
        $quality = (int)($_POST['quality'] ?? 80);
        if ($quality < 1) $quality = 1;
        if ($quality > 100) $quality = 100;

        $result = handle_upload('file', 'image', $customName);
        if (!$result['ok']) {
            json_response(['ok' => false, 'error' => $result['error']], 400);
        }

        $fullPath = __DIR__ . '/../../' . $result['url'];
        $originalExt = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        // 图片格式转换与压缩
        $needConvert = ($targetFormat !== 'original' && $targetFormat !== $originalExt && $originalExt !== 'svg' && $originalExt !== 'ico');
        $needCompress = ($targetFormat !== 'original' && $originalExt !== 'svg' && $originalExt !== 'ico');

        if (($needConvert || $needCompress) && extension_loaded('gd')) {
            $gdImage = null;
            switch ($originalExt) {
                case 'jpg': case 'jpeg':
                    $gdImage = @imagecreatefromjpeg($fullPath);
                    break;
                case 'png':
                    $gdImage = @imagecreatefrompng($fullPath);
                    break;
                case 'gif':
                    $gdImage = @imagecreatefromgif($fullPath);
                    break;
                case 'webp':
                    $gdImage = @imagecreatefromwebp($fullPath);
                    break;
                // bmp 需要 PHP 7.2+
                case 'bmp':
                    if (function_exists('imagecreatefrombmp')) {
                        $gdImage = @imagecreatefrombmp($fullPath);
                    }
                    break;
            }

            if ($gdImage) {
                // 保持透明通道
                imagealphablending($gdImage, false);
                imagesavealpha($gdImage, true);

                $outExt = $needConvert ? $targetFormat : $originalExt;
                if ($outExt === 'jpeg') $outExt = 'jpg';

                // 生成新文件名
                $baseName = pathinfo($fullPath, PATHINFO_FILENAME);
                $dir = dirname($fullPath);
                $newPath = $dir . '/' . $baseName . '.' . $outExt;

                // 如果格式变了，新旧路径可能不同
                if ($newPath !== $fullPath && file_exists($newPath)) {
                    $newPath = $dir . '/' . $baseName . '_' . bin2hex(random_bytes(2)) . '.' . $outExt;
                }

                $saved = false;
                switch ($outExt) {
                    case 'webp':
                        $saved = @imagewebp($gdImage, $newPath, $quality);
                        break;
                    case 'jpg':
                        // JPG 不支持透明，需要填充白色背景
                        $w = imagesx($gdImage);
                        $h = imagesy($gdImage);
                        $bg = imagecreatetruecolor($w, $h);
                        $white = imagecolorallocate($bg, 255, 255, 255);
                        imagefill($bg, 0, 0, $white);
                        imagecopy($bg, $gdImage, 0, 0, 0, 0, $w, $h);
                        $saved = @imagejpeg($bg, $newPath, $quality);
                        imagedestroy($bg);
                        break;
                    case 'png':
                        // PNG 压缩级别 0-9，quality 映射：100→0(无压缩), 1→9(最大压缩)
                        $pngLevel = (int)round((100 - $quality) / 100 * 9);
                        $saved = @imagepng($gdImage, $newPath, $pngLevel);
                        break;
                    case 'gif':
                        $saved = @imagegif($gdImage, $newPath);
                        break;
                }

                imagedestroy($gdImage);

                if ($saved) {
                    // 如果格式变了，删除旧文件
                    if ($newPath !== $fullPath) {
                        @unlink($fullPath);
                    }
                    $fullPath = $newPath;
                    $result['url'] = 'uploads/images/' . basename($newPath);
                }
            }
        }

        // 获取图片尺寸
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

        $actualExt = strtolower(pathinfo($result['url'], PATHINFO_EXTENSION));
        $originalName = $customName !== '' ? ($customName . '.' . $actualExt) : basename($result['url']);

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
        $newName = trim($data['filename'] ?? '');
        $remark = trim($data['remark'] ?? '');

        if (!$id) json_response(['error' => '缺少图片ID'], 400);

        // 查询当前记录
        $stmt = $pdo->prepare("SELECT file_url, filename FROM image_library WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetch();
        if (!$img) json_response(['error' => '图片不存在'], 404);

        $oldUrl = $img['file_url'];
        $newUrl = $oldUrl; // 默认不变
        $dbFilename = $newName;

        // 如果文件名变了，执行物理重命名
        if ($newName !== '' && $newName !== $img['filename']) {
            $oldPath = __DIR__ . '/../../' . $oldUrl;
            if (!file_exists($oldPath)) {
                json_response(['error' => '源文件不存在，无法重命名'], 400);
            }

            $ext = strtolower(pathinfo($oldUrl, PATHINFO_EXTENSION));
            // 如果用户输入带了正确后缀就用，否则自动补上原后缀
            $inputExt = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
            if ($inputExt === $ext) {
                $cleanBase = pathinfo($newName, PATHINFO_FILENAME);
            } else {
                $cleanBase = pathinfo($newName, PATHINFO_FILENAME) ?: $newName;
            }
            $cleanBase = preg_replace('/[^\w\x{4e00}-\x{9fff}.\-]/u', '_', $cleanBase);
            if ($cleanBase === '' || $cleanBase === '_') {
                $cleanBase = time() . '_' . bin2hex(random_bytes(4));
            }
            $safeName = $cleanBase . '.' . $ext;
            $dir = dirname($oldPath);
            $newPath = $dir . '/' . $safeName;

            // 避免同名冲突（排除自己）
            if ($newPath !== $oldPath && file_exists($newPath)) {
                $safeName = $cleanBase . '_' . bin2hex(random_bytes(2)) . '.' . $ext;
                $newPath = $dir . '/' . $safeName;
            }

            if ($newPath !== $oldPath) {
                if (!rename($oldPath, $newPath)) {
                    json_response(['error' => '文件重命名失败'], 500);
                }
                $newUrl = 'uploads/images/' . $safeName;
            }

            $dbFilename = $cleanBase . '.' . $ext;
        }

        $stmt = $pdo->prepare("UPDATE image_library SET filename = ?, file_url = ?, remark = ? WHERE id = ?");
        $stmt->execute([$dbFilename, $newUrl, $remark, $id]);
        json_response(['ok' => true, 'file_url' => $newUrl, 'filename' => $dbFilename]);
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
