/**
 * 后台公共JS - fetch封装、toast、模态框、CSRF、拖拽排序
 */

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

// 防XSS: HTML转义
function escapeHTML(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

const API = {
    async request(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
            },
        };
        const config = { ...defaults, ...options };
        if (options.headers) {
            config.headers = { ...defaults.headers, ...options.headers };
        }

        try {
            const resp = await fetch(url, config);
            const data = await resp.json();
            if (!resp.ok) {
                throw new Error(data.error || `HTTP ${resp.status}`);
            }
            return data;
        } catch (err) {
            AlertModal.error('操作失败', err.message || '请求失败');
            throw err;
        }
    },

    get(url) { return this.request(url); },

    post(url, body) {
        return this.request(url, { method: 'POST', body: JSON.stringify(body) });
    },

    put(url, body) {
        return this.request(url, { method: 'PUT', body: JSON.stringify(body) });
    },

    del(url, body = {}) {
        return this.request(url, { method: 'DELETE', body: JSON.stringify(body) });
    },

    async upload(url, formData) {
        try {
            const resp = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                body: formData,
            });
            let data;
            try {
                data = await resp.json();
            } catch {
                AlertModal.error('上传失败', '服务器返回了无法解析的响应，可能原因：<br>1. 文件超出了PHP的 <code>post_max_size</code> 限制<br>2. 服务器内存不足<br><b>建议：</b>到「系统信息」页面查看当前上传限制，修改 php.ini 配置');
                throw new Error('上传失败');
            }
            if (!resp.ok || !data.ok) {
                const msg = data.error || '上传失败';
                AlertModal.error('上传失败', explainUploadError(msg));
                throw new Error(msg);
            }
            return data;
        } catch (err) {
            if (err.message === '上传失败') throw err;
            AlertModal.error('上传失败', '网络错误或服务器无响应');
            throw err;
        }
    },
};

// Toast通知
const Toast = {
    container: null,

    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },

    show(message, type = 'success', duration = 3000) {
        if (!this.container) this.init();
        const el = document.createElement('div');
        el.className = `toast ${type}`;
        el.textContent = message;
        this.container.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(-20px)';
            el.style.transition = 'all 0.3s';
            setTimeout(() => el.remove(), 300);
        }, duration);
    },

    success(msg) { this.show(msg, 'success'); },
    error(msg) { this.show(msg, 'error', 5000); },
    warning(msg) { this.show(msg, 'warning'); },
};

// 全局反馈弹窗 — 用于保存、上传等操作的明确反馈
const AlertModal = {
    _overlay: null,

    _ensure() {
        if (this._overlay) return;
        const o = document.createElement('div');
        o.className = 'modal-overlay';
        o.id = '_alertModal';
        o.innerHTML = `
            <div class="modal" style="max-width:400px;text-align:center;">
                <div id="_alertIcon" style="font-size:2.8em;margin-bottom:12px;"></div>
                <div id="_alertMsg" style="font-size:1.05em;font-weight:600;margin-bottom:6px;"></div>
                <div id="_alertDetail" style="font-size:0.85em;color:#666;margin-bottom:20px;"></div>
                <button class="btn btn-primary" style="min-width:120px;" onclick="AlertModal.hide()">关闭</button>
            </div>`;
        document.body.appendChild(o);
        this._overlay = o;
    },

    show(type, msg, detail) {
        this._ensure();
        const icons = {
            success: '<span style="color:#10b981;">&#10004;</span>',
            error:   '<span style="color:#ef4444;">&#10008;</span>',
            warning: '<span style="color:#f59e0b;">&#9888;</span>',
        };
        document.getElementById('_alertIcon').innerHTML = icons[type] || icons.success;
        document.getElementById('_alertMsg').textContent = msg;
        document.getElementById('_alertDetail').innerHTML = detail || '';
        this._overlay.classList.add('active');
    },

    hide() {
        if (this._overlay) this._overlay.classList.remove('active');
    },

    success(msg, detail) { this.show('success', msg, detail); },
    error(msg, detail)   { this.show('error', msg, detail); },
    warning(msg, detail) { this.show('warning', msg, detail); },
};

