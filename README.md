<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.0+">
  <img src="https://img.shields.io/badge/Database-SQLite-003B57?style=flat-square&logo=sqlite&logoColor=white" alt="SQLite">
  <img src="https://img.shields.io/badge/Framework-None-green?style=flat-square" alt="No Framework">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=flat-square" alt="MIT License">
</p>

<h1 align="center">AppDown</h1>
<p align="center"><strong>自托管 APP 下载落地页 · 全后台可配置 · 开箱即用</strong></p>
<p align="center">基于 PHP + SQLite，零外部依赖，无需 Composer / Node.js / MySQL</p>

---

## ✨ 功能一览

<table>
<tr>
<td width="50%">

**🏢 后台管理**
- 全可视化配置，无需改代码
- 多应用管理，独立图标 / 主题色 / 下载按钮
- 拖拽排序，实时预览

**📱 iOS 分发**
- 自动生成 plist 文件（IPA 企业签分发）
- Mobileconfig WebClip 描述文件（免签安装到桌面）
- SSL 证书签名（全局 / 单应用独立证书）
- 双模板可选（毛玻璃 / 仿 App Store）
- 内置安装图文教程引导
- 微信 / QQ 自动提示跳转 Safari

**🤖 APK 生成器（URL 转 APK）**
- 输入网址自动封装为 Android WebView 应用
- 自定义应用名称、图标、启动图、包名、版本号
- 签名密钥管理（在线生成 / 导入已有密钥）
- 后台构建 + 实时进度轮询
- 生成结果管理（下载 / 关联到应用 / 删除）
- 一键环境部署脚本
- 自动检测非标准路径的 JDK / SDK

**🍎 IPA 生成器（URL 转 IPA）**
- 通过 Docker-OSX 在 Linux 上运行 macOS + Xcode
- 输入网址自动封装为 iOS WKWebView 应用
- 自定义应用名称、Bundle ID、版本号、图标
- 无签名模式构建（CODE_SIGNING_ALLOWED=NO）
- 三阶段环境部署：Docker 容器(自动) → Xcode 安装(终端交互) → 验证(自动)
- 后台构建 + 实时进度轮询 + IPA 管理

**📊 数据统计**
- 页面访问量 / 下载次数
- 来源智能识别（搜索引擎 / 社交平台 / 开发者平台 / 直接访问）
- 来源 TOP10（自动合并 http/https，图标+类型标签）
- 7天趋势图 / 今日下载明细

</td>
<td width="50%">

**🎨 高度自定义**
- 自定义代码注入（CSS / JS）
- 11 种内置一键特效（樱花、雪花、灯笼、粒子等）
- 节日欢迎弹窗（22个中国节日 + 自定义祝福语）
- 背景音乐播放
- 自定义字体上传（自动识别字体名称）
- 特色卡片（分类管理、FA图标/自定义图片图标）
- 友情链接（支持图标：FA图标/自定义图片、按链接开关显示）

**📎 附件管理**
- 按应用 → 平台 → 版本三级管理
- 拖拽上传 + XHR 进度条
- 文件自动命名（应用名-版本号）
- 上传后可编辑版本号、更新日志
- **安装包详细信息解析**（APK / IPA）：签名信息、版本号、包名 / Bundle ID、证书有效期、权限列表等
- **OCSP 证书吊销检测**：实时查询苹果 OCSP 服务器，检测 IPA 签名证书是否被吊销（掉签）
- 公共图片库（分类管理、格式转换压缩、备注、真实文件重命名、一键复制链接）

**🔒 安全防护**
- CSRF 保护 + 预处理语句
- 登录防爆破（5次锁定15分钟）
- 可选算术验证码
- 安装指纹机制，重装后旧会话自动失效
- 环境检测，安装锁定

</td>
</tr>
</table>

## 🛠 技术栈

| 组件 | 技术 |
|:---|:---|
| 后端 | PHP 8.0+（无框架、无 Composer） |
| 数据库 | SQLite（单文件，零配置） |
| 前端 | 原生 JS + CSS（无构建步骤） |
| 管理面板 | 自定义 UI + Chart.js (CDN) |
| 图标 | Font Awesome 7.1.0（本地） |
| APK 构建 | OpenJDK 17 + Android SDK + Gradle 8.5 |
| IPA 构建 | Docker-OSX (macOS Sonoma) + Xcode + KVM |

