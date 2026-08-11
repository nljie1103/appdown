<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/updater.php';
require_auth();

$message = '';
$error = '';
$result = null;
$force = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'refresh') {
            $force = true;
            $message = '已重新从 GitHub 获取版本信息';
        } elseif ($action === 'update') {
            $latest = updater_check_release(true);
            $expected = (string)($_POST['tag'] ?? '');
            if ($expected === '' || !hash_equals($latest['tag'], $expected)) throw new RuntimeException('GitHub 最新版本已发生变化，请刷新页面后重试');
            if (empty($latest['update_available'])) throw new RuntimeException('当前已经是最新版');
            $result = updater_perform($latest);
            $message = '升级完成：' . $result['tag'] . '。请刷新页面重新加载新版本代码。';
        }
    } catch (Throwable $e) {
        error_log('[AppDown updater] ' . $e);
        $error = $e->getMessage();
    }
}

$latest = null;
try { $latest = updater_check_release($force); }
catch (Throwable $e) { $error = $error ?: $e->getMessage(); }

admin_header('在线升级', 'update');
?>
<div class="page-header"><h1>在线升级</h1></div>
<?php if ($message): ?><div class="alert alert-success" style="margin-bottom:16px"><?=htmlspecialchars($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" style="margin-bottom:16px"><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="card">
    <h3 style="margin-top:0">版本状态</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:16px 0">
        <div style="padding:16px;background:var(--bg);border-radius:10px"><div style="color:var(--text-secondary);font-size:.85em">当前版本</div><div style="font-size:1.45em;font-weight:700;margin-top:5px">v<?=htmlspecialchars(APPDOWN_VERSION)?></div><small>单用户版 main</small></div>
        <div style="padding:16px;background:var(--bg);border-radius:10px"><div style="color:var(--text-secondary);font-size:.85em">GitHub 最新正式版</div><div style="font-size:1.45em;font-weight:700;margin-top:5px"><?=htmlspecialchars($latest['tag'] ?? '无法获取')?></div><small><?=!empty($latest['published_at'])?htmlspecialchars($latest['published_at']):''?></small></div>
    </div>
    <form method="post" style="display:inline-block;margin-right:8px"><?=csrf_field()?><input type="hidden" name="action" value="refresh"><button class="btn btn-outline"><i class="fas fa-sync-alt"></i> 重新检查 GitHub</button></form>
    <?php if ($latest && !empty($latest['update_available'])): ?>
    <form method="post" style="display:inline-block" onsubmit="return confirm('将从官方 GitHub Release 下载 <?=htmlspecialchars($latest['tag'])?>，自动备份当前程序文件后覆盖升级。data/、uploads/ 和安装锁会保留。确定升级？')">
        <?=csrf_field()?><input type="hidden" name="action" value="update"><input type="hidden" name="tag" value="<?=htmlspecialchars($latest['tag'])?>"><button class="btn btn-primary"><i class="fas fa-cloud-download-alt"></i> 立即升级到 <?=htmlspecialchars($latest['tag'])?></button>
    </form>
    <?php elseif ($latest): ?><span style="margin-left:10px;color:var(--success)"><i class="fas fa-check-circle"></i> 已是最新版</span><?php endif; ?>
</div>
<?php if ($latest): ?>
<div class="card"><h3 style="margin-top:0">最新版说明</h3><div style="line-height:1.75;white-space:normal"><?=nl2br(htmlspecialchars($latest['notes'] ?: '该 Release 未填写更新说明'))?></div><?php if(!empty($latest['html_url'])):?><p><a class="btn btn-outline" target="_blank" rel="noopener" href="<?=htmlspecialchars($latest['html_url'])?>"><i class="fab fa-github"></i> 查看 GitHub Release</a></p><?php endif;?></div>
<?php endif; ?>
<div class="card"><h3 style="margin-top:0">升级安全机制</h3><ul style="line-height:1.9;color:var(--text-secondary)"><li>只连接固定仓库 <code><?=htmlspecialchars(updater_repo())?></code>，只接受 <code>vX.Y.Z</code> 正式 Release。</li><li>升级包必须包含匹配的 edition/version 标识，并检查 ZIP 路径穿越、符号链接、文件数量和解压大小。</li><li>覆盖前自动备份现有程序文件到 <code>data/update-backups/</code>。</li><li><code>data/</code> 运行数据、<code>uploads/</code> 用户文件、<code>install/install.lock</code> 和服务器本地配置不会被 Release 覆盖。</li><li>升级过程失败会尝试恢复代码备份；建议生产环境仍保留站点级/服务器级备份。</li></ul><?php if($result):?><p><b>本次代码备份：</b><code><?=htmlspecialchars(basename($result['backup']))?></code> · 更新 <?=$result['updated_files']?> 个文件</p><?php endif;?></div>
<?php admin_footer(); ?>
