<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/super_auth.php';
require_once __DIR__ . '/../includes/updater.php';
require_super_auth();

$message = '';
$error = '';
$result = null;
$force = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    super_csrf_validate();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'refresh') { $force = true; $message = '已重新从 GitHub 获取版本信息'; }
        elseif ($action === 'update') {
            $latest = updater_check_release(true);
            $expected = (string)($_POST['tag'] ?? '');
            if ($expected === '' || !hash_equals($latest['tag'], $expected)) throw new RuntimeException('GitHub 最新版本已发生变化，请刷新后重试');
            if (empty($latest['update_available'])) throw new RuntimeException('当前已经是最新版');
            $result = updater_perform($latest);
            $message = '平台程序已升级到 ' . $result['tag'] . '。租户数据库和上传文件均保留。';
        }
    } catch (Throwable $e) { error_log('[AppDown SaaS updater] ' . $e); $error = $e->getMessage(); }
}
$latest = null;
try { $latest = updater_check_release($force); } catch (Throwable $e) { $error = $error ?: $e->getMessage(); }
$csrf = super_csrf_token();
?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>在线升级 - AppDown SaaS</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#172033;font-family:system-ui,-apple-system,sans-serif}.top{height:68px;background:#0d1321;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 max(24px,calc((100% - 1100px)/2))}.top a{color:#cbd5e1;text-decoration:none;margin-left:16px}.wrap{max-width:1100px;margin:auto;padding:28px 24px 60px}.card{background:#fff;border:1px solid #e6eaf0;border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 5px 24px rgba(35,50,78,.04)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.version{padding:18px;background:#f7f9fc;border-radius:12px}.version strong{display:block;font-size:1.5rem;margin:5px 0}.muted{color:#718096}.btn{border:0;border-radius:9px;padding:10px 15px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}.primary{background:#2563eb;color:#fff}.soft{background:#eef4ff;color:#2457bd}.notice{padding:12px 15px;border-radius:10px;margin-bottom:16px}.ok{background:#ecfdf3;color:#166534;border:1px solid #bbf7d0}.err{background:#fff1f0;color:#b42318;border:1px solid #fecaca}code{background:#f1f5f9;padding:2px 5px;border-radius:5px}@media(max-width:700px){.grid{grid-template-columns:1fr}}</style></head><body>
<header class="top"><b>AppDown SaaS · Super</b><div><a href="/super/dashboard.php">租户管理</a><a href="/">平台首页</a><a href="/super/logout.php">退出</a></div></header><main class="wrap"><h1>在线升级</h1><p class="muted">只有超级管理员可以升级整个平台；普通租户后台没有此权限。</p>
<?php if($message):?><div class="notice ok"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="notice err"><?=htmlspecialchars($error)?></div><?php endif;?>
<section class="card"><div class="grid"><div class="version"><span class="muted">当前 SaaS 版本</span><strong><?=htmlspecialchars(APPDOWN_RELEASE_TAG)?></strong><small>edition: saas</small></div><div class="version"><span class="muted">GitHub 最新 SaaS 正式版</span><strong><?=htmlspecialchars($latest['tag']??'无法获取')?></strong><small><?=htmlspecialchars($latest['published_at']??'')?></small></div></div><div style="margin-top:16px"><form method="post" style="display:inline-block;margin-right:8px"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="refresh"><button class="btn soft">重新检查 GitHub</button></form><?php if($latest&&!empty($latest['update_available'])):?><form method="post" style="display:inline-block" onsubmit="return confirm('将备份平台程序代码并升级到 <?=htmlspecialchars($latest['tag'])?>。所有租户 data/ 与 uploads/ 都会保留。确定继续？')"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="action" value="update"><input type="hidden" name="tag" value="<?=htmlspecialchars($latest['tag'])?>"><button class="btn primary">立即升级到 <?=htmlspecialchars($latest['tag'])?></button></form><?php elseif($latest):?><b style="color:#15803d">已是最新版</b><?php endif;?></div></section>
<?php if($latest):?><section class="card"><h2>Release 说明</h2><div style="line-height:1.75"><?=nl2br(htmlspecialchars($latest['notes']?:'无更新说明'))?></div><?php if(!empty($latest['html_url'])):?><p><a class="btn soft" target="_blank" rel="noopener" href="<?=htmlspecialchars($latest['html_url'])?>">查看 GitHub Release</a></p><?php endif;?></section><?php endif;?>
<section class="card"><h2>升级保护</h2><ul style="line-height:1.9" class="muted"><li>固定同步 <code><?=htmlspecialchars(updater_repo())?></code>，SaaS 只接受 <code>saas-vX.Y.Z</code>。</li><li>升级前备份平台程序文件到 <code>data/update-backups/</code>。</li><li><code>data/tenants/</code>、<code>data/saas.db</code>、所有租户 <code>uploads/</code>、主密钥和安装锁不会被覆盖。</li><li>Release ZIP 会校验版本线、路径穿越、符号链接、数量与大小。</li><li>升级失败会尝试自动回滚代码；生产环境仍建议保留完整服务器备份。</li></ul><?php if($result):?><p>备份：<code><?=htmlspecialchars(basename($result['backup']))?></code> · 更新 <?=$result['updated_files']?> 个文件</p><?php endif;?></section></main></body></html>