## 🚀 快速开始

### 环境要求

- PHP 8.0+ 且启用 `pdo_sqlite` 和 `fileinfo` 扩展
- 导入导出功能需启用 `zip` 扩展
- 安装包解析功能需启用 `zip` 和 `openssl` 扩展
- OCSP 证书吊销检测需启用 `curl` 和 `openssl` 扩展
- Mobileconfig 签名需启用 `openssl` 扩展
- 图片格式转换压缩需启用 `gd` 扩展（支持 WebP/JPEG/PNG/GIF）
- Nginx 或 Apache
- **无需** MySQL、Composer、Node.js
- **APK 生成功能**（可选）需额外安装 JDK 17 + Android SDK，见下方说明
- **IPA 生成功能**（可选）需 Linux 宿主机 + KVM 虚拟化 + Docker，见下方说明

### 一键部署

**方式一：Git 克隆**
```bash
cd /www/wwwroot/你的域名
git clone https://github.com/nljie1103/appdown.git
```

**方式二：下载压缩包**

点击页面右上角绿色 **Code** → **Download ZIP**，解压后将所有文件上传到网站根目录。

### 开始安装

1. 浏览器访问 `https://你的域名/install/`
2. 系统自动检测环境（PHP版本、扩展、目录权限），不通过会红色标示
3. 设置管理员账号和站点名称
4. 安装完成自动锁定，无需手动删除文件

> 💡 安装后访问 `https://你的域名/admin/` 进入后台管理

### APK 生成环境部署（可选）

如需使用「生成应用」功能（URL 转 APK），需要额外安装 JDK 和 Android SDK。

**方式一：一键部署脚本（推荐）**

```bash
sudo bash tools/setup-android-env.sh
```

脚本会自动完成：安装 OpenJDK 17 → 下载 Android SDK 命令行工具（Google 源失败自动切换腾讯云镜像）→ 安装 Build Tools 和 Platform → 验证环境。

**方式二：手动安装**

```bash
# 1. 安装 JDK 17
sudo apt install openjdk-17-jdk

# 2. 下载 Android SDK 命令行工具
sudo mkdir -p /opt/android-sdk/cmdline-tools
cd /opt/android-sdk/cmdline-tools
sudo wget https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip
sudo unzip commandlinetools-linux-*.zip
sudo mv cmdline-tools latest

# 3. 安装 SDK 组件
sudo /opt/android-sdk/cmdline-tools/latest/bin/sdkmanager \
  "build-tools;34.0.0" "platforms;android-34"
```

> ⚠️ 构建脚本会自动检测系统已安装的 JDK 和 SDK（包括非标准路径），也可通过 `JAVA_HOME` 和 `ANDROID_HOME` 环境变量手动指定。

### IPA 生成环境部署（可选）

如需使用「生成应用」的 IPA 功能（URL 转 IPA），需要 Docker-OSX（在 Linux 上通过 Docker 运行 macOS）。

**前提条件：**
- Linux 宿主机（不支持 Windows / macOS Docker Desktop）
- CPU 支持硬件虚拟化（VT-x / AMD-V），BIOS 已开启
- KVM 已安装并可用（`ls /dev/kvm`）
- 磁盘空间 ≥ 50GB（macOS 镜像约 20GB + Xcode 约 12GB）
- 内存 ≥ 8GB

**三阶段部署：**

```bash
# Phase 1: 安装 Docker + 拉取 macOS 镜像 + 创建容器（约 30 分钟）
# 也可以在后台「系统信息」页面一键触发
sudo bash tools/setup-ios-env.sh

# Phase 2: SSH 进入容器安装 Xcode（需要 Apple ID + 2FA 交互）
sudo bash tools/setup-ios-xcode.sh

# Phase 3: 在后台「系统信息」页面点击「验证 Xcode」按钮确认
```