// 自定义输入弹窗（替代浏览器prompt，风格与友链弹窗一致）
const PromptModal = {
    _overlay: null,
    _resolve: null,

    _ensure() {
        if (this._overlay) return;
        const o = document.createElement('div');
        o.className = 'modal-overlay';
        o.id = '_promptModal';
        o.innerHTML = `
            <div class="modal">
                <h3 id="_promptTitle">请输入</h3>
                <div class="form-group">
                    <label id="_promptLabel">名称</label>
                    <input type="text" class="form-control" id="_promptInput" placeholder="">
                </div>
                <div class="modal-actions">
                    <button class="btn btn-outline" onclick="PromptModal._cancel()">取消</button>
                    <button class="btn btn-primary" onclick="PromptModal._confirm()">保存</button>
                </div>
            </div>`;
        document.body.appendChild(o);
        this._overlay = o;
        document.getElementById('_promptInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') PromptModal._confirm();
            if (e.key === 'Escape') PromptModal._cancel();
        });
    },

    /**
     * @param {string} title  弹窗标题，如"添加平台分类"
     * @param {string} defaultValue  输入框默认值
     * @param {string} label  输入框上方的 label 文字（默认"名称"）
     * @param {string} placeholder  输入框占位符
     */
    open(title, defaultValue, label, placeholder) {
        this._ensure();
        document.getElementById('_promptTitle').textContent = title || '请输入';
        document.getElementById('_promptLabel').textContent = label || '名称';
        const input = document.getElementById('_promptInput');
        input.value = defaultValue || '';
        input.placeholder = placeholder || '';
        this._overlay.classList.add('active');
        setTimeout(() => { input.focus(); input.select(); }, 100);
        return new Promise(resolve => { this._resolve = resolve; });
    },

    _confirm() {
        const val = document.getElementById('_promptInput').value.trim();
        this._overlay.classList.remove('active');
        if (this._resolve) { this._resolve(val || null); this._resolve = null; }
    },

    _cancel() {
        this._overlay.classList.remove('active');
        if (this._resolve) { this._resolve(null); this._resolve = null; }
    }
};

// 模态框
const Modal = {
    show(id) {
        document.getElementById(id)?.classList.add('active');
    },
    hide(id) {
        document.getElementById(id)?.classList.remove('active');
    },
};

// 确认对话框
function confirmAction(message) {
    return confirm(message);
}

// 拖拽排序
function initSortable(container, onDrop) {
    let draggedEl = null;

    container.addEventListener('dragstart', (e) => {
        const row = e.target.closest('[data-id]');
        if (!row) return;
        draggedEl = row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragend', () => {
        if (draggedEl) {
            draggedEl.classList.remove('dragging');
            draggedEl = null;
        }
    });

    container.addEventListener('dragover', (e) => {
        e.preventDefault();
        const row = e.target.closest('[data-id]');
        if (!row || row === draggedEl) return;
        const rect = row.getBoundingClientRect();
        const mid = rect.top + rect.height / 2;
        if (e.clientY < mid) {
            row.parentNode.insertBefore(draggedEl, row);
        } else {
            row.parentNode.insertBefore(draggedEl, row.nextSibling);
        }
    });

    container.addEventListener('drop', (e) => {
        e.preventDefault();
        const ids = [...container.querySelectorAll('[data-id]')].map(el => parseInt(el.dataset.id));
        if (onDrop) onDrop(ids);
    });
}

// 上传错误码解释（PHP UPLOAD_ERR_*）
function explainUploadError(msg) {
    const codeMatch = msg.match(/code:\s*(\d+)/);
    if (!codeMatch) return msg;
    const code = parseInt(codeMatch[1]);
    const explanations = {
        1: '文件超出了 php.ini 中 upload_max_filesize 的限制<br><b>建议：</b>修改 php.ini 中 <code>upload_max_filesize</code> 的值（当前限制可在系统信息页查看）',
        2: '文件超出了表单中 MAX_FILE_SIZE 的限制<br><b>建议：</b>减小文件体积或联系管理员调整限制',
        3: '文件只有部分被上传<br><b>建议：</b>网络可能不稳定，请重试上传',
        4: '没有文件被上传<br><b>建议：</b>请选择一个文件后再上传',
        6: '找不到临时文件夹<br><b>建议：</b>服务器临时目录配置异常，请联系系统管理员',
        7: '文件写入失败<br><b>建议：</b>服务器磁盘空间可能不足或权限不足',
    };
    return explanations[code] || `上传错误代码: ${code}`;
}

