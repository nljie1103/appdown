<?php
/**
 * 字体管理页
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('字体管理', 'fonts');
?>

<div class="page-header"><h1>字体管理</h1></div>

<div class="card">
    <h3>当前字体</h3>
    <div class="form-row">
        <div class="form-group">
            <label>字体名称</label>
            <input type="text" class="form-control" id="fontFamily" placeholder="如: CustomFont">
        </div>
        <div class="form-group">
            <label>字体文件地址</label>
            <div style="display:flex;gap:8px;">
                <input type="text" class="form-control" id="fontUrl" style="flex:1;">
                <button class="btn btn-outline" onclick="document.getElementById('fontUpload').click()"><i class="fas fa-upload"></i> 上传</button>
                <input type="file" id="fontUpload" accept=".ttf,.woff,.woff2,.otf" style="display:none;">
            </div>
        </div>
    </div>
    <div id="fontPreview" style="margin:16px 0;padding:20px;background:#f9f9f9;border-radius:8px;font-size:1.5em;">
        字体预览: AppDown ABCDEFG abcdefg 1234567890
    </div>
    <button class="btn btn-primary" onclick="save()"><i class="fas fa-save"></i> 保存</button>
</div>

<div class="card">
    <h3>内置字体选择</h3>
    <p style="color:var(--text-secondary);margin-bottom:12px;">选择内置字体将使用系统字体，无需上传文件</p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;" id="builtinFonts"></div>
</div>

<script>
const builtinFonts = [
    { name: 'system-ui', label: '系统默认' },
    { name: 'Arial, sans-serif', label: 'Arial' },
    { name: '"Segoe UI", sans-serif', label: 'Segoe UI' },
    { name: '"PingFang SC", sans-serif', label: '苹方' },
    { name: '"Microsoft YaHei", sans-serif', label: '微软雅黑' },
    { name: '"Noto Sans SC", sans-serif', label: 'Noto Sans SC' },
    { name: 'serif', label: '衬线体' },
    { name: 'monospace', label: '等宽体' },
];

async function load() {
    const s = await API.get('/admin/api/settings.php');
    document.getElementById('fontFamily').value = s.font_family || 'CustomFont';
    document.getElementById('fontUrl').value = s.font_url || '';
    updatePreview();

    const container = document.getElementById('builtinFonts');
    builtinFonts.forEach((f, i) => {
        const btn = document.createElement('button');
        btn.className = 'btn btn-outline';
        btn.style.fontFamily = f.name;
        btn.textContent = f.label;
        btn.addEventListener('click', () => selectBuiltin(f.name));
        container.appendChild(btn);
    });
}

function selectBuiltin(family) {
    document.getElementById('fontFamily').value = family;
    document.getElementById('fontUrl').value = '';
    updatePreview();
}

function updatePreview() {
    const family = document.getElementById('fontFamily').value;
    document.getElementById('fontPreview').style.fontFamily = family;
}

document.getElementById('fontFamily').addEventListener('input', updatePreview);

document.getElementById('fontUpload').addEventListener('change', async function() {
    if (!this.files.length) return;
    const file = this.files[0];

    // 尝试从字体文件读取字体名称
    const fontName = await readFontName(file);
    if (fontName) {
        document.getElementById('fontFamily').value = fontName;
    } else {
        document.getElementById('fontFamily').value = '用户上传字体';
    }

    const fd = new FormData();
    fd.append('file', file);
    fd.append('category', 'font');
    fd.append('_csrf', CSRF_TOKEN);
    try {
        const res = await API.upload('/admin/api/upload.php', fd);
        document.getElementById('fontUrl').value = res.url;
        updatePreview();
        AlertModal.success('上传成功', '字体文件已上传，字体名称已自动识别');
    } catch(e) {}
    this.value = '';
});

// 从字体文件中读取font family name (nameID=1 or nameID=4)
async function readFontName(file) {
    try {
        const buf = await file.arrayBuffer();
        const view = new DataView(buf);
        // 检查是否为TrueType/OpenType
        const sig = view.getUint32(0);
        // 0x00010000=TrueType, 0x4F54544F='OTTO'=OpenType
        if (sig !== 0x00010000 && sig !== 0x4F54544F) {
            // 可能是WOFF
            const woffSig = view.getUint32(0);
            if (woffSig === 0x774F4646 || woffSig === 0x774F4632) {
                return null; // WOFF格式暂不解析，使用默认名
            }
            return null;
        }
        const numTables = view.getUint16(4);
        let nameOffset = 0, nameLength = 0;
        for (let i = 0; i < numTables; i++) {
            const tag = String.fromCharCode(
                view.getUint8(12 + i * 16),
                view.getUint8(13 + i * 16),
                view.getUint8(14 + i * 16),
                view.getUint8(15 + i * 16)
            );
            if (tag === 'name') {
                nameOffset = view.getUint32(12 + i * 16 + 8);
                nameLength = view.getUint32(12 + i * 16 + 12);
                break;
            }
        }
        if (!nameOffset) return null;
        const nameCount = view.getUint16(nameOffset + 2);
        const stringOffset = nameOffset + view.getUint16(nameOffset + 4);
        // 优先找 platformID=3 (Windows) nameID=4 (Full Name) 或 nameID=1 (Family)
        let found = null;
        for (let i = 0; i < nameCount; i++) {
            const rec = nameOffset + 6 + i * 12;
            const platformID = view.getUint16(rec);
            const nameID = view.getUint16(rec + 6);
            const length = view.getUint16(rec + 8);
            const offset = view.getUint16(rec + 10);
            if (nameID === 4 || nameID === 1) {
                const start = stringOffset + offset;
                let name = '';
                if (platformID === 3 || platformID === 0) {
                    // UTF-16 BE
                    for (let j = 0; j < length; j += 2) {
                        name += String.fromCharCode(view.getUint16(start + j));
                    }
                } else if (platformID === 1) {
                    // Mac Roman
                    for (let j = 0; j < length; j++) {
                        name += String.fromCharCode(view.getUint8(start + j));
                    }
                }
                if (name && name.trim()) {
                    found = name.trim();
                    if (nameID === 4) break; // Full Name优先
                }
            }
        }
        return found;
    } catch(e) {
        return null;
    }
}

async function save() {
    await API.post('/admin/api/settings.php', {
        settings: {
            font_family: document.getElementById('fontFamily').value.trim(),
            font_url: document.getElementById('fontUrl').value.trim(),
        }
    });
    AlertModal.success('保存成功', '字体设置已保存');
}

load();
</script>

<?php admin_footer(); ?>
