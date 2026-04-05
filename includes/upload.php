<?php
/**
 * 文件上传处理
 */

function handle_upload(string $field, string $category): array {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES[$field]['error'] ?? -1;
        return ['ok' => false, 'error' => "上传失败 (code: $code)"];
    }

    $file = $_FILES[$field];
    $rules = get_upload_rules($category);

    if (!$rules) {
        return ['ok' => false, 'error' => '无效的上传类型'];
    }

    // 检查文件大小
    if ($file['size'] > $rules['max_size']) {
        $max_mb = $rules['max_size'] / 1024 / 1024;
        return ['ok' => false, 'error' => "文件大小超过限制 ({$max_mb}MB)"];
    }

    // 检查扩展名
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $rules['extensions'], true)) {
        $allowed = implode(', ', $rules['extensions']);
        return ['ok' => false, 'error' => "不支持的文件类型，允许: $allowed"];
    }

    // MIME检查（图片和字体）
    if ($category === 'image') {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/webp', 'image/png', 'image/jpeg', 'image/gif'];
        if (!in_array($mime, $allowed_mimes, true)) {
            return ['ok' => false, 'error' => "文件MIME类型不合法: $mime"];
        }
    }

    // 生成安全文件名
    $safe_name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest_dir = __DIR__ . '/../uploads/' . $category . 's';

    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }

    $dest_path = $dest_dir . '/' . $safe_name;
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        return ['ok' => false, 'error' => '文件保存失败'];
    }

    $url = 'uploads/' . $category . 's/' . $safe_name;
    return ['ok' => true, 'url' => $url, 'filename' => $safe_name];
}

function get_upload_rules(string $category): ?array {
    return match($category) {
        'image' => [
            'extensions' => ['webp', 'png', 'jpg', 'jpeg', 'gif'],
            'max_size'   => 5 * 1024 * 1024,
        ],
        'font' => [
            'extensions' => ['ttf', 'woff', 'woff2', 'otf'],
            'max_size'   => 10 * 1024 * 1024,
        ],
        'app' => [
            'extensions' => ['apk', 'ipa', 'exe'],
            'max_size'   => 200 * 1024 * 1024,
        ],
        default => null,
    };
}

function delete_upload(string $url): void {
    if (str_starts_with($url, 'uploads/')) {
        $path = __DIR__ . '/../' . $url;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