// 全局FA图标选择器
const IconPicker = {
    _overlay: null,
    _callback: null,
    _data: null,
    _filtered: [],
    _tab: 'solid', // solid | brands
    _page: 0,
    _pageSize: 120,
    _search: '',

    _ensure() {
        if (this._overlay) return;
        const o = document.createElement('div');
        o.className = 'modal-overlay';
        o.id = '_iconPicker';
        o.style.zIndex = '10002';
        o.innerHTML = `
            <div class="modal" style="max-width:680px;height:70vh;display:flex;flex-direction:column;padding:0;">
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:8px;align-items:center;">
                    <h3 style="margin:0;flex-shrink:0;">选择图标</h3>
                    <input type="text" class="form-control" id="_ipkSearch" placeholder="搜索图标..." style="flex:1;height:32px;font-size:0.85em;" oninput="IconPicker._onSearch(this.value)">
                    <button class="btn btn-outline btn-sm" onclick="IconPicker.close()" style="flex-shrink:0;">✕</button>
                </div>
                <div style="padding:8px 16px 0;display:flex;gap:6px;">
                    <button class="btn btn-sm" id="_ipkTabSolid" onclick="IconPicker._switchTab('solid')">Solid (fas)</button>
                    <button class="btn btn-sm btn-outline" id="_ipkTabBrands" onclick="IconPicker._switchTab('brands')">Brands (fab)</button>
                    <span style="flex:1;"></span>
                    <span id="_ipkCount" style="font-size:0.8em;color:var(--text-secondary);align-self:center;"></span>
                </div>
                <div id="_ipkGrid" style="flex:1;overflow-y:auto;padding:12px 16px;"></div>
                <div id="_ipkPager" style="padding:8px 16px;border-top:1px solid var(--border);display:flex;justify-content:center;gap:8px;"></div>
            </div>`;
        document.body.appendChild(o);
        this._overlay = o;
    },

    async open(callback) {
        this._callback = callback;
        this._ensure();
        this._search = '';
        this._page = 0;
        document.getElementById('_ipkSearch').value = '';
        this._overlay.classList.add('active');
        if (!this._data) {
            document.getElementById('_ipkGrid').innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-secondary);">加载图标中...</div>';
            try {
                const resp = await fetch('/admin/assets/fa-icons.json');
                this._data = await resp.json();
            } catch(e) {
                document.getElementById('_ipkGrid').innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444;">加载图标数据失败</div>';
                return;
            }
        }
        this._switchTab(this._tab);
    },

    close() {
        if (this._overlay) this._overlay.classList.remove('active');
        this._callback = null;
    },

    _switchTab(tab) {
        this._tab = tab;
        this._page = 0;
        document.getElementById('_ipkTabSolid').className = tab === 'solid' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
        document.getElementById('_ipkTabBrands').className = tab === 'brands' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
        this._applyFilter();
    },

    _onSearch(val) {
        this._search = val.trim().toLowerCase();
        this._page = 0;
        this._applyFilter();
    },

    _applyFilter() {
        const list = this._data[this._tab] || [];
        this._filtered = this._search ? list.filter(n => n.includes(this._search)) : list;
        document.getElementById('_ipkCount').textContent = this._filtered.length + ' 个图标';
        this._render();
    },

    _render() {
        const start = this._page * this._pageSize;
        const slice = this._filtered.slice(start, start + this._pageSize);
        const prefix = this._tab === 'brands' ? 'fab' : 'fas';
        const grid = document.getElementById('_ipkGrid');
        grid.innerHTML = slice.map(name => {
            const cls = `${prefix} fa-${name}`;
            return `<div class="icon-picker-item" onclick="IconPicker._pick('${cls}')" title="${cls}">
                <i class="${cls}"></i>
                <span>${name}</span>
            </div>`;
        }).join('');

        // Pager
        const totalPages = Math.ceil(this._filtered.length / this._pageSize);
        const pager = document.getElementById('_ipkPager');
        if (totalPages <= 1) { pager.innerHTML = ''; return; }
        let html = '';
        if (this._page > 0) html += `<button class="btn btn-outline btn-sm" onclick="IconPicker._goPage(${this._page - 1})">上一页</button>`;
        html += `<span style="align-self:center;font-size:0.8em;color:var(--text-secondary);">${this._page + 1} / ${totalPages}</span>`;
        if (this._page < totalPages - 1) html += `<button class="btn btn-outline btn-sm" onclick="IconPicker._goPage(${this._page + 1})">下一页</button>`;
        pager.innerHTML = html;
    },

    _goPage(p) {
        this._page = p;
        this._render();
        document.getElementById('_ipkGrid').scrollTop = 0;
    },

    _pick(cls) {
        if (this._callback) this._callback(cls);
        this.close();
    },
};

