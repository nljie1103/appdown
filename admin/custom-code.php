<?php
/**
 * 自定义代码注入页
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('自定义代码', 'code');
?>

<div class="page-header"><h1>自定义代码</h1></div>

<div style="background:#fff3cd;color:#856404;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.9em;">
    <i class="fas fa-exclamation-triangle"></i> 错误的代码可能导致网站无法正常显示，请谨慎修改。适合添加统计代码（如百度统计、Google Analytics）等。
</div>

<div class="card">
    <h3>Head CSS (在 &lt;/head&gt; 前注入)</h3>
    <textarea class="form-control" id="head_css" rows="6" style="font-family:monospace;" placeholder="/* 自定义CSS */"></textarea>
    <button class="btn btn-primary btn-sm" onclick="save('head_css')" style="margin-top:10px;"><i class="fas fa-save"></i> 保存</button>
</div>

<div class="card">
    <h3>Head JS (在 &lt;/head&gt; 前注入)</h3>
    <textarea class="form-control" id="head_js" rows="6" style="font-family:monospace;" placeholder="// 自定义JavaScript"></textarea>
    <button class="btn btn-primary btn-sm" onclick="save('head_js')" style="margin-top:10px;"><i class="fas fa-save"></i> 保存</button>
</div>

<div class="card">
    <h3>Footer CSS (在 &lt;/body&gt; 前注入)</h3>
    <textarea class="form-control" id="footer_css" rows="6" style="font-family:monospace;" placeholder="/* 自定义CSS */"></textarea>
    <button class="btn btn-primary btn-sm" onclick="save('footer_css')" style="margin-top:10px;"><i class="fas fa-save"></i> 保存</button>
</div>

<div class="card">
    <h3>Footer JS (在 &lt;/body&gt; 前注入)</h3>
    <textarea class="form-control" id="footer_js" rows="6" style="font-family:monospace;" placeholder="// 自定义JavaScript (如统计代码)"></textarea>
    <button class="btn btn-primary btn-sm" onclick="save('footer_js')" style="margin-top:10px;"><i class="fas fa-save"></i> 保存</button>
</div>

<script>
async function load() {
    const data = await API.get('/admin/api/custom-code.php');
    ['head_css', 'head_js', 'footer_css', 'footer_js'].forEach(pos => {
        const el = document.getElementById(pos);
        if (el) el.value = data[pos] || '';
    });
}

async function save(position) {
    const code = document.getElementById(position).value;
    await API.post('/admin/api/custom-code.php', { position, code });
    Toast.success('已保存');
}

load();
</script>

<?php admin_footer(); ?>
