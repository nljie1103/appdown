<?php
/**
 * 特色卡片管理页 — 支持分类 + FA/图片双图标模式
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('特色卡片', 'features');
?>

<div class="page-header">
    <h1>特色卡片</h1>
    <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> 添加卡片</button>
</div>

<!-- 分类Tab栏 -->
<div class="card" style="padding:12px 16px;">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;" id="catTabs">
        <button class="btn btn-primary btn-sm" data-cat="all" onclick="selectCat('all',this)">全部</button>
        <button class="btn btn-outline btn-sm" data-cat="0" onclick="selectCat(0,this)">未分类</button>
        <!-- 动态分类Tab -->
    </div>
    <div style="margin-top:8px;">
        <button class="btn btn-outline btn-sm" onclick="addCategory()" style="font-size:0.8em;"><i class="fas fa-plus"></i> 添加分类</button>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead><tr><th></th><th>图标</th><th>标题</th><th>描述</th><th>分类</th><th>状态</th><th>操作</th></tr></thead>
            <tbody id="featureList"></tbody>
        </table>
    </div>
</div>

<!-- 添加/编辑卡片模态框 -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h3 id="modalTitle">添加卡片</h3>
        <input type="hidden" id="editId">
        <div class="form-group"><label>标题</label><input type="text" class="form-control" id="fTitle"></div>
        <div class="form-group"><label>描述</label><textarea class="form-control" id="fDesc" rows="3"></textarea></div>
        <div class="form-group">
            <label>图标</label>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px;">
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="fIconType" value="fa" checked onchange="toggleFIconMode()"> FA图标</label>
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="fIconType" value="image" onchange="toggleFIconMode()"> 自定义图片</label>
            </div>
            <div id="fIconFaMode">
                <div style="display:flex;gap:8px;align-items:center;">
                    <i id="fIconPreviewFa" class="fas fa-star" style="font-size:1.4em;width:32px;text-align:center;color:#666;"></i>
                    <input type="text" class="form-control" id="fIcon" placeholder="fas fa-star" oninput="document.getElementById('fIconPreviewFa').className=this.value||'fas fa-star'" style="flex:1;">
                    <button class="btn btn-outline" type="button" onclick="IconPicker.open(cls => { document.getElementById('fIcon').value=cls; document.getElementById('fIconPreviewFa').className=cls; })"><i class="fas fa-icons"></i> 选择</button>
                </div>
            </div>
            <div id="fIconImgMode" style="display:none;">
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="fIconPreviewImg" src="" style="width:32px;height:32px;border-radius:6px;object-fit:cover;border:1px solid #ddd;display:none;">
                    <button class="btn btn-outline" type="button" onclick="document.getElementById('fIconUpload').click()"><i class="fas fa-upload"></i> 上传</button>
                    <button class="btn btn-outline" type="button" onclick="ImagePicker.open(url => { document.getElementById('fIconUrl').value = url; document.getElementById('fIconPreviewImg').src = '/' + url; document.getElementById('fIconPreviewImg').style.display = ''; })"><i class="fas fa-images"></i> 图片库</button>
                    <input type="file" id="fIconUpload" accept="image/*" style="display:none;" onchange="uploadFIcon(this)">
                    <input type="hidden" id="fIconUrl">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>分类</label>
            <select class="form-control" id="fCategoryId">
                <option value="0">未分类</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('addModal')">取消</button>
            <button class="btn btn-primary" onclick="save()">保存</button>
        </div>
    </div>
</div>

<script>
let categories = [];
let currentCat = 'all';
let allCards = [];

async function loadCategories() {
    categories = await API.get('/admin/api/features.php?action=categories');
    renderCatTabs();
    updateCatSelect();
}

function renderCatTabs() {
    const el = document.getElementById('catTabs');
    // 保留前两个固定按钮
    const fixed = el.querySelectorAll('[data-cat="all"], [data-cat="0"]');
    // 移除动态分类
    el.querySelectorAll('.dyn-cat').forEach(e => e.remove());

    categories.forEach(c => {
        const btn = document.createElement('span');
        btn.className = 'dyn-cat';
        btn.style.cssText = 'display:inline-flex;align-items:center;gap:4px;';
        btn.innerHTML = `
            <button class="btn ${currentCat == c.id ? 'btn-primary' : 'btn-outline'} btn-sm" data-cat="${c.id}" onclick="selectCat(${c.id},this)">
                ${escapeHTML(c.name)} <small style="opacity:0.6;">(${c.card_count})</small>
            </button>
            <button class="btn btn-outline btn-sm" style="padding:4px 6px;font-size:0.75em;" onclick="renameCategory(${c.id},'${escapeHTML(c.name)}')" title="重命名">✎</button>
            <button class="btn btn-outline btn-sm" style="padding:4px 6px;font-size:0.75em;color:#e74c3c;border-color:#e74c3c;" onclick="deleteCategory(${c.id},'${escapeHTML(c.name)}')" title="删除">✕</button>
        `;
        el.appendChild(btn);
    });
}

function updateCatSelect() {
    const sel = document.getElementById('fCategoryId');
    sel.innerHTML = '<option value="0">未分类</option>' +
        categories.map(c => `<option value="${c.id}">${escapeHTML(c.name)}</option>`).join('');
}

function selectCat(cat, btn) {
    currentCat = cat;
    document.querySelectorAll('#catTabs [data-cat]').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline');
    });
    if (btn) {
        btn.classList.add('btn-primary');
        btn.classList.remove('btn-outline');
    }
    renderCards();
}

async function loadCards() {
    allCards = await API.get('/admin/api/features.php');
    renderCards();
}

function renderCards() {
    let list = allCards;
    if (currentCat !== 'all') {
        list = allCards.filter(f => f.category_id == currentCat);
    }

    const body = document.getElementById('featureList');
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-star"></i><p>暂无卡片</p></div></td></tr>';
        return;
    }
    body.innerHTML = list.map(f => {
        const iconHtml = f.icon_url
            ? `<img src="/${escapeHTML(f.icon_url)}" style="width:24px;height:24px;border-radius:4px;object-fit:cover;">`
            : (f.icon ? `<i class="${escapeHTML(f.icon)}" style="font-size:1.2em;color:#666;"></i>` : '<span style="color:#ccc;">-</span>');
        const catName = f.category_id > 0 ? (categories.find(c => c.id == f.category_id)?.name || '未知') : '未分类';
        return `
        <tr data-id="${f.id}" draggable="true">
            <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
            <td style="text-align:center;">${iconHtml}</td>
            <td><strong>${escapeHTML(f.title)}</strong></td>
            <td style="color:var(--text-secondary);max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHTML(f.description)}</td>
            <td><span style="font-size:0.85em;color:var(--text-secondary);">${escapeHTML(catName)}</span></td>
            <td><label class="toggle"><input type="checkbox" ${f.is_active?'checked':''} onchange="toggleCard(${f.id},this.checked)"><span class="toggle-slider"></span></label></td>
            <td>
                <button class="btn btn-outline btn-sm" onclick='editCard(${JSON.stringify(f)})'><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="delCard(${f.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        `;
    }).join('');

    initSortable(body, async (ids) => {
        await API.post('/admin/api/reorder.php', { table: 'feature_cards', order: ids });
        Toast.success('排序已保存');
    });
}

function toggleFIconMode() {
    const mode = document.querySelector('input[name="fIconType"]:checked').value;
    document.getElementById('fIconFaMode').style.display = mode === 'fa' ? '' : 'none';
    document.getElementById('fIconImgMode').style.display = mode === 'image' ? '' : 'none';
}

async function uploadFIcon(input) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('file', input.files[0]);
    fd.append('category', 'image');
    const res = await API.upload('/admin/api/upload.php', fd);
    if (res.ok) {
        document.getElementById('fIconUrl').value = res.url;
        document.getElementById('fIconPreviewImg').src = '/' + res.url;
        document.getElementById('fIconPreviewImg').style.display = '';
        Toast.success('图标已上传');
    }
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = '添加卡片';
    document.getElementById('editId').value = '';
    document.getElementById('fTitle').value = '';
    document.getElementById('fDesc').value = '';
    document.getElementById('fIcon').value = '';
    document.getElementById('fIconUrl').value = '';
    document.getElementById('fIconPreviewFa').className = 'fas fa-star';
    document.getElementById('fIconPreviewImg').style.display = 'none';
    document.querySelector('input[name="fIconType"][value="fa"]').checked = true;
    toggleFIconMode();
    // 默认选中当前分类
    if (currentCat !== 'all') {
        document.getElementById('fCategoryId').value = currentCat;
    } else {
        document.getElementById('fCategoryId').value = '0';
    }
    Modal.show('addModal');
}

function editCard(f) {
    document.getElementById('modalTitle').textContent = '编辑卡片';
    document.getElementById('editId').value = f.id;
    document.getElementById('fTitle').value = f.title;
    document.getElementById('fDesc').value = f.description;
    document.getElementById('fIcon').value = f.icon || '';
    document.getElementById('fIconUrl').value = f.icon_url || '';
    document.getElementById('fCategoryId').value = f.category_id || 0;

    if (f.icon_url) {
        document.querySelector('input[name="fIconType"][value="image"]').checked = true;
        document.getElementById('fIconPreviewImg').src = '/' + f.icon_url;
        document.getElementById('fIconPreviewImg').style.display = '';
    } else {
        document.querySelector('input[name="fIconType"][value="fa"]').checked = true;
        document.getElementById('fIconPreviewFa').className = f.icon || 'fas fa-star';
    }
    toggleFIconMode();
    Modal.show('addModal');
}

async function save() {
    const id = document.getElementById('editId').value;
    const iconType = document.querySelector('input[name="fIconType"]:checked').value;
    const body = {
        title: document.getElementById('fTitle').value.trim(),
        description: document.getElementById('fDesc').value.trim(),
        icon: iconType === 'fa' ? document.getElementById('fIcon').value.trim() : '',
        icon_url: iconType === 'image' ? document.getElementById('fIconUrl').value.trim() : '',
        category_id: parseInt(document.getElementById('fCategoryId').value) || 0,
    };

    if (id) {
        body.id = parseInt(id);
        body.is_active = 1;
        await API.put('/admin/api/features.php', body);
    } else {
        await API.post('/admin/api/features.php', body);
    }
    AlertModal.success('保存成功', '卡片信息已保存');
    Modal.hide('addModal');
    await loadCards();
    await loadCategories();
}

async function toggleCard(id, active) {
    await API.put('/admin/api/features.php', { id, is_active: active });
    Toast.success(active ? '已启用' : '已禁用');
}

async function delCard(id) {
    if (!confirmAction('确定删除此卡片？')) return;
    await API.del('/admin/api/features.php', { id });
    Toast.success('已删除');
    await loadCards();
    await loadCategories();
}

// === 分类管理 ===
async function addCategory() {
    const name = await PromptModal.open('添加特色卡片分类', '', '分类名称', '如: 视频功能, 社交功能');
    if (!name) return;
    await API.post('/admin/api/features.php?action=categories', { name: name.trim() });
    Toast.success('分类已添加');
    await loadCategories();
}

async function renameCategory(id, oldName) {
    const name = await PromptModal.open('重命名分类', oldName, '分类名称', '输入新的分类名称');
    if (!name || name === oldName) return;
    await API.put('/admin/api/features.php?action=categories', { id, name: name.trim() });
    Toast.success('已重命名');
    await loadCategories();
}

async function deleteCategory(id, name) {
    if (!confirmAction(`确定删除分类「${name}」？该分类下的卡片将变为未分类。`)) return;
    await API.del('/admin/api/features.php?action=categories', { id });
    if (currentCat == id) currentCat = 'all';
    Toast.success('已删除');
    await loadCategories();
    await loadCards();
}

// 初始化
(async function() {
    await loadCategories();
    await loadCards();
})();
</script>

<?php admin_footer(); ?>
