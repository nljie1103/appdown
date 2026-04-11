<?php
/**
 * 文件上传API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();
csrf_validate();
require_method('POST');

$category = $_POST['category'] ?? '';
if (!in_array($category, ['image', 'font', 'app', 'cert'], true)) {
    json_response(['ok' => false, 'error' => '无效的上传类型'], 400);
}

$result = handle_upload('file', $category);
json_response($result, $result['ok'] ? 200 : 400);