> 💡 Phase 1 和 Phase 3 可以通过后台 Web 界面操作，Phase 2 必须在终端中交互完成（需要输入 Apple ID 和两步验证码）。

**权限配置（如需通过 Web 后台触发安装/卸载）：**

```bash
# 给 PHP 用户 sudo 免密权限
echo 'www-data ALL=(ALL) NOPASSWD: /path/to/tools/setup-ios-env.sh' | sudo tee /etc/sudoers.d/appdown-ios
echo 'www-data ALL=(ALL) NOPASSWD: /path/to/tools/uninstall-ios-env.sh' | sudo tee -a /etc/sudoers.d/appdown-ios

# 给脚本执行权限
chmod +x tools/setup-ios-env.sh tools/setup-ios-xcode.sh tools/uninstall-ios-env.sh
```

## 📂 项目结构

```
appdown/
├── index.html              # 前端主页（API动态加载）
├── install/                # 安装程序（安装后自动锁定）
├── api/                    # 公共API
│   ├── config.php          #   站点配置JSON（带缓存）
│   ├── plist.php           #   iOS plist动态生成
│   ├── mobileconfig.php    #   iOS Mobileconfig 描述文件生成（支持签名）
│   └── track.php           #   访问/下载事件追踪
├── ios/                    # iOS安装引导页
│   ├── index.php           #   路由（根据模板分发）
│   ├── template-modern.php #   毛玻璃风格模板
│   ├── template-classic.php#   仿App Store模板
│   └── static/             #   安装教程截图
├── android/                # Android安装引导页
├── includes/               # PHP公共库（禁止Web访问）
├── admin/                  # 管理后台
│   ├── dashboard.php       #   统计仪表盘
│   ├── apps.php            #   应用列表
│   ├── app-edit.php        #   应用编辑（下载+轮播+iOS+MC签名）
│   ├── generate.php        #   生成应用（APK管理/生成/签名密钥）
│   ├── attachments.php     #   附件管理 + 公共图片库
│   ├── features.php        #   特色卡片（分类管理）
│   ├── links.php           #   友情链接（图标管理）
│   ├── fonts.php           #   字体管理
│   ├── settings.php        #   站点设置（含MC签名证书配置）
│   ├── custom-code.php     #   自定义代码 + 特效配置
│   ├── backup.php          #   数据导入导出
│   ├── system.php          #   系统信息
│   └── api/                #   后台AJAX接口
├── android-template/       # Android WebView 模板项目（Gradle）
├── ios-template/           # iOS WKWebView 模板项目（Xcode/SwiftUI）
├── tools/                  # 命令行工具
│   ├── build-worker.php    #   APK后台构建脚本（CLI）
│   ├── ios-build-worker.php#   IPA后台构建脚本（CLI）
│   ├── setup-android-env.sh#   Android一键环境部署脚本
│   ├── setup-ios-env.sh    #   iOS环境Phase 1：Docker+容器
│   ├── setup-ios-xcode.sh  #   iOS环境Phase 2：Xcode安装（交互式）
│   ├── uninstall-android-env.sh # Android环境卸载
│   └── uninstall-ios-env.sh#   iOS环境卸载
├── static/                 # 静态资源（FontAwesome）
├── data/                   # SQLite数据库（自动创建）
└── uploads/                # 用户上传文件
```

## 🖥 后台功能

| 页面 | 说明 |
|:---|:---|
| 📊 仪表盘 | 今日访问/下载量、7天趋势图、来源 TOP10（智能识别）、下载明细 |
| 📱 应用管理 | 添加/编辑/排序应用，支持自定义图标（FA图标或上传图片） |
| ✏️ 应用编辑 | 下载按钮（图标可自定义）、轮播截图、iOS安装页配置、MC签名证书 |
| 🔨 生成应用 | URL转APK/IPA（构建/进度/下载）、APK/IPA管理、签名密钥管理 |
| 📎 附件管理 | 按平台分类管理安装包，拖拽上传带进度条，上传后可编辑，安装包信息解析，公共图片库（格式转换压缩、真实重命名） |
| ⭐ 特色卡片 | 首页亮点卡片，分类管理，FA图标/自定义图片图标 |
| 🔗 友情链接 | 页脚链接管理，支持图标（FA/自定义图片），按链接开关图标显示 |
| 🔤 字体管理 | 上传自定义字体（自动识别字体名称）或选择系统字体 |
| ⚙️ 站点设置 | 站名/Logo/公告/统计数字/轮播间隔/登录验证码开关 |
| 🎭 特效配置 | 11种内置特效（参数可调）、节日欢迎弹窗、背景音乐 |
| 💻 自定义代码 | head/footer 注入 CSS/JS |
| 💾 导入导出 | 按数据类别选择性备份，支持 AES-256-GCM 加密，含上传文件 |
| 🖧 系统信息 | 运行环境检测、Android/iOS构建环境管理、一键安装/卸载 |

