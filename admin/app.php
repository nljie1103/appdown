<?php
/** AppDown Admin 2.0 Vue shell. */
require_once __DIR__ . '/../includes/init.php';
require_auth();

$js = __DIR__ . '/vue/admin2.js';
$css = __DIR__ . '/vue/admin2.css';
if (!is_file($js) || !is_file($css)) {
    http_response_code(503);
    ?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AppDown Admin 2.0</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#f7f8fa;color:#18181b;margin:0;display:grid;place-items:center;min-height:100vh}.c{width:min(520px,calc(100% - 32px));background:#fff;border:1px solid #e6e8ec;border-radius:16px;padding:24px;box-shadow:0 10px 35px rgba(16,24,40,.08)}h1{font-size:20px;margin:0 0 8px}p{color:#71717a;font-size:14px;line-height:1.65}a{color:#4f46e5}</style></head><body><div class="c"><h1>Admin 2.0 前端尚未构建</h1><p>当前源码已经存在，但生产静态资源缺失。请使用正式 Release / GitHub CI 构建产物，或在 <code>admin-ui/</code> 执行 <code>npm ci && npm run build</code>。</p><a href="/admin/dashboard.php">返回旧后台</a></div></body></html><?php
    exit;
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<title>AppDown Admin 2.0</title>
<link rel="stylesheet" href="/admin/vue/admin2.css">
</head>
<body>
<div id="app"></div>
<script type="module" src="/admin/vue/admin2.js"></script>
</body>
</html>
