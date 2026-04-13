<?php
/**
 * 生成应用 — Android(APK) / iOS(IPA占位 + Mobileconfig) 多平台管理
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('生成应用', 'generate');
?>

<div class="page-header">
    <h1>生成应用</h1>
</div>

<!-- 平台切换 -->
<div style="display:flex;gap:0;margin-bottom:20px;">
    <button id="platBtn_android" onclick="switchPlatform('android')" style="padding:10px 28px;border:2px solid var(--primary);background:var(--primary);color:#fff;cursor:pointer;font-size:0.95em;font-weight:600;border-radius:8px 0 0 8px;transition:all 0.2s;">
        <i class="fas fa-robot"></i> Android
    </button>
    <button id="platBtn_ios" onclick="switchPlatform('ios')" style="padding:10px 28px;border:2px solid var(--primary);background:transparent;color:var(--primary);cursor:pointer;font-size:0.95em;font-weight:600;border-radius:0 8px 8px 0;transition:all 0.2s;">
        <i class="fab fa-apple"></i> iOS
    </button>
</div>

<!-- ==================== Android 平台 ==================== -->
<div id="platform_android">

<!-- 选项卡 -->
<div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--border);">
    <button class="tab-btn active" onclick="switchTab('apks')" id="tabBtn_apks" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-box"></i> APK管理
    </button>
    <button class="tab-btn" onclick="switchTab('build')" id="tabBtn_build" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-hammer"></i> 生成新APK
    </button>
    <button class="tab-btn" onclick="switchTab('keys')" id="tabBtn_keys" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-key"></i> 签名密钥
    </button>
</div>

<!-- Tab 1: APK管理 -->
<div id="tab_apks">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;">已生成的APK</h3>
            <button class="btn btn-outline btn-sm" onclick="loadApks()"><i class="fas fa-sync-alt"></i> 刷新</button>
        </div>
        <div id="apkList" style="overflow-x:auto;">
            <p style="color:var(--text-secondary);text-align:center;padding:20px;">加载中...</p>
        </div>
    </div>
</div>

<!-- Tab 2: 生成新APK -->
<div id="tab_build" style="display:none;">
    <div class="card">
        <h3>基本信息</h3>
        <div class="form-row">
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 目标URL</label>
                <input type="url" class="form-control" id="buildUrl" placeholder="https://example.com">
            </div>
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 应用名称</label>
                <input type="text" class="form-control" id="buildAppName" placeholder="我的应用">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 包名</label>
                <input type="text" class="form-control" id="buildPackage" placeholder="com.example.myapp">
                <small style="color:var(--text-secondary);">格式: com.xxx.xxx，只能包含小写字母、数字和下划线</small>
            </div>
            <div class="form-group">
                <label>版本号</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" class="form-control" id="buildVersionName" value="1.0.0" placeholder="1.0.0" style="flex:2;">
                    <input type="number" class="form-control" id="buildVersionCode" value="1" min="1" placeholder="版本代码" style="flex:1;">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>图标与启动图</h3>
        <div class="form-row">
            <div class="form-group">
                <label>应用图标</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="buildIconPreview" src="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;border:1px solid var(--border);display:none;">
                    <button class="btn btn-outline" onclick="uploadBuildFile('icon')"><i class="fas fa-upload"></i> 上传图标</button>
                    <button class="btn btn-outline" onclick="ImagePicker.open(url => setBuildImage('icon', url))"><i class="fas fa-images"></i> 图片库</button>
                    <input type="file" id="buildIconFile" accept="image/*" style="display:none;" onchange="handleBuildUpload(this, 'icon')">
                    <input type="hidden" id="buildIconUrl">
                </div>
                <small style="color:var(--text-secondary);">推荐 512x512 PNG</small>
            </div>
            <div class="form-group">
                <label>启动图 (可选)</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="buildSplashPreview" src="" style="width:48px;height:80px;object-fit:cover;border:1px solid var(--border);border-radius:6px;display:none;">
                    <button class="btn btn-outline" onclick="uploadBuildFile('splash')"><i class="fas fa-upload"></i> 上传</button>
                    <button class="btn btn-outline" onclick="ImagePicker.open(url => setBuildImage('splash', url))"><i class="fas fa-images"></i> 图片库</button>
                    <input type="file" id="buildSplashFile" accept="image/*" style="display:none;" onchange="handleBuildUpload(this, 'splash')">
                    <input type="hidden" id="buildSplashUrl">
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>启动背景色</label>
                <input type="color" class="form-control" id="buildSplashColor" value="#FFFFFF" style="height:42px;">
            </div>
            <div class="form-group">
                <label>状态栏颜色</label>
                <input type="color" class="form-control" id="buildStatusBarColor" value="#000000" style="height:42px;">
            </div>
        </div>
    </div>

    <div class="card">
        <h3>签名密钥</h3>
        <div class="form-group">
            <label><span style="color:#e74c3c;">*</span> 选择签名密钥</label>
            <div style="display:flex;gap:8px;">
                <select class="form-control" id="buildKeystoreId" style="flex:1;">
                    <option value="">-- 请先创建或导入密钥 --</option>
                </select>
                <button class="btn btn-outline" onclick="switchTab('keys')" title="管理密钥"><i class="fas fa-cog"></i></button>
            </div>
        </div>
    </div>

    <div style="text-align:center;margin:20px 0;">
        <button class="btn btn-primary" id="buildStartBtn" onclick="startBuild()" style="padding:12px 48px;font-size:1.05em;">
            <i class="fas fa-hammer"></i> 开始生成APK
        </button>
    </div>

    <div class="card" id="buildProgressCard" style="display:none;">
        <h3>构建进度</h3>
        <div style="position:relative;background:var(--border);border-radius:8px;height:24px;overflow:hidden;margin-bottom:12px;">
            <div id="buildProgressBar" style="height:100%;background:var(--primary);transition:width 0.5s;width:0%;border-radius:8px;"></div>
            <span id="buildProgressPercent" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-size:0.8em;font-weight:600;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,0.3);">0%</span>
        </div>
        <p id="buildProgressMsg" style="text-align:center;color:var(--text-secondary);margin:0;">等待中...</p>
        <div id="buildResult" style="display:none;margin-top:16px;padding:16px;border-radius:8px;text-align:center;"></div>
    </div>
</div>

<!-- Tab 3: 签名密钥管理 -->
<div id="tab_keys" style="display:none;">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;">签名密钥列表</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="showGenerateKeyModal()"><i class="fas fa-plus"></i> 生成新密钥</button>
                <button class="btn btn-outline btn-sm" onclick="showUploadKeyModal()"><i class="fas fa-upload"></i> 导入密钥</button>
            </div>
        </div>
        <div id="keyList" style="overflow-x:auto;">
            <p style="color:var(--text-secondary);text-align:center;padding:20px;">加载中...</p>
        </div>
    </div>
</div>

<!-- Android弹窗 -->
<div class="modal-overlay" id="generateKeyModal">
    <div class="modal" style="max-width:520px;">
        <h3>生成新签名密钥</h3>
        <div class="form-row">
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥名称</label><input type="text" class="form-control" id="gkName" placeholder="如: 正式签名"></div>
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 别名 (alias)</label><input type="text" class="form-control" id="gkAlias" placeholder="如: mykey"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥库密码</label><input type="password" class="form-control" id="gkStorePwd" placeholder="至少6位"></div>
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥密码</label><input type="password" class="form-control" id="gkKeyPwd" placeholder="至少6位"></div>
        </div>
        <div class="form-group"><label>有效期 (年)</label><input type="number" class="form-control" id="gkValidity" value="25" min="1" max="100"></div>
        <details style="margin-bottom:12px;">
            <summary style="cursor:pointer;font-size:0.9em;color:var(--text-secondary);">DN 信息（可选）</summary>
            <div style="margin-top:8px;">
                <div class="form-row">
                    <div class="form-group"><label>通用名称 (CN)</label><input type="text" class="form-control" id="gkCN" placeholder="如: My Company"></div>
                    <div class="form-group"><label>组织 (O)</label><input type="text" class="form-control" id="gkOrg" placeholder="如: My Corp"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>部门 (OU)</label><input type="text" class="form-control" id="gkOU"></div>
                    <div class="form-group"><label>国家代码 (C)</label><input type="text" class="form-control" id="gkCountry" placeholder="如: CN" maxlength="2"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>省份 (ST)</label><input type="text" class="form-control" id="gkState"></div>
                    <div class="form-group"><label>城市 (L)</label><input type="text" class="form-control" id="gkCity"></div>
                </div>
            </div>
        </details>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('generateKeyModal')">取消</button>
            <button class="btn btn-primary" id="gkSubmitBtn" onclick="doGenerateKey()">生成</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="uploadKeyModal">
    <div class="modal" style="max-width:480px;">
        <h3>导入签名密钥</h3>
        <div class="form-group">
            <label><span style="color:#e74c3c;">*</span> Keystore文件</label>
            <div style="display:flex;gap:8px;">
                <input type="text" class="form-control" id="ukFileName" readonly placeholder="选择 .jks / .keystore / .p12 文件" style="flex:1;">
                <button class="btn btn-outline" onclick="document.getElementById('ukFileInput').click()"><i class="fas fa-folder-open"></i></button>
                <input type="file" id="ukFileInput" accept=".jks,.keystore,.p12,.pfx,.bks" style="display:none;" onchange="document.getElementById('ukFileName').value=this.files[0]?.name||''">
            </div>
        </div>
        <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥名称</label><input type="text" class="form-control" id="ukName" placeholder="如: 正式签名"></div>
        <div class="form-group"><label><span style="color:#e74c3c;">*</span> 别名 (alias)</label><input type="text" class="form-control" id="ukAlias"></div>
        <div class="form-row">
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥库密码</label><input type="password" class="form-control" id="ukStorePwd"></div>
            <div class="form-group"><label><span style="color:#e74c3c;">*</span> 密钥密码</label><input type="password" class="form-control" id="ukKeyPwd"></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('uploadKeyModal')">取消</button>
            <button class="btn btn-primary" id="ukSubmitBtn" onclick="doUploadKey()">导入</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="associateModal">
    <div class="modal" style="max-width:400px;">
        <h3>关联到应用</h3>
        <div class="form-group">
            <label>选择应用</label>
            <select class="form-control" id="assocAppId"><option value="">-- 不关联 --</option></select>
        </div>
        <input type="hidden" id="assocApkId">
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('associateModal')">取消</button>
            <button class="btn btn-primary" onclick="doAssociate()">保存</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="apkRenameModal">
    <div class="modal" style="max-width:480px;">
        <h3>重命名APK</h3>
        <p style="color:var(--danger);font-size:0.85em;margin-bottom:12px;"><i class="fas fa-exclamation-triangle"></i> 重命名会改变文件路径，已使用旧路径的下载链接将失效</p>
        <input type="hidden" id="apkRenameId">
        <div class="form-group"><label>当前文件名</label><input type="text" class="form-control" id="apkCurrentName" readonly></div>
        <div class="form-group"><label>新文件名</label><input type="text" class="form-control" id="apkNewName" placeholder="新文件名.apk"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('apkRenameModal')">取消</button>
            <button class="btn btn-primary" onclick="doApkRename()">确认重命名</button>
        </div>
    </div>
</div>

</div><!-- /platform_android -->

<!-- ==================== iOS 平台 ==================== -->
<div id="platform_ios" style="display:none;">

<!-- iOS 子选项卡 -->
<div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--border);">
    <button onclick="switchIosSubTab('ipa')" id="iosTabBtn_ipa" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--primary);border-bottom:2px solid var(--primary);margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-cube"></i> IPA
    </button>
    <button onclick="switchIosSubTab('mc_list')" id="iosTabBtn_mc_list" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-file-alt"></i> Mobileconfig管理
    </button>
    <button onclick="switchIosSubTab('mc_build')" id="iosTabBtn_mc_build" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-magic"></i> 生成新Mobileconfig
    </button>
    <button onclick="switchIosSubTab('mc_certs')" id="iosTabBtn_mc_certs" style="padding:10px 24px;border:none;background:none;cursor:pointer;font-size:0.95em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all 0.2s;">
        <i class="fas fa-certificate"></i> 证书管理
    </button>
</div>

<!-- iOS Tab: IPA -->
<div id="ios_tab_ipa" style="display:none;">
<!-- IPA 子选项卡 -->
<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:1px solid var(--border);">
    <button onclick="switchIpaSubTab('ipa_list')" id="ipaSubBtn_ipa_list" style="padding:8px 20px;border:none;background:none;cursor:pointer;font-size:0.9em;font-weight:600;color:var(--primary);border-bottom:2px solid var(--primary);margin-bottom:-1px;">
        <i class="fas fa-box"></i> IPA管理
    </button>
    <button onclick="switchIpaSubTab('ipa_build')" id="ipaSubBtn_ipa_build" style="padding:8px 20px;border:none;background:none;cursor:pointer;font-size:0.9em;font-weight:600;color:var(--text-secondary);border-bottom:2px solid transparent;margin-bottom:-1px;">
        <i class="fas fa-hammer"></i> 生成新IPA
    </button>
</div>

<!-- IPA列表 -->
<div id="ipa_sub_ipa_list">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;">已生成的IPA</h3>
            <button class="btn btn-outline btn-sm" onclick="loadIpas()"><i class="fas fa-sync-alt"></i> 刷新</button>
        </div>
        <div id="ipaList" style="overflow-x:auto;">
            <p style="color:var(--text-secondary);text-align:center;padding:20px;">加载中...</p>
        </div>
    </div>
</div>

<!-- 生成新IPA -->
<div id="ipa_sub_ipa_build" style="display:none;">
    <div class="card">
        <h3>基本信息</h3>
        <div class="form-row">
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 目标URL</label>
                <input type="url" class="form-control" id="ipaBuildUrl" placeholder="https://example.com">
            </div>
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 应用名称</label>
                <input type="text" class="form-control" id="ipaBuildAppName" placeholder="我的应用">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> Bundle ID</label>
                <input type="text" class="form-control" id="ipaBuildBundleId" placeholder="com.example.myapp">
                <small style="color:var(--text-secondary);">反向域名格式</small>
            </div>
            <div class="form-group">
                <label>版本号</label>
                <input type="text" class="form-control" id="ipaBuildVersion" value="1.0.0">
            </div>
        </div>
    </div>
    <div class="card">
        <h3>外观设置</h3>
        <div class="form-row">
            <div class="form-group">
                <label>应用图标</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <img id="ipaIconPreview" src="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;border:1px solid #ddd;display:none;">
                    <button class="btn btn-outline" onclick="uploadIpaIcon()"><i class="fas fa-upload"></i> 上传图标</button>
                    <button class="btn btn-outline" onclick="ImagePicker.open(url => { document.getElementById('ipaBuildIconUrl').value = url; const p = document.getElementById('ipaIconPreview'); p.src = '/' + url; p.style.display = ''; })"><i class="fas fa-images"></i> 图片库</button>
                    <input type="hidden" id="ipaBuildIconUrl">
                    <input type="file" id="ipaIconUpload" accept="image/png,image/jpeg" style="display:none;">
                </div>
            </div>
            <div class="form-group">
                <label>状态栏颜色</label>
                <input type="color" class="form-control" id="ipaBuildStatusBarColor" value="#000000" style="height:42px;">
            </div>
        </div>
    </div>
    <div class="card" style="background:#f0f9ff;border:1px solid #bae6fd;">
        <p style="margin:0;color:#0369a1;font-size:0.9em;">
            <i class="fas fa-info-circle"></i>
            IPA 将以无签名模式构建（CODE_SIGNING_ALLOWED=NO）。构建需要 Docker-OSX 环境就绪，
            请先在「系统信息」页面完成 iOS 环境安装。
        </p>
    </div>
    <div style="text-align:center;margin:20px 0;">
        <button class="btn btn-primary" id="ipaBuildBtn" onclick="startIpaBuild()" style="padding:12px 48px;font-size:1.05em;">
            <i class="fas fa-hammer"></i> 开始构建 IPA
        </button>
    </div>
    <div id="ipaBuildProgress" style="display:none;">
        <div class="card">
            <h3>构建进度</h3>
            <div style="background:var(--border);border-radius:8px;overflow:hidden;height:24px;margin-bottom:12px;">
                <div id="ipaBuildProgressBar" style="background:var(--primary);height:100%;width:0%;transition:width 0.5s;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                    <span style="color:#fff;font-size:0.75em;font-weight:600;" id="ipaBuildProgressPct">0%</span>
                </div>
            </div>
            <p id="ipaBuildProgressMsg" style="color:var(--text-secondary);font-size:0.9em;text-align:center;margin:0;">等待构建...</p>
        </div>
    </div>
</div>
</div>

<!-- IPA 弹窗 -->
<div class="modal-overlay" id="ipaAssociateModal">
    <div class="modal" style="max-width:400px;">
        <h3>关联到应用</h3>
        <div class="form-group">
            <label>选择应用</label>
            <select class="form-control" id="ipaAssocAppId"><option value="">-- 不关联 --</option></select>
        </div>
        <input type="hidden" id="ipaAssocIpaId">
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('ipaAssociateModal')">取消</button>
            <button class="btn btn-primary" onclick="doIpaAssociate()">保存</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="ipaRenameModal">
    <div class="modal" style="max-width:480px;">
        <h3>重命名IPA</h3>
        <p style="color:var(--danger);font-size:0.85em;margin-bottom:12px;"><i class="fas fa-exclamation-triangle"></i> 重命名会改变文件路径，已使用旧路径的链接将失效</p>
        <input type="hidden" id="ipaRenameId">
        <div class="form-group"><label>当前文件名</label><input type="text" class="form-control" id="ipaCurrentName" readonly></div>
        <div class="form-group"><label>新文件名</label><input type="text" class="form-control" id="ipaNewName" placeholder="新文件名.ipa"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('ipaRenameModal')">取消</button>
            <button class="btn btn-primary" onclick="doIpaRename()">确认重命名</button>
        </div>
    </div>
</div>

<!-- iOS Tab: Mobileconfig管理 -->
<div id="ios_tab_mc_list" style="display:none;">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;">已生成的Mobileconfig</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="switchIosSubTab('mc_build')"><i class="fas fa-plus"></i> 生成新文件</button>
                <button class="btn btn-outline btn-sm" onclick="loadMcList()"><i class="fas fa-sync-alt"></i> 刷新</button>
            </div>
        </div>
        <div id="mcFileList" style="overflow-x:auto;">
            <p style="color:var(--text-secondary);text-align:center;padding:20px;">加载中...</p>
        </div>
    </div>
</div>

<!-- iOS Tab: 生成新Mobileconfig -->
<div id="ios_tab_mc_build" style="display:none;">
    <div class="card">
        <h3>基本信息</h3>
        <div class="form-row">
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 显示名称</label>
                <input type="text" class="form-control" id="mcBuildName" placeholder="应用名称（显示在主屏幕）">
            </div>
            <div class="form-group">
                <label><span style="color:#e74c3c;">*</span> 目标URL</label>
                <input type="url" class="form-control" id="mcBuildUrl" placeholder="https://example.com/webapp">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Bundle ID</label>
                <input type="text" class="form-control" id="mcBuildBundleId" placeholder="com.webclip.appname（留空自动生成）">
            </div>
            <div class="form-group">
                <label>版本</label>
                <input type="text" class="form-control" id="mcBuildVersion" value="1">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>全屏模式</label>
                <select class="form-control" id="mcBuildFullscreen">
                    <option value="1">是（全屏显示，隐藏地址栏）</option>
                    <option value="0">否（保留地址栏）</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>图标与描述</h3>
        <div class="form-group">
            <label>WebClip图标 <small style="color:var(--text-secondary);">会嵌入到描述文件中</small></label>
            <div style="display:flex;gap:8px;align-items:center;">
                <img id="mcBuildIconPreview" src="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;border:1px solid #ddd;display:none;">
                <button class="btn btn-outline" onclick="document.getElementById('mcBuildIconUpload').click()"><i class="fas fa-upload"></i> 上传图标</button>
                <button class="btn btn-outline" onclick="ImagePicker.open(url => pickMcIconFromLib(url, 'mcBuildIconPreview', 'mcBuildIconData'))"><i class="fas fa-images"></i> 图片库</button>
                <input type="file" id="mcBuildIconUpload" accept="image/png,image/jpeg" style="display:none;" onchange="uploadMcIconFile(this, 'mcBuildIconPreview', 'mcBuildIconData')">
                <input type="hidden" id="mcBuildIconData">
                <span id="mcBuildIconStatus" style="font-size:0.85em;color:var(--text-secondary);"></span>
            </div>
        </div>
        <div class="form-group"><label>应用简介</label><textarea class="form-control" id="mcBuildDesc" rows="3" placeholder="描述文件的应用描述"></textarea></div>
    </div>

    <div class="card">
        <h3>签名证书</h3>
        <div class="form-row">
            <div class="form-group">
                <label>选择证书</label>
                <div style="display:flex;gap:8px;">
                    <select class="form-control" id="mcBuildCertId" style="flex:1;">
                        <option value="">-- 自动使用全局证书 --</option>
                    </select>
                    <button class="btn btn-outline" onclick="switchIosSubTab('mc_certs')" title="管理证书"><i class="fas fa-cog"></i></button>
                </div>
            </div>
            <div class="form-group">
                <label>组织名称 <small style="color:var(--text-secondary);">覆盖证书默认值</small></label>
                <input type="text" class="form-control" id="mcBuildPayloadOrg" placeholder="留空使用证书设置">
            </div>
        </div>
    </div>

    <div style="text-align:center;margin:20px 0;">
        <button class="btn btn-primary" id="mcBuildBtn" onclick="generateMobileconfig()" style="padding:12px 48px;font-size:1.05em;">
            <i class="fas fa-magic"></i> 生成Mobileconfig
        </button>
    </div>
</div>

<!-- iOS Tab: 证书管理 -->
<div id="ios_tab_mc_certs" style="display:none;">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;">签名证书列表</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="showAddCertModal()"><i class="fas fa-plus"></i> 新建证书</button>
            </div>
        </div>
        <p style="color:var(--text-secondary);font-size:0.85em;margin-bottom:12px;">设置为"全局默认"的证书将自动应用于未指定证书的Mobileconfig文件。</p>
        <div id="mcCertList" style="overflow-x:auto;">
            <p style="color:var(--text-secondary);text-align:center;padding:20px;">加载中...</p>
        </div>
    </div>
</div>

<!-- iOS 弹窗 -->
<div class="modal-overlay" id="mcAssociateModal">
    <div class="modal" style="max-width:400px;">
        <h3>关联到附件库</h3>
        <p style="font-size:0.85em;color:var(--text-secondary);margin-bottom:12px;">将Mobileconfig文件添加到应用的附件分类中</p>
        <div class="form-group">
            <label>选择应用</label>
            <select class="form-control" id="mcAssocAppId" onchange="loadMcAssocPlatforms()"><option value="">-- 请选择 --</option></select>
        </div>
        <div class="form-group">
            <label>附件分类</label>
            <select class="form-control" id="mcAssocPlatformId"><option value="">-- 先选择应用 --</option></select>
        </div>
        <div class="form-group">
            <label>版本号</label>
            <input type="text" class="form-control" id="mcAssocVersion" value="1.0" placeholder="如: 1.0">
        </div>
        <input type="hidden" id="mcAssocMcId">
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('mcAssociateModal')">取消</button>
            <button class="btn btn-primary" onclick="doMcAssociate()">关联</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="mcRenameModal">
    <div class="modal" style="max-width:480px;">
        <h3>重命名Mobileconfig</h3>
        <p style="color:var(--danger);font-size:0.85em;margin-bottom:12px;"><i class="fas fa-exclamation-triangle"></i> 重命名会改变文件路径，已使用旧路径的链接将失效</p>
        <input type="hidden" id="mcRenameId">
        <div class="form-group"><label>当前文件名</label><input type="text" class="form-control" id="mcCurrentName" readonly></div>
        <div class="form-group"><label>新文件名</label><input type="text" class="form-control" id="mcNewName" placeholder="新文件名.mobileconfig"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('mcRenameModal')">取消</button>
            <button class="btn btn-primary" onclick="doMcRename()">确认重命名</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="mcEditModal">
    <div class="modal" style="max-width:600px;max-height:85vh;overflow-y:auto;">
        <h3>编辑Mobileconfig</h3>
        <p style="color:var(--text-secondary);font-size:0.85em;margin-bottom:12px;">修改参数后将重新生成文件，关联信息保持不变。</p>
        <input type="hidden" id="mcEditId">
        <div class="form-row">
            <div class="form-group"><label>显示名称</label><input type="text" class="form-control" id="mcEditName"></div>
            <div class="form-group"><label>目标URL</label><input type="url" class="form-control" id="mcEditUrl"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Bundle ID</label><input type="text" class="form-control" id="mcEditBundleId"></div>
            <div class="form-group"><label>版本</label><input type="text" class="form-control" id="mcEditVersion"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>全屏模式</label>
                <select class="form-control" id="mcEditFullscreen"><option value="1">是</option><option value="0">否</option></select>
            </div>
        </div>
        <div class="form-group">
            <label>WebClip图标</label>
            <div style="display:flex;gap:8px;align-items:center;">
                <img id="mcEditIconPreview" src="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;border:1px solid #ddd;display:none;">
                <button class="btn btn-outline btn-sm" onclick="document.getElementById('mcEditIconUpload').click()"><i class="fas fa-upload"></i> 上传</button>
                <button class="btn btn-outline btn-sm" onclick="ImagePicker.open(url => pickMcIconFromLib(url, 'mcEditIconPreview', 'mcEditIconData'))"><i class="fas fa-images"></i> 图片库</button>
                <input type="file" id="mcEditIconUpload" accept="image/png,image/jpeg" style="display:none;" onchange="uploadMcIconFile(this, 'mcEditIconPreview', 'mcEditIconData')">
                <input type="hidden" id="mcEditIconData">
            </div>
        </div>
        <div class="form-group"><label>应用简介</label><textarea class="form-control" id="mcEditDesc" rows="2"></textarea></div>
        <div class="form-row">
            <div class="form-group">
                <label>证书</label>
                <select class="form-control" id="mcEditCertId"><option value="">-- 自动使用全局证书 --</option></select>
            </div>
            <div class="form-group"><label>组织名称</label><input type="text" class="form-control" id="mcEditPayloadOrg"></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('mcEditModal')">取消</button>
            <button class="btn btn-primary" id="mcEditSubmitBtn" onclick="updateMobileconfig()"><i class="fas fa-save"></i> 保存并重新生成</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addCertModal">
    <div class="modal" style="max-width:560px;max-height:85vh;overflow-y:auto;">
        <h3 id="addCertModalTitle">新建签名证书</h3>
        <input type="hidden" id="editCertId">
        <div class="form-group"><label><span style="color:#e74c3c;">*</span> 证书名称</label><input type="text" class="form-control" id="certName" placeholder="如: 正式证书"></div>
        <div class="form-group"><label>组织名称</label><input type="text" class="form-control" id="certPayloadOrg" placeholder="显示在描述文件中"></div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="certIsGlobal" style="accent-color:var(--primary);width:16px;height:16px;">
                设为全局默认证书
            </label>
        </div>
        <div class="form-group">
            <label>证书输入方式</label>
            <select class="form-control" id="certMode" onchange="toggleCertMode()">
                <option value="text">文本粘贴</option>
                <option value="path">服务器路径</option>
                <option value="upload">文件上传</option>
            </select>
        </div>
        <div id="certModeText">
            <div class="form-group"><label>签名证书 (PEM)</label><textarea class="form-control" id="certCertText" rows="3" style="font-family:monospace;font-size:0.85em;" placeholder="-----BEGIN CERTIFICATE-----"></textarea></div>
            <div class="form-group"><label>私钥 (PEM)</label><textarea class="form-control" id="certKeyText" rows="3" style="font-family:monospace;font-size:0.85em;" placeholder="-----BEGIN PRIVATE KEY-----"></textarea></div>
            <div class="form-group"><label>证书链 (PEM，可选)</label><textarea class="form-control" id="certChainText" rows="2" style="font-family:monospace;font-size:0.85em;" placeholder="中间证书"></textarea></div>
        </div>
        <div id="certModePath" style="display:none;">
            <div class="form-group"><label>证书文件路径</label><input type="text" class="form-control" id="certCertPath" placeholder="/etc/ssl/certs/cert.pem"></div>
            <div class="form-group"><label>私钥文件路径</label><input type="text" class="form-control" id="certKeyPath" placeholder="/etc/ssl/private/key.pem"></div>
            <div class="form-group"><label>证书链路径 (可选)</label><input type="text" class="form-control" id="certChainPath" placeholder="/etc/ssl/certs/chain.pem"></div>
        </div>
        <div id="certModeUpload" style="display:none;">
            <div class="form-group"><label>签名证书</label><div style="display:flex;gap:8px;"><input type="text" class="form-control" id="certCertUpload" style="flex:1;" readonly placeholder="点击上传"><button class="btn btn-outline" onclick="CertUploader.upload('certCertUpload')"><i class="fas fa-upload"></i></button></div></div>
            <div class="form-group"><label>私钥</label><div style="display:flex;gap:8px;"><input type="text" class="form-control" id="certKeyUpload" style="flex:1;" readonly placeholder="点击上传"><button class="btn btn-outline" onclick="CertUploader.upload('certKeyUpload')"><i class="fas fa-upload"></i></button></div></div>
            <div class="form-group"><label>证书链 (可选)</label><div style="display:flex;gap:8px;"><input type="text" class="form-control" id="certChainUpload" style="flex:1;" readonly placeholder="点击上传"><button class="btn btn-outline" onclick="CertUploader.upload('certChainUpload')"><i class="fas fa-upload"></i></button></div></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" onclick="Modal.hide('addCertModal')">取消</button>
            <button class="btn btn-primary" id="certSubmitBtn" onclick="submitCert()">保存</button>
        </div>
    </div>
</div>

</div><!-- /platform_ios -->

<script>
// ===== 平台切换 =====
let currentPlatform = 'android';
function switchPlatform(plat) {
    document.getElementById('platform_android').style.display = plat === 'android' ? '' : 'none';
    document.getElementById('platform_ios').style.display = plat === 'ios' ? '' : 'none';
    document.getElementById('platBtn_android').style.background = plat === 'android' ? 'var(--primary)' : 'transparent';
    document.getElementById('platBtn_android').style.color = plat === 'android' ? '#fff' : 'var(--primary)';
    document.getElementById('platBtn_ios').style.background = plat === 'ios' ? 'var(--primary)' : 'transparent';
    document.getElementById('platBtn_ios').style.color = plat === 'ios' ? '#fff' : 'var(--primary)';
    currentPlatform = plat;
    if (plat === 'android') { switchTab('apks'); }
    if (plat === 'ios') { switchIosSubTab('ipa'); }
}

// ===== iOS子Tab切换 =====
function switchIosSubTab(tab) {
    ['ipa', 'mc_list', 'mc_build', 'mc_certs'].forEach(t => {
        const el = document.getElementById('ios_tab_' + t);
        if (el) el.style.display = t === tab ? '' : 'none';
        const btn = document.getElementById('iosTabBtn_' + t);
        if (btn) {
            btn.style.color = t === tab ? 'var(--primary)' : 'var(--text-secondary)';
            btn.style.borderBottomColor = t === tab ? 'var(--primary)' : 'transparent';
        }
    });
    if (tab === 'ipa') loadIpas();
    if (tab === 'mc_list') loadMcList();
    if (tab === 'mc_certs') loadMcCerts();
    if (tab === 'mc_build') loadMcCertSelect();
}

// ===== Android选项卡切换 =====
let currentTab = 'apks';
function switchTab(tab) {
    ['apks', 'build', 'keys'].forEach(t => {
        document.getElementById('tab_' + t).style.display = t === tab ? '' : 'none';
        const btn = document.getElementById('tabBtn_' + t);
        btn.style.color = t === tab ? 'var(--primary)' : 'var(--text-secondary)';
        btn.style.borderBottomColor = t === tab ? 'var(--primary)' : 'transparent';
    });
    currentTab = tab;
    if (tab === 'apks') loadApks();
    if (tab === 'keys') { loadKeys(); loadKeystoreSelect(); }
    if (tab === 'build') loadKeystoreSelect();
}

// ===== 通用：加载应用列表 =====
let allApps = [];
async function loadApps() {
    try { allApps = await API.get('/admin/api/apps.php'); } catch(e) { allApps = []; }
}

// ===== APK列表 =====
async function loadApks() {
    const el = document.getElementById('apkList');
    try {
        const [rows, tasks] = await Promise.all([
            API.get('/admin/api/generate.php?action=list_apks'),
            API.get('/admin/api/generate.php?action=list_tasks'),
        ]);
        const runningTasks = tasks.filter(t => t.status === 'pending' || t.status === 'building');

        if (!rows.length && !runningTasks.length) {
            el.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px;">暂无生成记录</p>';
            return;
        }
        let html = '<table class="data-table"><thead><tr>' +
            '<th>应用名</th><th>包名</th><th>版本</th><th>URL</th><th>大小</th><th>签名密钥</th><th>关联应用</th><th>创建时间</th><th>操作</th>' +
            '</tr></thead><tbody>';

        for (const t of runningTasks) {
            const statusLabel = t.status === 'building'
                ? `<span style="background:#f59e0b;color:#fff;padding:2px 8px;border-radius:4px;font-size:0.8em;"><i class="fas fa-spinner fa-spin"></i> 构建中 ${t.progress}%</span>`
                : `<span style="background:#6366f1;color:#fff;padding:2px 8px;border-radius:4px;font-size:0.8em;"><i class="fas fa-clock"></i> 等待中</span>`;
            html += `<tr style="background:#fffbeb;">
                <td>${escapeHTML(t.app_name || '-')}</td>
                <td><code style="font-size:0.8em;">${escapeHTML(t.package_name || '-')}</code></td>
                <td>-</td><td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${statusLabel}</td>
                <td>${escapeHTML(t.progress_msg || '')}</td><td>-</td><td>-</td>
                <td style="white-space:nowrap;">${t.created_at || ''}</td>
                <td style="white-space:nowrap;">
                    <button class="btn btn-outline btn-sm" onclick="cancelTask(${t.id})" title="取消" style="color:#e74c3c;"><i class="fas fa-stop-circle"></i> 取消</button>
                </td>
            </tr>`;
        }

        for (const r of rows) {
            const fname = r.apk_url ? r.apk_url.split('/').pop() : '';
            html += `<tr>
                <td>${escapeHTML(r.app_name)}</td>
                <td><code style="font-size:0.8em;">${escapeHTML(r.package_name)}</code></td>
                <td>${escapeHTML(r.version_name)} (${r.version_code})</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHTML(r.url)}">${escapeHTML(r.url)}</td>
                <td>${escapeHTML(r.apk_size)}</td>
                <td>${escapeHTML(r.keystore_name || '-')}</td>
                <td>${r.linked_app_name ? escapeHTML(r.linked_app_name) : '<span style="color:var(--text-secondary);">未关联</span>'}</td>
                <td style="white-space:nowrap;">${r.created_at || ''}</td>
                <td style="white-space:nowrap;">
                    ${r.apk_url ? `<a href="/${escapeHTML(r.apk_url)}" class="btn btn-outline btn-sm" download title="下载"><i class="fas fa-download"></i></a>` : ''}
                    <button class="btn btn-outline btn-sm" onclick="showApkRename(${r.id}, '${escapeHTML(fname)}')" title="重命名"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="showAssociate(${r.id})" title="关联应用"><i class="fas fa-link"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="deleteApk(${r.id})" title="删除" style="color:#e74c3c;"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        }
        html += '</tbody></table>';
        el.innerHTML = html;
    } catch(e) {
        el.innerHTML = '<p style="color:#e74c3c;text-align:center;padding:20px;">加载失败</p>';
    }
}

async function cancelTask(taskId) {
    if (!await AlertModal.confirm('确定要取消这个构建任务吗？')) return;
    try {
        await API.post('/admin/api/generate.php', { action: 'cancel_task', task_id: taskId });
        Toast.success('任务已取消');
        if (buildPolling) { clearInterval(buildPolling); buildPolling = null; }
        document.getElementById('buildStartBtn').disabled = false;
        document.getElementById('buildStartBtn').innerHTML = '<i class="fas fa-hammer"></i> 开始生成APK';
        document.getElementById('buildProgressCard').style.display = 'none';
        loadApks();
    } catch(e) {}
}

async function deleteApk(id) {
    if (!await confirmAction('确定要删除此APK及其文件？')) return;
    try { await API.del('/admin/api/generate.php', { id }); Toast.success('已删除'); loadApks(); } catch(e) {}
}

async function showAssociate(apkId) {
    document.getElementById('assocApkId').value = apkId;
    if (!allApps.length) await loadApps();
    const sel = document.getElementById('assocAppId');
    sel.innerHTML = '<option value="">-- 不关联 --</option>';
    for (const a of allApps) sel.innerHTML += `<option value="${a.id}">${escapeHTML(a.name)}</option>`;
    Modal.show('associateModal');
}

async function doAssociate() {
    const apkId = parseInt(document.getElementById('assocApkId').value);
    const appId = document.getElementById('assocAppId').value;
    try {
        await API.put('/admin/api/generate.php', { action: 'associate', apk_id: apkId, app_id: appId ? parseInt(appId) : null });
        Toast.success('关联已更新'); Modal.hide('associateModal'); loadApks();
    } catch(e) {}
}

// APK重命名
function showApkRename(id, currentName) {
    document.getElementById('apkRenameId').value = id;
    document.getElementById('apkCurrentName').value = currentName;
    document.getElementById('apkNewName').value = currentName;
    Modal.show('apkRenameModal');
}

async function doApkRename() {
    const id = parseInt(document.getElementById('apkRenameId').value);
    const newName = document.getElementById('apkNewName').value.trim();
    if (!newName) { Toast.error('请输入新文件名'); return; }
    try {
        await API.put('/admin/api/generate.php', { action: 'rename', apk_id: id, new_name: newName });
        Toast.success('重命名成功'); Modal.hide('apkRenameModal'); loadApks();
    } catch(e) {}
}

// ===== 构建 =====
let buildPolling = null;

function uploadBuildFile(type) {
    document.getElementById(type === 'icon' ? 'buildIconFile' : 'buildSplashFile').click();
}

async function handleBuildUpload(input, type) {
    if (!input.files[0]) return;
    const fd = new FormData(); fd.append('file', input.files[0]); fd.append('category', 'image');
    try { const res = await API.upload('/admin/api/upload.php', fd); setBuildImage(type, res.url); } catch(e) {}
}

function setBuildImage(type, url) {
    if (type === 'icon') {
        document.getElementById('buildIconUrl').value = url;
        const prev = document.getElementById('buildIconPreview'); prev.src = '/' + url; prev.style.display = '';
    } else {
        document.getElementById('buildSplashUrl').value = url;
        const prev = document.getElementById('buildSplashPreview'); prev.src = '/' + url; prev.style.display = '';
    }
}

async function loadKeystoreSelect() {
    try {
        const rows = await API.get('/admin/api/keystores.php');
        const sel = document.getElementById('buildKeystoreId');
        const curVal = sel.value;
        sel.innerHTML = '<option value="">-- 请选择签名密钥 --</option>';
        for (const k of rows) sel.innerHTML += `<option value="${k.id}">${escapeHTML(k.name)} (${escapeHTML(k.alias)})</option>`;
        if (curVal) sel.value = curVal;
    } catch(e) {}
}

async function startBuild() {
    const btn = document.getElementById('buildStartBtn');
    if (btn.disabled) { Toast.error('正在生成中，请耐心等待'); return; }
    const url = document.getElementById('buildUrl').value.trim();
    const appName = document.getElementById('buildAppName').value.trim();
    const packageName = document.getElementById('buildPackage').value.trim();
    const versionName = document.getElementById('buildVersionName').value.trim() || '1.0.0';
    const versionCode = parseInt(document.getElementById('buildVersionCode').value) || 1;
    const keystoreId = parseInt(document.getElementById('buildKeystoreId').value) || 0;
    const iconUrl = document.getElementById('buildIconUrl').value;
    const splashUrl = document.getElementById('buildSplashUrl').value;
    const splashColor = document.getElementById('buildSplashColor').value;
    const statusBarColor = document.getElementById('buildStatusBarColor').value;

    if (!url) { Toast.error('请输入目标URL'); return; }
    if (!appName) { Toast.error('请输入应用名称'); return; }
    if (!packageName) { Toast.error('请输入包名'); return; }
    if (!/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){1,}$/.test(packageName)) { Toast.error('包名格式不正确'); return; }
    if (!keystoreId) { Toast.error('请选择签名密钥'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 正在生成中...';
    document.getElementById('buildProgressCard').style.display = '';
    document.getElementById('buildResult').style.display = 'none';
    updateProgress(0, '提交构建任务...');

    try {
        const res = await API.post('/admin/api/generate.php', {
            action: 'build', url, app_name: appName, package_name: packageName,
            version_name: versionName, version_code: versionCode, keystore_id: keystoreId,
            icon_url: iconUrl, splash_url: splashUrl, splash_color: splashColor, status_bar_color: statusBarColor,
        });
        pollBuildStatus(res.task_id);
    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-hammer"></i> 开始生成APK';
        updateProgress(0, '提交失败');
    }
}

function pollBuildStatus(taskId) {
    if (buildPolling) clearInterval(buildPolling);
    buildPolling = setInterval(async () => {
        try {
            const t = await API.get(`/admin/api/generate.php?action=task_status&id=${taskId}`);
            updateProgress(t.progress, t.progress_msg);
            if (t.status === 'done') {
                clearInterval(buildPolling); buildPolling = null;
                document.getElementById('buildStartBtn').disabled = false;
                document.getElementById('buildStartBtn').innerHTML = '<i class="fas fa-hammer"></i> 开始生成APK';
                const rd = document.getElementById('buildResult'); rd.style.display = ''; rd.style.background = '#ecfdf5';
                rd.innerHTML = `<div style="color:#10b981;font-size:1.2em;font-weight:600;margin-bottom:8px;"><i class="fas fa-check-circle"></i> 构建成功!</div><p>文件大小: ${escapeHTML(t.result_size)}</p><a href="/${escapeHTML(t.result_url)}" class="btn btn-primary" download><i class="fas fa-download"></i> 下载APK</a>`;
            }
            if (t.status === 'failed') {
                clearInterval(buildPolling); buildPolling = null;
                document.getElementById('buildStartBtn').disabled = false;
                document.getElementById('buildStartBtn').innerHTML = '<i class="fas fa-hammer"></i> 开始生成APK';
                const rd = document.getElementById('buildResult'); rd.style.display = ''; rd.style.background = '#fef2f2';
                rd.innerHTML = `<div style="color:#ef4444;font-size:1.2em;font-weight:600;margin-bottom:8px;"><i class="fas fa-times-circle"></i> 构建失败</div><pre style="text-align:left;font-size:0.8em;max-height:200px;overflow:auto;background:#1a1a2e;color:#eee;padding:12px;border-radius:6px;margin-top:8px;">${escapeHTML(t.error_msg || '未知错误')}</pre>`;
            }
        } catch(e) {}
    }, 2500);
}

function updateProgress(pct, msg) {
    document.getElementById('buildProgressBar').style.width = pct + '%';
    document.getElementById('buildProgressPercent').textContent = pct + '%';
    document.getElementById('buildProgressMsg').textContent = msg || '';
}

// ===== 签名密钥 =====
async function loadKeys() {
    const el = document.getElementById('keyList');
    try {
        const rows = await API.get('/admin/api/keystores.php');
        if (!rows.length) { el.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px;">暂无密钥</p>'; return; }
        let html = '<table class="data-table"><thead><tr><th>名称</th><th>别名</th><th>组织</th><th>有效期</th><th>创建时间</th><th>操作</th></tr></thead><tbody>';
        for (const k of rows) {
            const org = [k.common_name, k.org_name].filter(Boolean).join(' / ') || '-';
            html += `<tr><td>${escapeHTML(k.name)}</td><td><code>${escapeHTML(k.alias)}</code></td><td>${escapeHTML(org)}</td><td>${k.validity_years}年</td><td style="white-space:nowrap;">${k.created_at || ''}</td><td style="white-space:nowrap;"><button class="btn btn-outline btn-sm" onclick="deleteKey(${k.id})" title="删除" style="color:#e74c3c;"><i class="fas fa-trash"></i></button></td></tr>`;
        }
        el.innerHTML = html + '</tbody></table>';
    } catch(e) { el.innerHTML = '<p style="color:#e74c3c;text-align:center;padding:20px;">加载失败</p>'; }
}

async function deleteKey(id) {
    if (!await confirmAction('确定要删除此签名密钥？')) return;
    try { await API.del('/admin/api/keystores.php', { id }); Toast.success('已删除'); loadKeys(); loadKeystoreSelect(); } catch(e) {}
}

function showGenerateKeyModal() {
    ['gkName','gkAlias','gkStorePwd','gkKeyPwd','gkCN','gkOrg','gkOU','gkCountry','gkState','gkCity'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('gkValidity').value = 25;
    Modal.show('generateKeyModal');
}

async function doGenerateKey() {
    const name = document.getElementById('gkName').value.trim();
    const alias = document.getElementById('gkAlias').value.trim();
    const storePwd = document.getElementById('gkStorePwd').value;
    const keyPwd = document.getElementById('gkKeyPwd').value;
    if (!name || !alias) { Toast.error('名称和别名为必填项'); return; }
    if (storePwd.length < 6 || keyPwd.length < 6) { Toast.error('密码至少6位'); return; }
    document.getElementById('gkSubmitBtn').disabled = true;
    try {
        await API.post('/admin/api/keystores.php?action=generate', {
            name, alias, store_password: storePwd, key_password: keyPwd,
            validity_years: parseInt(document.getElementById('gkValidity').value) || 25,
            common_name: document.getElementById('gkCN').value.trim(), org_name: document.getElementById('gkOrg').value.trim(),
            org_unit: document.getElementById('gkOU').value.trim(), country: document.getElementById('gkCountry').value.trim(),
            state_name: document.getElementById('gkState').value.trim(), locality: document.getElementById('gkCity').value.trim(),
        });
        Toast.success('密钥已生成'); Modal.hide('generateKeyModal'); loadKeys(); loadKeystoreSelect();
    } catch(e) {}
    document.getElementById('gkSubmitBtn').disabled = false;
}

function showUploadKeyModal() {
    ['ukName','ukAlias','ukStorePwd','ukKeyPwd','ukFileName'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('ukFileInput').value = '';
    Modal.show('uploadKeyModal');
}

async function doUploadKey() {
    const fileInput = document.getElementById('ukFileInput');
    if (!fileInput.files[0]) { Toast.error('请选择keystore文件'); return; }
    const name = document.getElementById('ukName').value.trim();
    const alias = document.getElementById('ukAlias').value.trim();
    const storePwd = document.getElementById('ukStorePwd').value;
    const keyPwd = document.getElementById('ukKeyPwd').value;
    if (!name || !alias || !storePwd || !keyPwd) { Toast.error('所有字段为必填项'); return; }
    const fd = new FormData();
    fd.append('file', fileInput.files[0]); fd.append('action', 'upload');
    fd.append('name', name); fd.append('alias', alias);
    fd.append('store_password', storePwd); fd.append('key_password', keyPwd);
    document.getElementById('ukSubmitBtn').disabled = true;
    try { await API.upload('/admin/api/keystores.php', fd); Toast.success('密钥已导入'); Modal.hide('uploadKeyModal'); loadKeys(); loadKeystoreSelect(); } catch(e) {}
    document.getElementById('ukSubmitBtn').disabled = false;
}

// ===== Mobileconfig管理 =====
let mcListData = [];

async function loadMcList() {
    const el = document.getElementById('mcFileList');
    try {
        mcListData = await API.get('/admin/api/mobileconfig.php?action=list');
        if (!mcListData.length) {
            el.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px;">暂无生成记录，点击上方"生成新文件"创建</p>';
            return;
        }
        let html = '<table class="data-table"><thead><tr><th>显示名称</th><th>Bundle ID</th><th>目标URL</th><th>证书</th><th>签名</th><th>关联应用</th><th>大小</th><th>创建时间</th><th>操作</th></tr></thead><tbody>';
        for (const r of mcListData) {
            const fname = r.file_path ? r.file_path.split('/').pop() : '';
            const signedHtml = r.cert_id
                ? '<span style="color:#27ae60;"><i class="fas fa-lock"></i> 已签名</span>'
                : '<span style="color:var(--text-secondary);"><i class="fas fa-lock-open"></i> 未签名</span>';
            html += `<tr>
                <td>${escapeHTML(r.display_name)}</td>
                <td><code style="font-size:0.8em;">${escapeHTML(r.bundle_id || '-')}</code></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHTML(r.target_url)}">${escapeHTML(r.target_url)}</td>
                <td>${r.cert_name ? escapeHTML(r.cert_name) : '<span style="color:var(--text-secondary);">无</span>'}</td>
                <td style="white-space:nowrap;">${signedHtml}</td>
                <td>${r.linked_app_name ? escapeHTML(r.linked_app_name) : '<span style="color:var(--text-secondary);">未关联</span>'}</td>
                <td>${escapeHTML(r.file_size || '-')}</td>
                <td style="white-space:nowrap;">${r.created_at || ''}</td>
                <td style="white-space:nowrap;">
                    ${r.file_path ? `<a href="/${escapeHTML(r.file_path)}" class="btn btn-outline btn-sm" download title="下载"><i class="fas fa-download"></i></a>` : ''}
                    <button class="btn btn-outline btn-sm" onclick="showMcEdit(${r.id})" title="编辑"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="showMcRename(${r.id}, '${escapeHTML(fname)}')" title="重命名"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="showMcAssociate(${r.id})" title="关联应用"><i class="fas fa-link"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="deleteMc(${r.id})" title="删除" style="color:#e74c3c;"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        }
        el.innerHTML = html + '</tbody></table>';
    } catch(e) { el.innerHTML = '<p style="color:#e74c3c;text-align:center;padding:20px;">加载失败</p>'; }
}

async function deleteMc(id) {
    if (!await confirmAction('确定要删除此Mobileconfig文件？')) return;
    try { await API.del('/admin/api/mobileconfig.php', { id }); Toast.success('已删除'); loadMcList(); } catch(e) {}
}

async function showMcAssociate(mcId) {
    document.getElementById('mcAssocMcId').value = mcId;
    if (!allApps.length) await loadApps();
    const sel = document.getElementById('mcAssocAppId');
    sel.innerHTML = '<option value="">-- 请选择 --</option>';
    for (const a of allApps) sel.innerHTML += `<option value="${a.id}">${escapeHTML(a.name)}</option>`;
    document.getElementById('mcAssocPlatformId').innerHTML = '<option value="">-- 先选择应用 --</option>';
    document.getElementById('mcAssocVersion').value = '1.0';
    Modal.show('mcAssociateModal');
}

async function loadMcAssocPlatforms() {
    const appId = document.getElementById('mcAssocAppId').value;
    const sel = document.getElementById('mcAssocPlatformId');
    if (!appId) { sel.innerHTML = '<option value="">-- 先选择应用 --</option>'; return; }
    try {
        const platforms = await API.get('/admin/api/attachments.php?app_id=' + appId);
        sel.innerHTML = '<option value="">-- 请选择分类 --</option>';
        for (const p of platforms) sel.innerHTML += `<option value="${p.id}">${escapeHTML(p.name)}</option>`;
        if (!platforms.length) sel.innerHTML = '<option value="">该应用暂无附件分类</option>';
    } catch(e) { sel.innerHTML = '<option value="">加载失败</option>'; }
}

async function doMcAssociate() {
    const mcId = parseInt(document.getElementById('mcAssocMcId').value);
    const appId = document.getElementById('mcAssocAppId').value;
    const platformId = document.getElementById('mcAssocPlatformId').value;
    const version = document.getElementById('mcAssocVersion').value.trim() || '1.0';
    if (!appId) { Toast.error('请选择应用'); return; }
    if (!platformId) { Toast.error('请选择附件分类'); return; }
    try {
        await API.put('/admin/api/mobileconfig.php', { action: 'associate', mc_id: mcId, app_id: parseInt(appId), platform_id: parseInt(platformId), version: version });
        Toast.success('已关联到附件库'); Modal.hide('mcAssociateModal'); loadMcList();
    } catch(e) {}
}

function showMcRename(id, currentName) {
    document.getElementById('mcRenameId').value = id;
    document.getElementById('mcCurrentName').value = currentName;
    document.getElementById('mcNewName').value = currentName;
    Modal.show('mcRenameModal');
}

async function doMcRename() {
    const id = parseInt(document.getElementById('mcRenameId').value);
    const newName = document.getElementById('mcNewName').value.trim();
    if (!newName) { Toast.error('请输入新文件名'); return; }
    try {
        await API.put('/admin/api/mobileconfig.php', { action: 'rename', id, new_name: newName });
        Toast.success('重命名成功'); Modal.hide('mcRenameModal'); loadMcList();
    } catch(e) {}
}

function showMcEdit(id) {
    const r = mcListData.find(m => m.id === id);
    if (!r) return;
    document.getElementById('mcEditId').value = id;
    document.getElementById('mcEditName').value = r.display_name;
    document.getElementById('mcEditUrl').value = r.target_url;
    document.getElementById('mcEditBundleId').value = r.bundle_id || '';
    document.getElementById('mcEditVersion').value = r.version || '1';
    document.getElementById('mcEditFullscreen').value = r.fullscreen ? '1' : '0';
    document.getElementById('mcEditDesc').value = r.description || '';
    document.getElementById('mcEditPayloadOrg').value = r.payload_org || '';
    document.getElementById('mcEditIconData').value = r.icon_data || '';
    const preview = document.getElementById('mcEditIconPreview');
    if (r.icon_data) { preview.src = 'data:image/png;base64,' + r.icon_data; preview.style.display = ''; }
    else { preview.style.display = 'none'; }
    loadMcCertSelect().then(() => {
        document.getElementById('mcEditCertId').value = r.cert_id || '';
    });
    Modal.show('mcEditModal');
}

async function updateMobileconfig() {
    const id = parseInt(document.getElementById('mcEditId').value);
    const btn = document.getElementById('mcEditSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
    try {
        await API.put('/admin/api/mobileconfig.php', {
            action: 'update', id,
            display_name: document.getElementById('mcEditName').value.trim(),
            target_url: document.getElementById('mcEditUrl').value.trim(),
            bundle_id: document.getElementById('mcEditBundleId').value.trim(),
            version: document.getElementById('mcEditVersion').value.trim(),
            fullscreen: document.getElementById('mcEditFullscreen').value,
            description: document.getElementById('mcEditDesc').value.trim(),
            icon_data: document.getElementById('mcEditIconData').value,
            cert_id: document.getElementById('mcEditCertId').value || null,
            payload_org: document.getElementById('mcEditPayloadOrg').value.trim(),
        });
        Toast.success('已更新并重新生成'); Modal.hide('mcEditModal'); loadMcList();
    } catch(e) {}
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> 保存并重新生成';
}

// 生成新Mobileconfig
async function generateMobileconfig() {
    const name = document.getElementById('mcBuildName').value.trim();
    const url = document.getElementById('mcBuildUrl').value.trim();
    if (!name) { Toast.error('请输入显示名称'); return; }
    if (!url) { Toast.error('请输入目标URL'); return; }

    const btn = document.getElementById('mcBuildBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
    try {
        const res = await API.post('/admin/api/mobileconfig.php', {
            action: 'generate',
            display_name: name, target_url: url,
            bundle_id: document.getElementById('mcBuildBundleId').value.trim(),
            version: document.getElementById('mcBuildVersion').value.trim() || '1',
            fullscreen: document.getElementById('mcBuildFullscreen').value,
            description: document.getElementById('mcBuildDesc').value.trim(),
            icon_data: document.getElementById('mcBuildIconData').value,
            cert_id: document.getElementById('mcBuildCertId').value || null,
            payload_org: document.getElementById('mcBuildPayloadOrg').value.trim(),
        });
        Toast.success(res.signed ? '已生成并签名' : '已生成（未签名）');
        switchIosSubTab('mc_list');
    } catch(e) {}
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-magic"></i> 生成Mobileconfig';
}

// 图标上传（Base64嵌入）
function uploadMcIconFile(input, previewId, dataId) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        const base64 = reader.result.split(',')[1];
        document.getElementById(dataId).value = base64;
        const prev = document.getElementById(previewId);
        prev.src = reader.result; prev.style.display = '';
    };
    reader.readAsDataURL(file);
    input.value = '';
}

function pickMcIconFromLib(url, previewId, dataId) {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = img.width; canvas.height = img.height;
        canvas.getContext('2d').drawImage(img, 0, 0);
        const dataUrl = canvas.toDataURL('image/png');
        const base64 = dataUrl.split(',')[1];
        document.getElementById(dataId).value = base64;
        const prev = document.getElementById(previewId);
        prev.src = dataUrl; prev.style.display = '';
    };
    img.src = '/' + url;
}

// ===== 证书管理 =====
async function loadMcCertSelect() {
    try {
        const rows = await API.get('/admin/api/mobileconfig.php?action=list_certs');
        ['mcBuildCertId', 'mcEditCertId'].forEach(selId => {
            const sel = document.getElementById(selId);
            if (!sel) return;
            const curVal = sel.value;
            sel.innerHTML = '<option value="">-- 自动使用全局证书 --</option>';
            for (const c of rows) {
                const globalTag = c.is_global ? ' ⭐全局' : '';
                sel.innerHTML += `<option value="${c.id}">${escapeHTML(c.name)}${globalTag}</option>`;
            }
            if (curVal) sel.value = curVal;
        });
    } catch(e) {}
}

async function loadMcCerts() {
    const el = document.getElementById('mcCertList');
    try {
        const rows = await API.get('/admin/api/mobileconfig.php?action=list_certs');
        if (!rows.length) {
            el.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px;">暂无证书，点击"新建证书"或"从设置导入"</p>';
            return;
        }
        const modeLabel = { text: '文本', path: '路径', upload: '上传' };
        let html = '<table class="data-table"><thead><tr><th>名称</th><th>颁发者</th><th>模式</th><th>组织名</th><th style="text-align:center;">全局默认</th><th>到期时间</th><th>操作</th></tr></thead><tbody>';
        for (const c of rows) {
            // 到期时间高亮：已过期红色，30天内即将过期橙色
            let expiresHtml = '-';
            if (c.cert_expires) {
                const exp = new Date(c.cert_expires);
                const now = new Date();
                const daysLeft = Math.ceil((exp - now) / 86400000);
                if (daysLeft < 0) {
                    expiresHtml = `<span style="color:#e74c3c;font-weight:600;" title="已过期">${escapeHTML(c.cert_expires)}</span>`;
                } else if (daysLeft < 30) {
                    expiresHtml = `<span style="color:#e67e22;font-weight:600;" title="${daysLeft}天后过期">${escapeHTML(c.cert_expires)}</span>`;
                } else {
                    expiresHtml = `<span title="${daysLeft}天后过期">${escapeHTML(c.cert_expires)}</span>`;
                }
            }
            html += `<tr>
                <td>${escapeHTML(c.name)}</td>
                <td style="font-size:0.85em;color:#555;">${escapeHTML(c.cert_issuer || '-')}</td>
                <td>${modeLabel[c.mode] || c.mode}</td>
                <td>${escapeHTML(c.payload_org || '-')}</td>
                <td style="text-align:center;">${c.is_global ? '<span style="color:#27ae60;font-weight:600;">&#11088; 是</span>' : '<span style="color:var(--text-secondary);cursor:pointer;" onclick="setGlobalCert(' + c.id + ')" title="点击设为全局">否</span>'}</td>
                <td style="white-space:nowrap;">${expiresHtml}</td>
                <td style="white-space:nowrap;">
                    <button class="btn btn-outline btn-sm" onclick="showEditCertModal(${c.id})" title="编辑"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="deleteCert(${c.id})" title="删除" style="color:#e74c3c;"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        }
        el.innerHTML = html + '</tbody></table>';
    } catch(e) { el.innerHTML = '<p style="color:#e74c3c;text-align:center;padding:20px;">加载失败</p>'; }
}

async function setGlobalCert(id) {
    try {
        await API.put('/admin/api/mobileconfig.php', { action: 'update_cert', id, is_global: 1 });
        Toast.success('已设为全局默认'); loadMcCerts(); loadMcCertSelect();
    } catch(e) {}
}

async function deleteCert(id) {
    if (!await confirmAction('确定要删除此证书？')) return;
    try { await API.del('/admin/api/mobileconfig.php', { action: 'delete_cert', id }); Toast.success('已删除'); loadMcCerts(); loadMcCertSelect(); } catch(e) {}
}

function toggleCertMode() {
    const mode = document.getElementById('certMode').value;
    document.getElementById('certModeText').style.display = mode === 'text' ? '' : 'none';
    document.getElementById('certModePath').style.display = mode === 'path' ? '' : 'none';
    document.getElementById('certModeUpload').style.display = mode === 'upload' ? '' : 'none';
}

function showAddCertModal() {
    document.getElementById('addCertModalTitle').textContent = '新建签名证书';
    document.getElementById('editCertId').value = '';
    ['certName','certPayloadOrg','certCertText','certKeyText','certChainText','certCertPath','certKeyPath','certChainPath','certCertUpload','certKeyUpload','certChainUpload'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('certIsGlobal').checked = false;
    document.getElementById('certMode').value = 'text';
    toggleCertMode();
    Modal.show('addCertModal');
}

async function showEditCertModal(id) {
    try {
        const c = await API.get(`/admin/api/mobileconfig.php?action=get_cert&id=${id}`);
        document.getElementById('addCertModalTitle').textContent = '编辑签名证书';
        document.getElementById('editCertId').value = c.id;
        document.getElementById('certName').value = c.name || '';
        document.getElementById('certPayloadOrg').value = c.payload_org || '';
        document.getElementById('certIsGlobal').checked = !!c.is_global;
        document.getElementById('certMode').value = c.mode || 'text';
        toggleCertMode();
        // 填入证书值到对应模式字段
        const suffix = c.mode === 'text' ? 'Text' : c.mode === 'path' ? 'Path' : 'Upload';
        document.getElementById('certCert' + suffix).value = c.cert || '';
        document.getElementById('certKey' + suffix).value = c.key || '';
        document.getElementById('certChain' + suffix).value = c.chain || '';
        Modal.show('addCertModal');
    } catch(e) {}
}

async function submitCert() {
    const name = document.getElementById('certName').value.trim();
    if (!name) { Toast.error('请输入证书名称'); return; }
    const mode = document.getElementById('certMode').value;
    const suffix = mode === 'text' ? 'Text' : mode === 'path' ? 'Path' : 'Upload';
    const cert = document.getElementById('certCert' + suffix).value.trim();
    const key = document.getElementById('certKey' + suffix).value.trim();
    const chain = document.getElementById('certChain' + suffix).value.trim();
    const payload = {
        name, mode, cert, key, chain,
        payload_org: document.getElementById('certPayloadOrg').value.trim(),
        is_global: document.getElementById('certIsGlobal').checked ? 1 : 0,
    };

    const editId = document.getElementById('editCertId').value;
    document.getElementById('certSubmitBtn').disabled = true;
    try {
        if (editId) {
            await API.put('/admin/api/mobileconfig.php', { action: 'update_cert', id: parseInt(editId), ...payload });
        } else {
            await API.post('/admin/api/mobileconfig.php', { action: 'create_cert', ...payload });
        }
        Toast.success(editId ? '证书已更新' : '证书已创建');
        Modal.hide('addCertModal'); loadMcCerts(); loadMcCertSelect();
    } catch(e) {}
    document.getElementById('certSubmitBtn').disabled = false;
}

// ===== 初始化 =====
loadApps();
switchTab('apks');
loadKeystoreSelect();
checkRunningTask();

async function checkRunningTask() {
    try {
        const tasks = await API.get('/admin/api/generate.php?action=list_tasks');
        const running = tasks.find(t => t.status === 'pending' || t.status === 'building');
        if (running) {
            switchTab('build');
            document.getElementById('buildStartBtn').disabled = true;
            document.getElementById('buildStartBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 正在生成中...';
            document.getElementById('buildProgressCard').style.display = '';
            document.getElementById('buildResult').style.display = 'none';
            updateProgress(running.progress, running.progress_msg);
            pollBuildStatus(running.id);
        }
    } catch(e) {}
}

// ========== IPA 功能 ==========

function switchIpaSubTab(tab) {
    ['ipa_list', 'ipa_build'].forEach(t => {
        const el = document.getElementById('ipa_sub_' + t);
        if (el) el.style.display = t === tab ? '' : 'none';
        const btn = document.getElementById('ipaSubBtn_' + t);
        if (btn) {
            btn.style.color = t === tab ? 'var(--primary)' : 'var(--text-secondary)';
            btn.style.borderBottomColor = t === tab ? 'var(--primary)' : 'transparent';
        }
    });
    if (tab === 'ipa_list') loadIpas();
}

async function loadIpas() {
    const el = document.getElementById('ipaList');
    try {
        const [rows, tasks] = await Promise.all([
            API.get('/admin/api/generate.php?action=list_ipas'),
            API.get('/admin/api/generate.php?action=list_ipa_tasks'),
        ]);
        const runningTasks = tasks.filter(t => t.status === 'pending' || t.status === 'building');

        if (!rows.length && !runningTasks.length) {
            el.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px;">暂无IPA生成记录</p>';
            return;
        }
        let html = '<table class="data-table"><thead><tr>' +
            '<th>应用名</th><th>Bundle ID</th><th>版本</th><th>URL</th><th>大小</th><th>签名</th><th>关联应用</th><th>创建时间</th><th>操作</th>' +
            '</tr></thead><tbody>';

        for (const t of runningTasks) {
            const statusLabel = t.status === 'building'
                ? `<span style="background:#f59e0b;color:#fff;padding:2px 8px;border-radius:4px;font-size:0.8em;"><i class="fas fa-spinner fa-spin"></i> 构建中 ${t.progress}%</span>`
                : `<span style="background:#6366f1;color:#fff;padding:2px 8px;border-radius:4px;font-size:0.8em;"><i class="fas fa-clock"></i> 等待中</span>`;
            html += `<tr style="background:#fffbeb;">
                <td>${escapeHTML(t.app_name || '-')}</td>
                <td><code style="font-size:0.8em;">${escapeHTML(t.bundle_id || '-')}</code></td>
                <td>-</td><td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${statusLabel}</td>
                <td>${escapeHTML(t.progress_msg || '')}</td><td>-</td><td>-</td>
                <td>${t.created_at || '-'}</td>
                <td><button class="btn btn-outline btn-sm" onclick="cancelIpaBuildTask(${t.id})" style="color:#e74c3c;border-color:#e74c3c;"><i class="fas fa-times"></i></button></td>
            </tr>`;
        }

        for (const r of rows) {
            const filename = r.ipa_url ? r.ipa_url.split('/').pop() : '-';
            html += `<tr>
                <td>${escapeHTML(r.app_name)}</td>
                <td><code style="font-size:0.8em;">${escapeHTML(r.bundle_id)}</code></td>
                <td>${escapeHTML(r.version_name)}</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escapeHTML(r.url)}">${escapeHTML(r.url)}</td>
                <td>${escapeHTML(r.ipa_size || '-')}</td>
                <td><span style="background:#e5e7eb;padding:2px 6px;border-radius:4px;font-size:0.8em;">${escapeHTML(r.signing_mode || 'unsigned')}</span></td>
                <td>${r.linked_app_name ? escapeHTML(r.linked_app_name) : '<span style="color:var(--text-secondary);">未关联</span>'}</td>
                <td style="white-space:nowrap;">${r.created_at || '-'}</td>
                <td style="white-space:nowrap;">
                    ${r.ipa_url ? `<a href="/${escapeHTML(r.ipa_url)}" class="btn btn-outline btn-sm" download title="下载"><i class="fas fa-download"></i></a>` : ''}
                    <button class="btn btn-outline btn-sm" onclick="showIpaAssociate(${r.id})" title="关联应用"><i class="fas fa-link"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="showIpaRename(${r.id}, '${escapeHTML(filename)}')" title="重命名"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-outline btn-sm" onclick="deleteIpa(${r.id})" style="color:#e74c3c;border-color:#e74c3c;" title="删除"><i class="fas fa-trash-alt"></i></button>
                </td>
            </tr>`;
        }
        html += '</tbody></table>';
        el.innerHTML = html;
    } catch (e) {
        el.innerHTML = '<p style="color:var(--danger);text-align:center;padding:20px;">加载失败</p>';
    }
}

async function startIpaBuild() {
    const url = document.getElementById('ipaBuildUrl').value.trim();
    const appName = document.getElementById('ipaBuildAppName').value.trim();
    const bundleId = document.getElementById('ipaBuildBundleId').value.trim();
    const versionName = document.getElementById('ipaBuildVersion').value.trim() || '1.0.0';
    const iconUrl = document.getElementById('ipaBuildIconUrl').value;
    const statusBarColor = document.getElementById('ipaBuildStatusBarColor').value;

    if (!url) { AlertModal.error('错误', '请输入目标URL'); return; }
    if (!appName) { AlertModal.error('错误', '请输入应用名称'); return; }
    if (!bundleId) { AlertModal.error('错误', '请输入Bundle ID'); return; }
    if (!/^[a-z][a-z0-9_-]*(\.[a-z][a-z0-9_-]*){1,}$/.test(bundleId)) {
        AlertModal.error('错误', 'Bundle ID 格式不正确，例: com.example.app');
        return;
    }

    const btn = document.getElementById('ipaBuildBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 提交中...';

    try {
        const res = await API.post('/admin/api/generate.php', {
            action: 'build_ipa', url, app_name: appName, bundle_id: bundleId,
            version_name: versionName, icon_url: iconUrl, status_bar_color: statusBarColor,
        });
        document.getElementById('ipaBuildProgress').style.display = '';
        pollIpaBuildStatus(res.task_id);
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-hammer"></i> 开始构建 IPA';
    }
}

let _ipaPollTimer = null;
function pollIpaBuildStatus(taskId) {
    if (_ipaPollTimer) clearInterval(_ipaPollTimer);
    _ipaPollTimer = setInterval(async () => {
        try {
            const resp = await fetch('/admin/api/generate.php?action=task_status&id=' + taskId, {
                headers: { 'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' }
            });
            const task = await resp.json();
            const bar = document.getElementById('ipaBuildProgressBar');
            const pct = document.getElementById('ipaBuildProgressPct');
            const msg = document.getElementById('ipaBuildProgressMsg');
            if (bar) {
                bar.style.width = task.progress + '%';
                pct.textContent = task.progress + '%';
            }
            if (msg) msg.textContent = task.progress_msg || '';

            if (task.status === 'done') {
                clearInterval(_ipaPollTimer);
                bar.style.background = '#27ae60';
                msg.innerHTML = '<span style="color:#27ae60;font-weight:600;"><i class="fas fa-check-circle"></i> 构建完成！</span>' +
                    (task.result_url ? ` <a href="/${escapeHTML(task.result_url)}" class="btn btn-primary btn-sm" download style="margin-left:8px;"><i class="fas fa-download"></i> 下载 IPA (${escapeHTML(task.result_size)})</a>` : '');
                const btn = document.getElementById('ipaBuildBtn');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-hammer"></i> 开始构建 IPA';
            } else if (task.status === 'failed') {
                clearInterval(_ipaPollTimer);
                bar.style.background = '#e74c3c';
                msg.innerHTML = '<span style="color:#e74c3c;font-weight:600;"><i class="fas fa-times-circle"></i> 构建失败</span><pre style="background:#fff5f5;padding:8px;border-radius:4px;margin-top:8px;font-size:0.8em;white-space:pre-wrap;max-height:200px;overflow-y:auto;">' + escapeHTML(task.error_msg || '未知错误') + '</pre>';
                const btn = document.getElementById('ipaBuildBtn');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-hammer"></i> 开始构建 IPA';
            }
        } catch (e) {}
    }, 2000);
}

async function cancelIpaBuildTask(taskId) {
    if (!await AlertModal.confirm('确认', '确定取消此 IPA 构建任务？')) return;
    try {
        await API.post('/admin/api/generate.php', { action: 'cancel_task', task_id: taskId });
        AlertModal.success('已取消', '任务已取消');
        loadIpas();
    } catch (e) {}
}

async function deleteIpa(id) {
    if (!await AlertModal.confirm('确认删除', '将永久删除此IPA文件和记录，是否继续？', { icon: 'danger', okText: '删除', okClass: 'btn-danger' })) return;
    try {
        await API.del('/admin/api/generate.php', { id, type: 'ipa' });
        AlertModal.success('已删除');
        loadIpas();
    } catch (e) {}
}

function showIpaAssociate(ipaId) {
    document.getElementById('ipaAssocIpaId').value = ipaId;
    const sel = document.getElementById('ipaAssocAppId');
    sel.innerHTML = '<option value="">-- 不关联 --</option>';
    allApps.forEach(a => sel.innerHTML += `<option value="${a.id}">${escapeHTML(a.name)}</option>`);
    Modal.show('ipaAssociateModal');
}

async function doIpaAssociate() {
    const ipaId = document.getElementById('ipaAssocIpaId').value;
    const appId = document.getElementById('ipaAssocAppId').value;
    try {
        await API.put('/admin/api/generate.php', { action: 'associate', type: 'ipa', ipa_id: parseInt(ipaId), app_id: appId ? parseInt(appId) : null });
        Modal.hide('ipaAssociateModal');
        AlertModal.success('关联成功');
        loadIpas();
    } catch (e) {}
}

function showIpaRename(id, currentName) {
    document.getElementById('ipaRenameId').value = id;
    document.getElementById('ipaCurrentName').value = currentName;
    document.getElementById('ipaNewName').value = currentName;
    Modal.show('ipaRenameModal');
}

async function doIpaRename() {
    const id = document.getElementById('ipaRenameId').value;
    const newName = document.getElementById('ipaNewName').value.trim();
    if (!newName) { AlertModal.error('错误', '请输入新文件名'); return; }
    try {
        await API.put('/admin/api/generate.php', { action: 'rename', type: 'ipa', ipa_id: parseInt(id), new_name: newName });
        Modal.hide('ipaRenameModal');
        AlertModal.success('重命名成功');
        loadIpas();
    } catch (e) {}
}

function uploadIpaIcon() {
    const input = document.getElementById('ipaIconUpload');
    input.onchange = async function() {
        if (!this.files.length) return;
        const fd = new FormData();
        fd.append('file', this.files[0]);
        fd.append('category', 'image');
        fd.append('_csrf', CSRF_TOKEN);
        try {
            const res = await API.upload('/admin/api/upload.php', fd);
            document.getElementById('ipaBuildIconUrl').value = res.url;
            const p = document.getElementById('ipaIconPreview');
            p.src = '/' + res.url;
            p.style.display = '';
        } catch (e) {}
        this.value = '';
    };
    input.click();
}

// 检查正在运行的 IPA 构建
async function checkRunningIpaTask() {
    try {
        const tasks = await API.get('/admin/api/generate.php?action=list_ipa_tasks');
        const running = tasks.find(t => t.status === 'pending' || t.status === 'building');
        if (running) {
            switchPlatform('ios');
            switchIosSubTab('ipa');
            switchIpaSubTab('ipa_build');
            document.getElementById('ipaBuildBtn').disabled = true;
            document.getElementById('ipaBuildBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 正在构建中...';
            document.getElementById('ipaBuildProgress').style.display = '';
            const bar = document.getElementById('ipaBuildProgressBar');
            const pct = document.getElementById('ipaBuildProgressPct');
            const msg = document.getElementById('ipaBuildProgressMsg');
            bar.style.width = running.progress + '%';
            pct.textContent = running.progress + '%';
            msg.textContent = running.progress_msg || '';
            pollIpaBuildStatus(running.id);
        }
    } catch(e) {}
}
checkRunningIpaTask();
</script>

<?php admin_footer(); ?>