## 🔧 Nginx 安全规则

在 Nginx server 块中添加（宝塔面板：网站 → 设置 → 伪静态）：

```nginx
# 禁止访问数据库和公共库
location ~* ^/(data|includes)/ {
    deny all;
    return 404;
}

location ~* ^/install/(install\.lock|access\.log)$ {
    deny all;
    return 404;
}

# 禁止访问隐藏文件
location ~ /\. {
    deny all;
    return 404;
}

# 禁止在上传目录执行PHP
location ~* ^/uploads/.*\.php$ {
    deny all;
    return 404;
}

# 禁止直接下载签名密钥和证书文件
location ~* ^/uploads/(keystores|certs)/ {
    deny all;
    return 404;
}

# 禁止访问构建工具目录
location ~* ^/(tools|android-template|ios-template)/ {
    deny all;
    return 404;
}
```

## 📡 API 接口

### 公共接口

| 方法 | 路径 | 说明 |
|:---|:---|:---|
| GET | `/api/config.php` | 返回完整站点配置 JSON |
| GET | `/api/plist.php?app=slug` | 动态生成 iOS 安装 plist |
| GET | `/api/mobileconfig.php?app=slug` | 生成 iOS Mobileconfig 描述文件（支持 SSL 签名） |
| POST | `/api/track.php` | 记录访问 / 下载事件 |

### 后台接口（需登录 + CSRF）

| 路径 | 方法 | 说明 |
|:---|:---|:---|
| `/admin/api/apps.php` | CRUD | 应用管理 |
| `/admin/api/downloads.php` | CRUD | 下载按钮 |
| `/admin/api/images.php` | CRUD | 轮播图 |
| `/admin/api/attachments.php` | CRUD | 附件平台分类 |
| `/admin/api/attachment-files.php` | POST/PUT/DELETE | 附件文件上传、编辑、删除 |
| `/admin/api/package-info.php` | GET | 安装包详细信息解析（APK/IPA） |
| `/admin/api/features.php` | CRUD | 特色卡片 + 分类 |
| `/admin/api/links.php` | CRUD | 友情链接 |
| `/admin/api/image-library.php` | CRUD | 公共图片库 |
| `/admin/api/fonts.php` | CRUD | 字体管理 |
| `/admin/api/settings.php` | GET/POST | 站点设置 |
| `/admin/api/custom-code.php` | GET/POST | 自定义代码 |
| `/admin/api/backup.php` | POST | 数据导入导出 |
| `/admin/api/upload.php` | POST | 文件上传 |
| `/admin/api/reorder.php` | POST | 拖拽排序 |
| `/admin/api/generate.php` | CRUD | APK/IPA 构建任务 / 生成结果管理 |
| `/admin/api/keystores.php` | CRUD | 签名密钥管理（生成 / 导入） |

## 💡 常见操作

| 操作 | 方法 |
|:---|:---|
| 重新安装 | 删除 `install/install.lock` 后访问 `/install/` |
| 修改上传限制 | 修改 PHP 配置的 `upload_max_filesize` 和 `post_max_size` |
| 备份数据 | 后台「导入导出」页面，支持选择性导出 + 加密 |
| 手动备份 | 复制 `data/app.db` 和 `uploads/` 目录 |
| 开启验证码 | 后台「站点设置 → 安全设置」开启 |

## 📜 开源协议

[MIT](LICENSE) — 自由使用，欢迎 Star ⭐
