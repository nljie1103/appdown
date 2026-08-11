<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/super_auth.php';
require_super_auth();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    super_csrf_validate();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $result = create_tenant(
                (string)($_POST['slug'] ?? ''),
                (string)($_POST['display_name'] ?? ''),
                (string)($_POST['password'] ?? '')
            );
            if (!$result['ok']) $error = $result['error'];
            else $message = '租户 ' . $result['slug'] . ' 创建成功';
        } elseif ($action === 'toggle') {
            $slug = (string)($_POST['slug'] ?? '');
            $status = (string)($_POST['status'] ?? 'disabled');
            $result = update_tenant_record($slug, ['status' => $status]);
            if (!$result['ok']) $error = $result['error'];
            else $message = '租户状态已更新';
        } elseif ($action === 'update') {
            $slug = (string)($_POST['slug'] ?? '');
            $changes = ['display_name' => (string)($_POST['display_name'] ?? '')];
            if ((string)($_POST['password'] ?? '') !== '') $changes['password'] = (string)$_POST['password'];
            $result = update_tenant_record($slug, $changes);
            if (!$result['ok']) $error = $result['error'];
            else $message = '租户资料已更新';
        } elseif ($action === 'delete') {
            $slug = normalize_tenant_slug((string)($_POST['slug'] ?? ''));
            $confirm = normalize_tenant_slug((string)($_POST['confirm_slug'] ?? ''));
            if ($slug === '' || !hash_equals($slug, $confirm)) {
                $error = '永久删除需要再次输入完整用户名确认';
            } else {
                $result = delete_tenant_permanently($slug);
                if (!$result['ok']) $error = $result['error'];
                else $message = '租户及其数据库、上传文件已永久删除';
            }
        }
    } catch (Throwable $e) {
        error_log('[AppDown super] ' . $e);
        $error = '操作失败：' . $e->getMessage();
    }
}

$tenants = list_tenants();
$total = count($tenants);
$active = count(array_filter($tenants, fn($t) => $t['status'] === 'active'));
$disabled = $total - $active;
$csrf = super_csrf_token();

