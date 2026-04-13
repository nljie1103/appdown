<?php
/**
 * 文件上传处理
 */

/**
 * 生成无冲突文件名：base.ext → base(1).ext → base(2).ext ...
 */
function resolve_filename_collision(string $dir, string $base, string $ext): string {
    $filename = $base . '.' . $ext;
    if (!file_exists($dir . '/' . $filename)) {
        return $filename;
    }
    $i = 1;
    do {
        $filename = $base . '(' . $i . ').' . $ext;
        $i++;
    } while (file_exists($dir . '/' . $filename));
    return $filename;
}

function handle_upload(string $field, string $category, string $custom_name = ''): array {
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
        $allowed_mimes = ['image/webp', 'image/png', 'image/jpeg', 'image/gif',
                          'image/x-icon', 'image/vnd.microsoft.icon'];
        if (!in_array($mime, $allowed_mimes, true)) {
            return ['ok' => false, 'error' => "文件MIME类型不合法: $mime"];
        }
    }

    // 生成安全文件名
    if ($custom_name !== '') {
        // 使用自定义名称，清理非法字符
        $clean = preg_replace('/[^\w\x{4e00}-\x{9fff}.\-]/u', '_', $custom_name);
        $safe_name = $clean . '.' . $ext;
    } else {
        // 保留原始文件名，清理非法字符
        $original = pathinfo($file['name'], PATHINFO_FILENAME);
        $clean = preg_replace('/[^\w\x{4e00}-\x{9fff}.\-]/u', '_', $original);
        if ($clean === '' || $clean === '_') {
            $clean = time() . '_' . bin2hex(random_bytes(4));
        }
        $safe_name = $clean . '.' . $ext;
    }
    $dest_dir = __DIR__ . '/../uploads/' . $category . 's';

    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }

    $dest_path = $dest_dir . '/' . $safe_name;
    // 如有同名文件，追加 (1)(2) 后缀
    if (file_exists($dest_path)) {
        $base = pathinfo($safe_name, PATHINFO_FILENAME);
        $safe_name = resolve_filename_collision($dest_dir, $base, $ext);
        $dest_path = $dest_dir . '/' . $safe_name;
    }
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        return ['ok' => false, 'error' => '文件保存失败'];
    }

    $url = 'uploads/' . $category . 's/' . $safe_name;
    return ['ok' => true, 'url' => $url, 'filename' => $safe_name];
}

function get_upload_rules(string $category): ?array {
    // 读取PHP配置的上传限制
    $php_max = min(parse_size(ini_get('upload_max_filesize')), parse_size(ini_get('post_max_size')));

    switch ($category) {
        case 'image':
            return [
                'extensions' => ['webp', 'png', 'jpg', 'jpeg', 'gif', 'ico'],
                'max_size'   => $php_max,
            ];
        case 'font':
            return [
                'extensions' => ['ttf', 'woff', 'woff2', 'otf'],
                'max_size'   => $php_max,
            ];
        case 'app':
            return [
                'extensions' => ['apk', 'ipa', 'exe', 'dmg', 'zip'],
                'max_size'   => $php_max,
            ];
        case 'cert':
            return [
                'extensions' => ['pem', 'crt', 'key', 'p12'],
                'max_size'   => $php_max,
            ];
        case 'keystore':
            return [
                'extensions' => ['jks', 'keystore', 'p12', 'pfx', 'bks'],
                'max_size'   => $php_max,
            ];
        default:
            return null;
    }
}

/**
 * 将PHP的ini大小值(如 "200M", "1G")转为字节数
 */
function parse_size(string $val): int {
    $val = trim($val);
    $unit = strtolower(substr($val, -1));
    $num = (int)$val;
    switch ($unit) {
        case 'g': return $num * 1024 * 1024 * 1024;
        case 'm': return $num * 1024 * 1024;
        case 'k': return $num * 1024;
        default:  return $num;
    }
}

function delete_upload(string $url): void {
    if (substr($url, 0, 8) !== 'uploads/' || strpos($url, '..') !== false) {
        return;
    }
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if (!$uploadsDir) return;
    $path = realpath(__DIR__ . '/../' . $url);
    if ($path && str_starts_with($path, $uploadsDir . DIRECTORY_SEPARATOR) && is_file($path)) {
        unlink($path);
    }
}
