/**
 * 后台公共JS - fetch封装、toast、模态框、CSRF、拖拽排序
 */

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

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
            Toast.error(err.message || '请求失败');
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
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            body: formData,
        });
        const data = await resp.json();
        if (!resp.ok || !data.ok) {
            const msg = data.error || '上传失败';
            Toast.error(msg);
            throw new Error(msg);
        }
        return data;
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
            el.style.transform = 'translateX(40px)';
            el.style.transition = 'all 0.3s';
            setTimeout(() => el.remove(), 300);
        }, duration);
    },

    success(msg) { this.show(msg, 'success'); },
    error(msg) { this.show(msg, 'error', 5000); },
    warning(msg) { this.show(msg, 'warning'); },
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

// 初始化toast
Toast.init();