function tenant_disk_bytes(string $slug): int {
    $sum = 0;
    foreach ([tenant_data_dir($slug), tenant_upload_dir($slug)] as $root) {
        if (!is_dir($root)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if ($f->isFile()) $sum += $f->getSize();
    }
    return $sum;
}
function fmt_bytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 1) . ' MB';
    return number_format($bytes / 1073741824, 2) . ' GB';
}
?>
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>AppDown SaaS 超级后台</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#172033;font-family:system-ui,-apple-system,sans-serif}.top{height:68px;background:#0d1321;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 max(24px,calc((100% - 1280px)/2));position:sticky;top:0;z-index:10}.brand{font-weight:800;font-size:1.1rem}.top a{color:#cbd5e1;text-decoration:none;font-size:.9rem}.wrap{max-width:1280px;margin:0 auto;padding:28px 24px 60px}.head{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:22px}.head h1{margin:0 0 5px;font-size:1.7rem}.muted{color:#718096;font-size:.9rem}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}.stat,.card{background:#fff;border:1px solid #e6eaf0;border-radius:16px;box-shadow:0 5px 24px rgba(35,50,78,.04)}.stat{padding:20px}.stat strong{display:block;font-size:1.8rem;margin-top:5px}.card{padding:20px;margin-bottom:20px}.card h2{margin:0 0 16px;font-size:1.05rem}.formgrid{display:grid;grid-template-columns:1fr 1.3fr 1.2fr auto;gap:10px}.input{width:100%;padding:10px 12px;border:1px solid #dbe1ea;border-radius:9px;font:inherit;background:#fff}.input:focus{outline:0;border-color:#4f86f7;box-shadow:0 0 0 3px rgba(79,134,247,.12)}.btn{border:0;border-radius:9px;padding:10px 15px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.primary{background:#2563eb;color:#fff}.soft{background:#eef4ff;color:#2457bd}.danger{background:#fff0f0;color:#bf3030}.dark{background:#172033;color:#fff}.notice{padding:12px 15px;border-radius:10px;margin-bottom:16px}.ok{background:#ecfdf3;color:#166534;border:1px solid #bbf7d0}.err{background:#fff1f0;color:#b42318;border:1px solid #fecaca}.tenant-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:15px}.tenant{background:#fff;border:1px solid #e4e9f0;border-radius:16px;padding:18px}.tenant-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:14px}.tenant h3{margin:0 0 4px;font-size:1.05rem}.slug{font-family:ui-monospace,monospace;color:#667085;font-size:.83rem}.badge{padding:4px 8px;border-radius:999px;font-size:.75rem;font-weight:700}.active{background:#dcfce7;color:#166534}.disabled{background:#f1f5f9;color:#64748b}.meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.82rem;color:#667085;margin-bottom:14px}.actions{display:flex;gap:8px;flex-wrap:wrap}.editbox{margin-top:14px;padding-top:14px;border-top:1px solid #edf0f4}.editbox form{display:grid;gap:8px}.dangerbox{margin-top:10px;padding-top:10px;border-top:1px dashed #f1b7b7}.empty{text-align:center;color:#98a2b3;padding:34px}@media(max-width:760px){.stats{grid-template-columns:1fr}.formgrid{grid-template-columns:1fr}.tenant-grid{grid-template-columns:1fr}.head{align-items:flex-start;flex-direction:column}.top{padding:0 18px}}
</style></head><body>
<header class="top"><div class="brand">AppDown SaaS · Super</div><div><span style="color:#94a3b8;margin-right:15px"><?=htmlspecialchars($_SESSION['super_user'] ?? '')?></span><a href="/super/logout.php">退出</a></div></header>
<main class="wrap">
<div class="head"><div><h1>租户管理</h1><div class="muted">一个租户对应一个独立分发页、SQLite 数据库和上传目录。</div></div><div class="actions"><a class="btn soft" href="/super/update.php">在线升级</a><a class="btn dark" href="/" target="_blank">查看平台首页</a></div></div>
<?php if($message):?><div class="notice ok"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="notice err"><?=htmlspecialchars($error)?></div><?php endif;?>
<section class="stats"><div class="stat"><span class="muted">全部租户</span><strong><?=$total?></strong></div><div class="stat"><span class="muted">正常</span><strong><?=$active?></strong></div><div class="stat"><span class="muted">已停用</span><strong><?=$disabled?></strong></div></section>
<section class="card"><h2>创建新租户</h2><form method="post" class="formgrid"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="create"><input class="input" name="slug" placeholder="用户名，如 leon" pattern="[a-z0-9][a-z0-9_-]{2,31}" required><input class="input" name="display_name" placeholder="站点显示名称" required><input class="input" type="password" name="password" minlength="8" placeholder="初始密码（至少8位）" required><button class="btn primary">创建租户</button></form></section>
<section class="tenant-grid">
<?php foreach($tenants as $t): $disk=tenant_disk_bytes($t['slug']); ?>
<article class="tenant"><div class="tenant-top"><div><h3><?=htmlspecialchars($t['display_name'])?></h3><div class="slug">/<?=htmlspecialchars($t['slug'])?>/</div></div><span class="badge <?=$t['status']==='active'?'active':'disabled'?>"><?=$t['status']==='active'?'正常':'已停用'?></span></div>
<div class="meta"><span>创建：<?=htmlspecialchars($t['created_at'])?></span><span>占用：<?=htmlspecialchars(fmt_bytes($disk))?></span><span>最后登录：<?=htmlspecialchars($t['last_login'] ?: '从未')?></span><span>数据库独立</span></div>
<div class="actions"><a class="btn soft" href="/<?=rawurlencode($t['slug'])?>/" target="_blank">打开分发页</a><a class="btn soft" href="/admin/login.php?tenant=<?=rawurlencode($t['slug'])?>" target="_blank">后台登录</a><form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="slug" value="<?=htmlspecialchars($t['slug'])?>"><input type="hidden" name="status" value="<?=$t['status']==='active'?'disabled':'active'?>"><button class="btn <?=$t['status']==='active'?'danger':'primary'?>"><?=$t['status']==='active'?'停用':'启用'?></button></form></div>
<div class="editbox"><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="update"><input type="hidden" name="slug" value="<?=htmlspecialchars($t['slug'])?>"><input class="input" name="display_name" value="<?=htmlspecialchars($t['display_name'])?>" required><input class="input" type="password" name="password" minlength="8" placeholder="留空则不重置密码"><button class="btn dark">保存名称 / 重置密码</button></form></div>
<div class="dangerbox"><form method="post" onsubmit="return confirm('永久删除会同时删除该租户数据库、主密钥和上传文件，无法恢复。确定继续？')"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="slug" value="<?=htmlspecialchars($t['slug'])?>"><div style="display:flex;gap:8px"><input class="input" name="confirm_slug" placeholder="输入 <?=htmlspecialchars($t['slug'])?> 确认永久删除" required><button class="btn danger">永久删除</button></div></form></div></article>
<?php endforeach;?>
<?php if(!$tenants):?><div class="card empty">还没有租户，先创建第一个分发站。</div><?php endif;?>
</section></main></body></html>
