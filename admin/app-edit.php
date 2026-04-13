<?php
/**
 * 单个应用编辑页 - 下载按钮 + 轮播图管理
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/apps.php'); exit; }

admin_header('编辑应用', 'apps');
?>

<div class="page-header">
    <h1 id="pageTitle">编辑应用</h1>
    <a href="/admin/apps.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> 返回列表</a>
</div>

<!-- 基本信息 -->
<div class="card" id="appInfo">
    <h3>基本信息</h3>
    <div class="form-row">
        <div class="form-group">
            <label>应用标识</label>
            <input type="text" class="form-control" id="appSlug" readonly style="background:#f5f5f5;">
        </div>
        <div class="form-group">
            <label>应用名称</label>
            <input type="text" class="form-control" id="appName">
        </div>
        <div class="form-group">
            <label>图标</label>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px;">
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="iconType" value="fa" checked onchange="toggleIconMode('iconFaMode', 'iconImgMode', 'iconType')"> FA图标</label>
                <label style="margin:0;font-weight:400;cursor:pointer;"><input type="radio" name="iconType" value="image" onchange="toggleIconMode('iconFaMode', 'iconImgMode', 'iconType')"> 自定义图片</label>
            </div>
            <div id="iconFaMode">
                <div style="display:flex;gap:8px;align-items:center;">
                    <i id="appIconPreview" class="fas fa-tv" style="font-size:1.4em;width:32px;text-align:center;color:#666;"></i>
                    <input type="text" class="form-control" id="appIcon" placeholder="fas fa-tv" oninput="document.getElementById('appIconPreview').className=this.value||'fas fa-tv'" style="flex:1;">
                    <button class="btn btn-outline" type="button" onclick="IconPicker.open(cls => { document.getElementById('appIcon').value=cls; document.getElementById('appIconPreview').className=cls; })"><i class="fas fa-icons"></i> 选择</button>
                </div>
            </div>
            <div id="iconImgMode" style="display:none;">
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="iconPreview" src="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;border:1px solid #ddd;display:none;">
                    <button class="btn btn-outline" onclick="document.getElementById('iconUpload').click()"><i class="fas fa-upload"></i> 上传图标</button>
                    <button class="btn btn-outline" onclick="ImagePicker.open(url => { document.getElementById('appIconUrl').value = url; document.getElementById('iconPreview').src = '/' + url; document.getElementById('iconPreview').style.display = ''; })"><i class="fas fa-images"></i> 图片库</button>
                    <input type="file" id="iconUpload" accept="image/*" style="display:none;" onchange="uploadIconFile(this, 'appIconUrl', 'iconPreview', 'image')">
                    <input type="hidden" id="appIconUrl">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>主题色</label>
            <input type="color" class="form-control" id="appColor" style="height:42px;">
        </div>
    </div>
    <div class="form-group">
        <label>特色卡片分类</label>
        <select class="form-control" id="appFeatureCatId">
            <option value="0">无（使用全局特色卡片）</option>
        </select>
    </div>
    <button class="btn btn-primary" onclick="saveApp()"><i class="fas fa-save"></i> 保存基本信息</button>
</div>

<!-- iOS安装页配置 -->
<div class="card">
    <h3>iOS安装页配置</h3>
    <div style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #e2e8f0;">
        <button class="ios-tab active" id="tabIpa" onclick="switchIosTab('ipa')" style="padding:8px 20px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;">Plist配置</button>
        <button class="ios-tab" id="tabMc" onclick="switchIosTab('mc')" style="padding:8px 20px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;">Mobileconfig配置</button>
    </div>

    <!-- IPA配置面板 -->
    <div id="panelIpa">
        <p style="color:var(--text-secondary);margin-bottom:12px;font-size:0.9em;">配置后系统自动生成plist文件，用户可通过 <code style="color:#e53e3e;font-weight:700;">/ios/?app=应用标识</code> 访问iOS安装引导页</p>
        <div class="form-row">
            <div class="form-group">
                <label>IPA文件地址</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" class="form-control" id="iosIpaUrl" placeholder="如: https://example.com/app.ipa 或选择附件" style="flex:1;">
                    <button class="btn btn-outline btn-sm" type="button" onclick="showAttPicker('iosIpaUrl')" title="从附件选择"><i class="fas fa-paperclip"></i></button>
                </div>
                <select class="form-control att-picker" id="iosIpaUrlPicker" style="display:none;margin-top:6px;" onchange="pickAttachment(this,'iosIpaUrl')">
                    <option value="">-- 选择一个版本 --</option>
                </select>
            </div>
            <div class="form-group"><label>Bundle ID</label><input type="text" class="form-control" id="iosBundleId" placeholder="如: com.example.app"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>证书名称</label><input type="text" class="form-control" id="iosCert" placeholder="如: Etisalat - Emirates..."></div>
            <div class="form-group"><label>应用版本</label><input type="text" class="form-control" id="iosVersion" placeholder="如: 7.2.3"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>应用大小</label><input type="text" class="form-control" id="iosSize" placeholder="如: 4.5 MB"></div>
            <div class="form-group">
                <label>安装页模板</label>
                <select class="form-control" id="iosTemplate">
                    <option value="modern">现代风格（毛玻璃）</option>
                    <option value="classic">经典风格（仿App Store）</option>
                </select>
            </div>
        </div>
        <div class="form-group"><label>应用简介</label><textarea class="form-control" id="iosDesc" rows="3" placeholder="iOS安装页展示的应用描述"></textarea></div>
        <div id="plistPreview" style="display:none;margin-bottom:12px;">
            <label style="font-size:0.85em;color:var(--text-secondary);">Plist 安装链接：</label>
            <div style="background:#f5f5f5;padding:8px 12px;border-radius:6px;font-size:0.85em;word-break:break-all;font-family:monospace;" id="plistUrlDisplay"></div>
        </div>
        <button class="btn btn-primary" onclick="saveIosConfig()"><i class="fas fa-save"></i> 保存Plist配置</button>
    </div>

    <!-- Mobileconfig配置面板 -->
    <div id="panelMc" style="display:none;">

        <div class="form-group">
            <label>Mobileconfig 文件地址</label>
            <input type="hidden" id="mcFileId" value="">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="text" class="form-control" id="mcFileUrl" style="flex:1;min-width:200px;" placeholder="输入链接或点击按钮从附件库选择">
                <button class="btn btn-outline" type="button" onclick="showAttPicker('mcFileUrl')" title="从附件库选择"><i class="fas fa-paperclip"></i> 附件库</button>
                <button class="btn btn-outline" type="button" onclick="clearMcFile()"><i class="fas fa-times"></i></button>
            </div>
            <select class="form-control att-picker" id="mcFileUrlPicker" style="display:none;margin-top:6px;" onchange="pickAttachment(this,'mcFileUrl')">
                <option value="">-- 选择一个文件 --</option>
            </select>
            <small style="color:var(--text-secondary);font-size:0.8em;">可直接输入链接或从附件库选择（在"生成应用"页生成后关联到应用即可在附件库中找到）</small>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>安装页模板</label>
                <select class="form-control" id="mcTemplate">
                    <option value="modern">现代风格（毛玻璃）</option>
                    <option value="classic">经典风格（仿App Store）</option>
                </select>
            </div>
        </div>
        <div class="form-group"><label>应用简介</label><textarea class="form-control" id="mcDesc" rows="3" placeholder="描述文件的应用描述"></textarea></div>

        <div id="mcPreview" style="display:none;margin-bottom:12px;">
            <label style="font-size:0.85em;color:var(--text-secondary);">Mobileconfig 下载链接：</label>
            <div style="background:#f5f5f5;padding:8px 12px;border-radius:6px;font-size:0.85em;word-break:break-all;font-family:monospace;" id="mcUrlDisplay"></div>
        </div>
        <button class="btn btn-primary" onclick="saveMcConfig()"><i class="fas fa-save"></i> 保存Mobileconfig配置</button>
    </div>
</div>

<!-- Android安装页配置 -->
<div class="card">
    <h3>Android安装页配置</h3>
    <p style="color:var(--text-secondary);margin-bottom:12px;font-size:0.9em;">配置后用户可通过 <code style="color:#e53e3e;font-weight:700;">/android/?app=应用标识</code> 访问Android安装引导页</p>
    <div class="form-group">
        <label>APK文件地址</label>
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" class="form-control" id="androidApkUrl" placeholder="如: https://example.com/app.apk 或选择附件" style="flex:1;">
            <button class="btn btn-outline btn-sm" type="button" onclick="showAttPicker('androidApkUrl')" title="从附件选择"><i class="fas fa-paperclip"></i></button>
        </div>
        <select class="form-control att-picker" id="androidApkUrlPicker" style="display:none;margin-top:6px;" onchange="pickAttachment(this,'androidApkUrl')">
            <option value="">-- 选择一个版本 --</option>
        </select>
    </div>
    <div class="form-row">
        <div class="form-group"><label>应用版本</label><input type="text" class="form-control" id="androidVersion" placeholder="如: 1.2.0"></div>
        <div class="form-group"><label>应用大小</label><input type="text" class="form-control" id="androidSize" placeholder="如: 12.5 MB"></div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>安装页模板</label>
            <select class="form-control" id="androidTemplate">
                <option value="modern">现代风格</option>
                <option value="classic">经典风格（仿Play Store）</option>
            </select>
        </div>
    </div>
    <div class="form-group"><label>应用简介</label><textarea class="form-control" id="androidDesc" rows="3" placeholder="Android安装页展示的应用描述"></textarea></div>
    <div id="androidPreview" style="display:none;margin-bottom:12px;">
        <label style="font-size:0.85em;color:var(--text-secondary);">安装页链接：</label>
        <div style="background:#f5f5f5;padding:8px 12px;border-radius:6px;font-size:0.85em;word-break:break-all;font-family:monospace;" id="androidUrlDisplay"></div>
    </div>
    <button class="btn btn-primary" onclick="saveAndroidConfig()"><i class="fas fa-save"></i> 保存Android配置</button>
</div>

<!-- 下载按钮 -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;">下载按钮</h3>
        <button class="btn btn-primary btn-sm" onclick="openAddDlModal()"><i class="fas fa-plus"></i> 添加</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th></th><th>图标</th><th>类型</th><th>按钮文本</th><th>副标题</th><th>链接</th><th>操作</th></tr></thead>
            <tbody id="dlList"></tbody>
        </table>
    </div>
</div>

<!-- 轮播图 -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;">轮播图</h3>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-outline btn-sm" onclick="Modal.show('addImgUrlModal')"><i class="fas fa-link"></i> 添加URL</button>
            <button class="btn btn-outline btn-sm" onclick="ImagePicker.open(url => { addImageFromPicker(url); })"><i class="fas fa-images"></i> 从图片库</button>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('imgUpload').click()"><i class="fas fa-upload"></i> 上传图片</button>
            <input type="file" id="imgUpload" accept="image/*" multiple style="display:none;" onchange="uploadImages(this.files)">
        </div>
    </div>
    <div class="image-grid" id="imgGrid"></div>
</div>

<!-- 添加下载按钮模态框 -->
<div class="modal-overlay" id="addDlModal">
    <div class="modal">
        <h3>添加下载按钮</h3>
        <div class="form-group">
            <label>平台类型</label>
            <select class="form-control" id="dlType" onchange="onDlTypeChange('dlType','dlIcon','dlIconPreview')">
                <option value="android">Android 直接下载</option>
                <option value="android-install">Android 安装页</option>
                <option value="ios-ipa">iOS IPA 直接安装</option>
                <option value="ios-ipa-install">iOS IPA 安装页</option>
                <option value="ios-mobileconfig">iOS Mobileconfig 直接下载</option>
                <option value="ios-mobileconfig-install">iOS Mobileconfig 安装页</option>
                <option value="windows">Windows</option>
                <option value="web">Web</option>
                <option value="tv">TV</option>
                <option value="other">其他</option>
            </select>
        </div>
        <div class="form-group">
            <label>按钮图标 <small style="color:var(--text-secondary);">FA图标类名</small></label>
            <div style="display:flex;gap:8px;align-items:center;">
                <i id="dlIconPreview" class="fab fa-android" style="font-size:1.4em;width:28px;text-align:center;"></i>
                <input type="text" class="form-control" id="dlIcon" placeholder="fab fa-android" oninput="updateIconPreview(this.value,'dlIconPreview')" style="flex:1;">
                <button class="btn btn-outline" type="button" onclick="IconPicker.open(cls => { document.getElementById('dlIcon').value=cls; updateIconPreview(cls,'dlIconPreview'); })"><i class="fas fa-icons"></i> 选择</button>
            </div>
        </div>
        <div class="form-group"><label>按钮文本</label><input type="text" class="form-control" id="dlText" placeholder="如: Android"></div>
        <div class="form-group"><label>副标题</label><input type="text" class="form-control" id="dlSubtext" placeholder="如: 点击下载"></div>
        <div class="form-group">
            <label>下载链接</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-control" id="dlHref" placeholder="如: android/app.apk 或 https://..." style="flex:1;">
                <button class="btn btn-outline btn-sm" type="button" onclick="showAttPicker('dlHref')" title="从附件选择"><i class="fas fa-paperclip"></i> 选择附件</button>
            </div>
            <select class="form-control att-picker" id="dlHrefPicker" style="display:none;margin-top:6px;" onchange="pickAttachment(this,'dlHref')">
                <option value="">-- 选择一个版本 --</option>
            </select>
            <p id="dlHrefAutoHint" style="display:none;margin-top:6px;font-size:0.85em;color:var(--success, #38a169);"><i class="fas fa-check-circle"></i> 已为您自动填写iOS安装页地址</p>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('addDlModal')">取消</button>
            <button class="btn btn-primary" onclick="addDownload()">添加</button>
        </div>
    </div>
</div>

<!-- 编辑下载按钮模态框 -->
<div class="modal-overlay" id="editDlModal">
    <div class="modal">
        <h3>编辑下载按钮</h3>
        <input type="hidden" id="editDlId">
        <div class="form-group">
            <label>平台类型</label>
            <select class="form-control" id="editDlType" onchange="onDlTypeChange('editDlType','editDlIcon','editDlIconPreview')">
                <option value="android">Android 直接下载</option>
                <option value="android-install">Android 安装页</option>
                <option value="ios-ipa">iOS IPA 直接安装</option>
                <option value="ios-ipa-install">iOS IPA 安装页</option>
                <option value="ios-mobileconfig">iOS Mobileconfig 直接下载</option>
                <option value="ios-mobileconfig-install">iOS Mobileconfig 安装页</option>
                <option value="windows">Windows</option>
                <option value="web">Web</option>
                <option value="tv">TV</option>
                <option value="other">其他</option>
            </select>
        </div>
        <div class="form-group">
            <label>按钮图标 <small style="color:var(--text-secondary);">FA图标类名</small></label>
            <div style="display:flex;gap:8px;align-items:center;">
                <i id="editDlIconPreview" class="fab fa-android" style="font-size:1.4em;width:28px;text-align:center;"></i>
                <input type="text" class="form-control" id="editDlIcon" placeholder="fab fa-android" oninput="updateIconPreview(this.value,'editDlIconPreview')" style="flex:1;">
                <button class="btn btn-outline" type="button" onclick="IconPicker.open(cls => { document.getElementById('editDlIcon').value=cls; updateIconPreview(cls,'editDlIconPreview'); })"><i class="fas fa-icons"></i> 选择</button>
            </div>
        </div>
        <div class="form-group"><label>按钮文本</label><input type="text" class="form-control" id="editDlText" placeholder="如: Android"></div>
        <div class="form-group"><label>副标题</label><input type="text" class="form-control" id="editDlSubtext" placeholder="如: 点击下载"></div>
        <div class="form-group">
            <label>下载链接</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-control" id="editDlHref" placeholder="如: android/app.apk 或 https://..." style="flex:1;">
                <button class="btn btn-outline btn-sm" type="button" onclick="showAttPicker('editDlHref')" title="从附件选择"><i class="fas fa-paperclip"></i> 选择附件</button>
            </div>
            <select class="form-control att-picker" id="editDlHrefPicker" style="display:none;margin-top:6px;" onchange="pickAttachment(this,'editDlHref')">
                <option value="">-- 选择一个版本 --</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('editDlModal')">取消</button>
            <button class="btn btn-primary" onclick="saveEditDownload()">保存</button>
        </div>
    </div>
</div>

<!-- 添加图片URL模态框 -->
<div class="modal-overlay" id="addImgUrlModal">
    <div class="modal">
        <h3>添加图片 (URL)</h3>
        <div class="form-group"><label>图片地址</label><input type="text" class="form-control" id="imgUrl" placeholder="如: img/app/1.webp 或 https://..."></div>
        <div class="form-group"><label>描述文本</label><input type="text" class="form-control" id="imgAlt" placeholder="如: 首页界面"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('addImgUrlModal')">取消</button>
            <button class="btn btn-primary" onclick="addImageUrl()">添加</button>
        </div>
    </div>
</div>

<script>
const APP_ID = <?= $id ?>;
let attachments = [];
let appSlug = '';

// 预定义类型→图标映射
const TYPE_ICON_MAP = {
    'android': 'fab fa-android',
    'android-install': 'fab fa-android',
    'ios-ipa': 'fab fa-apple',
    'ios-ipa-install': 'fab fa-apple',
    'ios-mobileconfig': 'fab fa-apple',
    'ios-mobileconfig-install': 'fab fa-apple',
    'ios': 'fab fa-apple',
    windows: 'fab fa-windows',
    web: 'fas fa-globe',
    tv: 'fas fa-tv',
    other: 'fas fa-download'
};

async function loadAttachments() {
    try {
        attachments = await API.get(`/admin/api/attachments.php?app_id=${APP_ID}`);
    } catch(e) { attachments = []; }
}

function buildAttOptions() {
    let html = '<option value="">-- 选择一个版本 --</option>';
    attachments.forEach(plat => {
        if (!plat.files || !plat.files.length) return;
        html += `<optgroup label="${escapeHTML(plat.name)}">`;
        plat.files.forEach(f => {
            html += `<option value="${escapeHTML(f.file_url)}">${escapeHTML(f.version)} (${escapeHTML(f.file_size)})</option>`;
        });
        html += '</optgroup>';
    });
    return html;
}

function showAttPicker(targetId) {
    const picker = document.getElementById(targetId + 'Picker');
    if (picker.style.display === 'none') {
        picker.innerHTML = buildAttOptions();
        picker.style.display = '';
        if (!attachments.length || !attachments.some(p => p.files && p.files.length)) {
            picker.innerHTML = '<option value="">暂无附件，请先在附件管理中上传</option>';
        }
    } else {
        picker.style.display = 'none';
    }
}

function pickAttachment(sel, targetId) {
    if (sel.value) {
        document.getElementById(targetId).value = sel.value;
        // 从附件库选择时清除旧的mc_file_id关联
        if (targetId === 'mcFileUrl') {
            document.getElementById('mcFileId').value = '';
            updateMcPreview();
        }
    }
    sel.style.display = 'none';
}

function updateIconPreview(val, previewId) {
    const el = document.getElementById(previewId);
    el.className = val || 'fas fa-download';
    el.style.fontSize = '1.4em';
    el.style.width = '28px';
    el.style.textAlign = 'center';
}

function onDlTypeChange(typeId, iconId, previewId) {
    const sel = document.getElementById(typeId);
    const type = sel.value;
    const defaultIcon = TYPE_ICON_MAP[type] || 'fas fa-download';
    document.getElementById(iconId).value = defaultIcon;
    updateIconPreview(defaultIcon, previewId);
    // 更新按钮文本placeholder提示
    const typeName = sel.options[sel.selectedIndex].text;
    const isAddModal = typeId === 'dlType';
    const textId = isAddModal ? 'dlText' : 'editDlText';
    document.getElementById(textId).placeholder = '如: ' + typeName;
    if (isAddModal) {
        const hrefInput = document.getElementById('dlHref');
        const hint = document.getElementById('dlHrefAutoHint');
        let autoUrl = '';
        let hintText = '';

        switch (type) {
            case 'android-install':
                autoUrl = '/android/?app=' + appSlug;
                hintText = '已自动填写Android安装页地址';
                break;
            case 'ios-ipa':
                if (appSlug) {
                    autoUrl = 'itms-services://?action=download-manifest&url=' +
                        encodeURIComponent(location.origin + '/api/plist.php?app=' + appSlug);
                    hintText = '已自动填写IPA安装链接';
                }
                break;
            case 'ios-ipa-install':
                if (appSlug) {
                    autoUrl = '/ios/?app=' + appSlug;
                    hintText = '已自动填写iOS IPA安装页地址';
                }
                break;
            case 'ios-mobileconfig':
                if (appSlug) {
                    autoUrl = '/api/mobileconfig.php?app=' + appSlug;
                    hintText = '已自动填写Mobileconfig下载地址';
                }
                break;
            case 'ios-mobileconfig-install':
                if (appSlug) {
                    autoUrl = '/ios/?app=' + appSlug + '&type=mobileconfig';
                    hintText = '已自动填写Mobileconfig安装页地址';
                }
                break;
        }

        if (autoUrl && appSlug) {
            hrefInput.value = autoUrl;
            if (hint) { hint.innerHTML = '<i class="fas fa-check-circle"></i> ' + hintText; hint.style.display = ''; }
        } else {
            // 切换到非自动填充类型时清除之前的自动填充链接
            const autoPatterns = ['/ios/?app=', '/android/?app=', '/api/mobileconfig.php', 'itms-services://'];
            if (autoPatterns.some(p => hrefInput.value.startsWith(p))) hrefInput.value = '';
            if (hint) hint.style.display = 'none';
        }
    }
}

function openAddDlModal() {
    document.getElementById('dlType').value = 'android';
    document.getElementById('dlIcon').value = 'fab fa-android';
    document.getElementById('dlText').value = '';
    document.getElementById('dlSubtext').value = '';
    document.getElementById('dlHref').value = '';
    document.getElementById('dlHrefPicker').style.display = 'none';
    const hint = document.getElementById('dlHrefAutoHint');
    if (hint) hint.style.display = 'none';
    updateIconPreview('fab fa-android', 'dlIconPreview');
    Modal.show('addDlModal');
}

async function loadApp() {
    // 加载特色卡片分类列表
    try {
        const cats = await API.get('/admin/api/features.php?action=categories');
        const sel = document.getElementById('appFeatureCatId');
        sel.innerHTML = '<option value="0">无（使用全局特色卡片）</option>' +
            cats.map(c => `<option value="${c.id}">${escapeHTML(c.name)}</option>`).join('');
    } catch(e) {}

    const app = await API.get(`/admin/api/apps.php?id=${APP_ID}`);
    appSlug = app.slug;
    document.getElementById('pageTitle').textContent = `编辑: ${app.name}`;
    document.getElementById('appSlug').value = app.slug;
    document.getElementById('appName').value = app.name;
    document.getElementById('appIcon').value = app.icon;
    document.getElementById('appIconPreview').className = app.icon || 'fas fa-tv';
    document.getElementById('appColor').value = app.theme_color;

    // 图标模式
    const iconUrl = app.icon_url || '';
    document.getElementById('appIconUrl').value = iconUrl;
    if (iconUrl) {
        document.querySelector('input[name="iconType"][value="image"]').checked = true;
        toggleIconMode('iconFaMode', 'iconImgMode', 'iconType');
        document.getElementById('iconPreview').src = '/' + iconUrl;
        document.getElementById('iconPreview').style.display = '';
    }

    // iOS配置
    document.getElementById('iosIpaUrl').value = app.ios_ipa_url || '';
    document.getElementById('iosBundleId').value = app.ios_bundle_id || '';
    document.getElementById('iosCert').value = app.ios_cert_name || '';
    document.getElementById('iosVersion').value = app.ios_version || '';
    document.getElementById('iosSize').value = app.ios_size || '';
    document.getElementById('iosDesc').value = app.ios_description || '';
    document.getElementById('iosTemplate').value = app.ios_template || 'modern';
    document.getElementById('appFeatureCatId').value = app.feature_category_id || 0;
    updatePlistPreview();

    // Mobileconfig配置
    document.getElementById('mcFileId').value = app.mc_file_id || '';
    document.getElementById('mcDesc').value = app.mc_description || '';
    document.getElementById('mcTemplate').value = app.mc_template || 'modern';
    if (app.mc_file_url) {
        document.getElementById('mcFileUrl').value = app.mc_file_url;
    } else if (app.mc_file_id) {
        try {
            const mcList = await API.get('/admin/api/mobileconfig.php?action=list');
            const mc = mcList.find(m => m.id == app.mc_file_id);
            if (mc) {
                document.getElementById('mcFileUrl').value = '/' + (mc.file_path || '');
            } else {
                document.getElementById('mcFileUrl').value = '(关联的文件已被删除)';
            }
        } catch(e) {
            document.getElementById('mcFileUrl').value = '(已关联 #' + app.mc_file_id + ')';
        }
    }
    updateMcPreview();

    // Android配置
    document.getElementById('androidApkUrl').value = app.android_apk_url || '';
    document.getElementById('androidVersion').value = app.android_version || '';
    document.getElementById('androidSize').value = app.android_size || '';
    document.getElementById('androidDesc').value = app.android_description || '';
    document.getElementById('androidTemplate').value = app.android_template || 'modern';
    updateAndroidPreview();

    renderDownloads(app.downloads);
    renderImages(app.images);
}

function updatePlistPreview() {
    const ipaUrl = document.getElementById('iosIpaUrl').value.trim();
    const previewDiv = document.getElementById('plistPreview');
    const urlDisplay = document.getElementById('plistUrlDisplay');
    if (ipaUrl && appSlug) {
        const plistUrl = location.origin + '/api/plist.php?app=' + appSlug;
        const installUrl = 'itms-services://?action=download-manifest&url=' + plistUrl;
        urlDisplay.textContent = installUrl;
        previewDiv.style.display = '';
    } else {
        previewDiv.style.display = 'none';
    }
}

function updateMcPreview() {
    const fileId = document.getElementById('mcFileId').value;
    const previewDiv = document.getElementById('mcPreview');
    const urlDisplay = document.getElementById('mcUrlDisplay');
    if ((fileId || document.getElementById('mcFileUrl').value) && appSlug) {
        urlDisplay.textContent = location.origin + '/api/mobileconfig.php?app=' + appSlug;
        previewDiv.style.display = '';
    } else {
        previewDiv.style.display = 'none';
    }
}

function updateAndroidPreview() {
    const apkUrl = document.getElementById('androidApkUrl').value.trim();
    const previewDiv = document.getElementById('androidPreview');
    const urlDisplay = document.getElementById('androidUrlDisplay');
    if (apkUrl && appSlug) {
        urlDisplay.textContent = location.origin + '/android/?app=' + appSlug;
        previewDiv.style.display = '';
    } else {
        previewDiv.style.display = 'none';
    }
}

function switchIosTab(tab) {
    document.getElementById('panelIpa').style.display = tab === 'ipa' ? '' : 'none';
    document.getElementById('panelMc').style.display = tab === 'mc' ? '' : 'none';
    document.getElementById('tabIpa').classList.toggle('active', tab === 'ipa');
    document.getElementById('tabMc').classList.toggle('active', tab === 'mc');
    document.getElementById('tabIpa').style.borderBottomColor = tab === 'ipa' ? 'var(--primary, #007AFF)' : 'transparent';
    document.getElementById('tabIpa').style.color = tab === 'ipa' ? 'var(--primary, #007AFF)' : 'var(--text-secondary)';
    document.getElementById('tabMc').style.borderBottomColor = tab === 'mc' ? 'var(--primary, #007AFF)' : 'transparent';
    document.getElementById('tabMc').style.color = tab === 'mc' ? 'var(--primary, #007AFF)' : 'var(--text-secondary)';
}
// 初始化选项卡样式
switchIosTab('ipa');

function clearMcFile() {
    document.getElementById('mcFileId').value = '';
    document.getElementById('mcFileUrl').value = '';
    updateMcPreview();
}

async function saveMcConfig() {
    const fileId = document.getElementById('mcFileId').value;
    const fileUrl = document.getElementById('mcFileUrl').value.trim();
    await API.put('/admin/api/apps.php', {
        id: APP_ID,
        mc_file_id: fileId ? parseInt(fileId) : null,
        mc_file_url: fileUrl,
        mc_description: document.getElementById('mcDesc').value.trim(),
        mc_template: document.getElementById('mcTemplate').value,
    });
    AlertModal.success('保存成功', 'Mobileconfig配置已保存');
    updateMcPreview();
}

async function saveAndroidConfig() {
    await API.put('/admin/api/apps.php', {
        id: APP_ID,
        android_apk_url: document.getElementById('androidApkUrl').value.trim(),
        android_version: document.getElementById('androidVersion').value.trim(),
        android_size: document.getElementById('androidSize').value.trim(),
        android_description: document.getElementById('androidDesc').value.trim(),
        android_template: document.getElementById('androidTemplate').value,
    });
    AlertModal.success('保存成功', 'Android安装页配置已保存');
    updateAndroidPreview();
}

async function saveApp() {
    const iconType = document.querySelector('input[name="iconType"]:checked').value;
    await API.put('/admin/api/apps.php', {
        id: APP_ID,
        name: document.getElementById('appName').value.trim(),
        icon: document.getElementById('appIcon').value.trim(),
        icon_url: iconType === 'image' ? document.getElementById('appIconUrl').value.trim() : '',
        theme_color: document.getElementById('appColor').value,
        feature_category_id: parseInt(document.getElementById('appFeatureCatId').value) || 0,
    });
    AlertModal.success('保存成功', '基本信息已保存');
}

async function saveIosConfig() {
    const ipaUrl = document.getElementById('iosIpaUrl').value.trim();
    // 自动生成plist链接
    let plistUrl = '';
    if (ipaUrl && appSlug) {
        plistUrl = 'itms-services://?action=download-manifest&url=' + encodeURIComponent(location.origin + '/api/plist.php?app=' + appSlug);
    }
    await API.put('/admin/api/apps.php', {
        id: APP_ID,
        ios_ipa_url: ipaUrl,
        ios_bundle_id: document.getElementById('iosBundleId').value.trim(),
        ios_plist_url: plistUrl,
        ios_cert_name: document.getElementById('iosCert').value.trim(),
        ios_version: document.getElementById('iosVersion').value.trim(),
        ios_size: document.getElementById('iosSize').value.trim(),
        ios_description: document.getElementById('iosDesc').value.trim(),
        ios_template: document.getElementById('iosTemplate').value,
    });
    AlertModal.success('保存成功', 'iOS配置已保存');
    updatePlistPreview();
}

// === 下载按钮 ===
let _downloads = [];
function renderDownloads(list) {
    _downloads = list;
    const body = document.getElementById('dlList');
    if (list.length === 0) {
        body.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-secondary);">暂无下载按钮</td></tr>';
        return;
    }
    body.innerHTML = list.map(d => {
        const icon = d.btn_icon || TYPE_ICON_MAP[d.btn_type] || 'fas fa-download';
        return `
        <tr data-id="${d.id}" draggable="true">
            <td><span class="drag-handle"><i class="fas fa-grip-vertical"></i></span></td>
            <td><i class="${escapeHTML(icon)}" style="font-size:1.2em;"></i></td>
            <td>${escapeHTML(d.btn_type)}</td>
            <td>${escapeHTML(d.btn_text)}</td>
            <td>${escapeHTML(d.btn_subtext)}</td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHTML(d.href)}">${escapeHTML(d.href)}</td>
            <td>
                <button class="btn btn-outline btn-sm" onclick="editDownload(${d.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="deleteDownload(${d.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        `;
    }).join('');

    initSortable(body, async (ids) => {
        await API.post('/admin/api/reorder.php', { table: 'app_downloads', order: ids });
        Toast.success('排序已保存');
    });
}

async function addDownload() {
    const btnType = document.getElementById('dlType').value;
    const btnIcon = document.getElementById('dlIcon').value.trim();
    await API.post('/admin/api/downloads.php', {
        app_id: APP_ID,
        btn_type: btnType,
        btn_icon: btnIcon,
        btn_text: document.getElementById('dlText').value.trim(),
        btn_subtext: document.getElementById('dlSubtext').value.trim(),
        href: document.getElementById('dlHref').value.trim() || '#',
    });
    AlertModal.success('添加成功', '下载按钮已添加');
    Modal.hide('addDlModal');
    loadApp();
}

function editDownload(id) {
    const d = _downloads.find(x => x.id === id);
    if (!d) return;
    document.getElementById('editDlId').value = d.id;
    // 如果btn_type不在预定义列表中，设为other
    const typeSelect = document.getElementById('editDlType');
    const knownTypes = [...typeSelect.options].map(o => o.value);
    typeSelect.value = knownTypes.includes(d.btn_type) ? d.btn_type : 'other';

    const icon = d.btn_icon || TYPE_ICON_MAP[d.btn_type] || 'fas fa-download';
    document.getElementById('editDlIcon').value = icon;
    updateIconPreview(icon, 'editDlIconPreview');

    document.getElementById('editDlText').value = d.btn_text;
    document.getElementById('editDlText').placeholder = '如: ' + typeSelect.options[typeSelect.selectedIndex].text;
    document.getElementById('editDlSubtext').value = d.btn_subtext;
    document.getElementById('editDlHref').value = d.href;
    document.getElementById('editDlHrefPicker').style.display = 'none';
    Modal.show('editDlModal');
}

async function saveEditDownload() {
    await API.put('/admin/api/downloads.php', {
        id: parseInt(document.getElementById('editDlId').value),
        btn_type: document.getElementById('editDlType').value,
        btn_icon: document.getElementById('editDlIcon').value.trim(),
        btn_text: document.getElementById('editDlText').value.trim(),
        btn_subtext: document.getElementById('editDlSubtext').value.trim(),
        href: document.getElementById('editDlHref').value.trim(),
        is_active: 1,
    });
    AlertModal.success('保存成功', '下载按钮已更新');
    Modal.hide('editDlModal');
    loadApp();
}

async function deleteDownload(id) {
    if (!await confirmAction('确定删除此下载按钮？')) return;
    await API.del('/admin/api/downloads.php', { id });
    Toast.success('已删除');
    loadApp();
}

// === 轮播图 ===
function renderImages(list) {
    const grid = document.getElementById('imgGrid');
    if (list.length === 0) {
        grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;"><i class="fas fa-images"></i><p>暂无轮播图</p></div>';
        return;
    }
    grid.innerHTML = list.map(img => `
        <div class="image-item" data-id="${img.id}">
            <img src="/${escapeHTML(img.image_url)}" alt="${escapeHTML(img.alt_text)}" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjM1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PC9zdmc+'">
            <div class="actions">
                <button onclick="deleteImage(${img.id})" title="删除"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `).join('');
}

async function uploadImages(files) {
    for (const file of files) {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('category', 'image');
        fd.append('_csrf', CSRF_TOKEN);

        try {
            const res = await API.upload('/admin/api/upload.php', fd);
            await API.post('/admin/api/images.php', {
                app_id: APP_ID,
                image_url: res.url,
                alt_text: file.name.replace(/\.[^.]+$/, ''),
            });
        } catch (e) { /* error already toasted */ }
    }
    Toast.success('上传完成');
    loadApp();
}

async function addImageUrl() {
    const url = document.getElementById('imgUrl').value.trim();
    const alt = document.getElementById('imgAlt').value.trim();
    if (!url) { Toast.error('图片地址不能为空'); return; }

    await API.post('/admin/api/images.php', { app_id: APP_ID, image_url: url, alt_text: alt });
    Toast.success('添加成功');
    Modal.hide('addImgUrlModal');
    loadApp();
}

async function addImageFromPicker(url) {
    await API.post('/admin/api/images.php', { app_id: APP_ID, image_url: url, alt_text: '' });
    Toast.success('已添加');
    loadApp();
}

async function deleteImage(id) {
    if (!await confirmAction('确定删除此图片？')) return;
    await API.del('/admin/api/images.php', { id });
    Toast.success('已删除');
    loadApp();
}

loadApp();
loadAttachments();
</script>

<?php admin_footer(); ?>