// 全局图片选择器
const ImagePicker = {
    _overlay: null,
    _callback: null,
    _categories: [],
    _currentCatId: null,

    _ensure() {
        if (this._overlay) return;
        // 注入分类样式
        if (!document.getElementById('_ipCatStyle')) {
            const st = document.createElement('style');
            st.id = '_ipCatStyle';
            st.textContent = `.ip-cat{display:flex;align-items:center;gap:10px;padding:10px 12px;margin:3px 4px;border-radius:8px;cursor:pointer;transition:all .15s;color:var(--text-secondary);border:1px solid transparent;}.ip-cat:hover{background:var(--bg);color:var(--text);border-color:var(--border);}.ip-cat.active{background:var(--primary);color:#fff;border-color:var(--primary);}.ip-cat.active i{color:#fff;}`;
            document.head.appendChild(st);
        }
        const o = document.createElement('div');
        o.className = 'modal-overlay';
        o.id = '_imagePicker';
        o.style.zIndex = '10001';
        o.innerHTML = `
            <div class="modal" style="max-width:720px;height:70vh;display:flex;flex-direction:column;padding:0;">
                <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;">从图片库选择</h3>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <button class="btn btn-primary btn-sm" id="_ipUploadBtn" style="display:none;" onclick="document.getElementById('_ipFileInput').click()"><i class="fas fa-upload"></i> 上传新图片</button>
                        <button class="btn btn-outline btn-sm" onclick="ImagePicker.close()">✕</button>
                    </div>
                </div>
                <div style="display:flex;flex:1;overflow:hidden;">
                    <div style="width:180px;border-right:1px solid var(--border);overflow-y:auto;padding:4px;" id="_ipCatList"></div>
                    <div style="flex:1;overflow-y:auto;padding:12px;" id="_ipImgList">
                        <div style="text-align:center;color:var(--text-secondary);padding:40px 0;">请先选择左侧分类</div>
                    </div>
                </div>
                <input type="file" id="_ipFileInput" accept="image/*" style="display:none;" onchange="ImagePicker._upload(this)">
            </div>`;
        document.body.appendChild(o);
        this._overlay = o;
    },

    async open(callback) {
        this._callback = callback;
        this._ensure();
        this._currentCatId = null;
        document.getElementById('_ipUploadBtn').style.display = 'none';
        document.getElementById('_ipImgList').innerHTML = '<div style="text-align:center;color:var(--text-secondary);padding:40px 0;">请先选择左侧分类</div>';
        this._overlay.classList.add('active');
        await this._loadCategories();
    },

    close() {
        if (this._overlay) this._overlay.classList.remove('active');
        this._callback = null;
    },

    async _loadCategories() {
        this._categories = await API.get('/admin/api/image-library.php?action=categories');
        const el = document.getElementById('_ipCatList');
        if (!this._categories.length) {
            el.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-secondary);font-size:0.85em;">暂无分类<br><small>请先到附件管理中创建图片分类</small></div>';
            return;
        }
        el.innerHTML = this._categories.map(c => `
            <div class="${c.id == this._currentCatId ? 'ip-cat active' : 'ip-cat'}" onclick="ImagePicker._selectCat(${c.id})">
                <i class="fas fa-folder${c.id == this._currentCatId ? '-open' : ''}" style="font-size:1.1em;"></i>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:0.88em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHTML(c.name)}</div>
                    <div style="font-size:0.75em;opacity:0.6;margin-top:2px;">${c.image_count} 张图片</div>
                </div>
            </div>
        `).join('');
    },

    async _selectCat(catId) {
        this._currentCatId = catId;
        // 重新渲染分类列表（更新高亮+图标）
        await this._loadCategories();

        document.getElementById('_ipUploadBtn').style.display = '';
        const images = await API.get(`/admin/api/image-library.php?action=images&category_id=${catId}`);
        const el = document.getElementById('_ipImgList');
        if (!images.length) {
            el.innerHTML = '<div style="text-align:center;color:var(--text-secondary);padding:40px 0;">该分类暂无图片</div>';
            return;
        }
        el.innerHTML = images.map(img => `
            <div style="display:flex;align-items:center;gap:10px;padding:8px;border-radius:8px;cursor:pointer;transition:background 0.15s;"
                 onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background=''"
                 onclick="ImagePicker._pick('${escapeHTML(img.file_url)}')">
                <img src="/${escapeHTML(img.file_url)}" style="width:32px;height:32px;border-radius:4px;object-fit:cover;border:1px solid var(--border);flex-shrink:0;" loading="lazy">
                <span style="flex:1;font-size:0.85em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHTML(img.filename || img.file_url.split('/').pop())}</span>
                <span style="font-size:0.75em;color:var(--text-secondary);white-space:nowrap;">${img.width && img.height ? img.width + '×' + img.height : ''}</span>
                <span style="font-size:0.75em;color:var(--text-secondary);white-space:nowrap;">${escapeHTML(img.file_size)}</span>
            </div>
        `).join('');
    },

    _pick(url) {
        if (this._callback) this._callback(url);
        this.close();
    },

    async _upload(input) {
        if (!input.files[0] || !this._currentCatId) return;
        const fd = new FormData();
        fd.append('file', input.files[0]);
        fd.append('category_id', this._currentCatId);
        fd.append('_csrf', CSRF_TOKEN);
        try {
            await API.upload('/admin/api/image-library.php?action=images', fd);
            Toast.success('图片已上传');
            await this._selectCat(this._currentCatId);
            await this._loadCategories();
        } catch(e) {}
        input.value = '';
    },
};

// 初始化toast
Toast.init();
