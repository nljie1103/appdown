<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.0+">
  <img src="https://img.shields.io/badge/Database-SQLite-003B57?style=flat-square&logo=sqlite&logoColor=white" alt="SQLite">
  <img src="https://img.shields.io/badge/Admin-Vue_3-42B883?style=flat-square&logo=vuedotjs&logoColor=white" alt="Vue 3 Admin">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=flat-square" alt="MIT License">
</p>

<h1 align="center">AppDown</h1>
<p align="center"><strong>自托管 IPA / APK 分发站 · 全后台配置 · 可视化首页模板 · URL 封装应用</strong></p>
<p align="center">PHP + SQLite 后端 · Vue 3 + TypeScript 管理后台 · 生产环境无需 Node.js</p>

---

> 当前 `main` 分支是 **单站 / 个人自建版**。如果你需要一个安装实例承载多个独立用户、每个用户拥有自己的分发站，请使用仓库的 `saas` 分支（SaaS 分支 README 会单独说明其路由、超级后台和租户数据结构）。

## ✨ 核心功能

### 🧭 AppDown Admin 2.0

`/admin/` 现在默认进入 **Vue 3 + TypeScript** 管理后台，而不是把每个功能做成互相独立的 PHP 页面。视觉基准为 70% shadcn New York + 20% Linear + 10% Vercel / Stripe，并支持浅色、深色、跟随系统三种主题。

Admin 2.0 已接入真实 PHP API，而不是静态演示数据，覆盖：

- 仪表盘真实访问 / 下载趋势与来源统计
- 应用 CRUD、iOS / Android 版本、下载方式、截图上传
- 附件平台 / 版本 / 文件管理
- APK / IPA Builder、实时任务进度、构建产物重命名 / 关联附件 / 删除
- Android Keystore 创建 / 导入 / 加密密码维护
- Mobileconfig 与签名证书
- 分发首页模板、特色卡片 / 分类 / 友情链接、完整媒体库、字体、自定义代码
- 站点设置、加密备份 / 恢复、APK/IPA 包信息解析、TTF/OTF 字体名自动识别、PHP/SQLite 系统概览、Android/iOS Builder 路径与 Xcode 2FA、账户安全
- 单用户版在线升级

源码位于 `admin-ui/`，生产构建产物位于 `admin/vue/`。正式部署 / Release 已直接包含构建后的 JS/CSS，**生产服务器不需要安装 Node.js**。PHP + SQLite 继续负责认证、权限、业务逻辑、文件、Builder 与数据安全边界。

### 分发首页

- 多应用统一展示，每个应用可配置独立图标、主题色、下载按钮和轮播截图
- 后台维护应用、下载链接、版本与附件，无需修改 HTML
- 响应式手机 / 桌面布局
- 微信、QQ、微博、抖音等应用内浏览器打开提示
- 页面访问 / 下载点击统计
- 自定义 Logo、Favicon、背景、字体、公告、特色卡片和友情链接
- 自定义 CSS / JavaScript 与内置页面特效

### 🎨 分发首页模板 2.0

这里的模板是用户真正看到的 **App 分发页布局**。2.0 不再只是覆盖颜色：模板可以重新组织 Hero、应用选择器、下载按钮、截图、统计、特色卡片等 DOM 与组件，同时继续共用同一套下载、轮播、统计、浏览器检测与安全逻辑。

后台进入 Admin 2.0 的“页面模板”，当前内置 9 套：

| 模板 | 结构特点 |
|---|---|
| 经典 | 保留 AppDown 原始居中分发结构，作为兼容基线 |
| 玻璃工作台 | 独立玻璃 Hero、悬浮应用选择器、分层内容区 |
| 编辑部 | 杂志式超大标题、细线导航、大留白与横向截图 |
| 开发者终端 | 深色左侧应用控制栏 + 右侧产品舞台 |
| 品牌发布 | 独立 Aurora Showcase，大品牌 Hero + 控制面板 + 沉浸内容舞台 |
| App Store | 左侧应用列表 + 右侧应用详情 / 下载 / 截图 |
| Bento 展示 | Hero、统计、下载、截图和特色能力组成 Bento 网格 |
| Split 产品页 | 左侧固定品牌 / 应用栏 + 右侧当前应用舞台 |
| Mobile First | 手机产品页式窄内容流、触控优先组件与纵向截图 |

结构引擎位于 `static/landing-layouts.js`，公共布局 CSS 位于 `static/landing-layouts.css`；Aurora Showcase 另有 `static/landing-showcase.css`。用户自己的 Head CSS 仍然排在内置模板 token 后面，可继续覆盖内置样式。

### 📱 iOS 分发

- 自动生成企业分发 plist
- IPA / Bundle ID / 版本信息维护
- Mobileconfig WebClip 描述文件生成
- Mobileconfig SSL 签名
- 全局 / 单应用证书
- iOS 安装引导模板
- 证书到期信息与 OCSP 检查

### ⚡ Template Builder 2.0（默认快速路线）

普通 URL → App 封装默认不再为每个任务重新跑完整 Android / Xcode 编译，而是使用仓库随版本发布的真实预编译母包：

- Android：`template.apk → APKEditor Patch / Rebuild → JKS 重签 → 签名验证`
- iOS：`template.ipa → Info.plist / config Patch → P12 + mobileprovision + zsign → 已签名 IPA`
- 支持自定义应用名、URL、图标、包名 / Bundle ID、版本号与构建号
- Android 继续使用后台创建 / 导入的 Keystore；iOS 新增 P12/PFX 身份与 Provisioning Profile 管理，并校验证书、Profile、Bundle ID 与有效期匹配关系
- 日常快速构建容器不需要 Android SDK、Gradle、macOS 或 Xcode，可运行于 `linux/amd64` 与 `linux/arm64`
- 首次只需执行一次 `sudo bash tools/setup-template-builder.sh` 安装固定的 root-owned Runner 并构建运行镜像；PHP 不获得任意 Docker Socket 权限
- 后台仍保留“完整编译 · 高级”模式：Android 走原 Gradle 工程，iOS 走原 Docker-OSX / Xcode 路线，用于模板源码 / 原生能力发生变化时重新完整编译
- 永久 `Template Builder 2.0 CI` 会从母包真实执行 Android Patch+JKS 重签与 iOS P12/Profile+zsign 重签；原 AppDown CI 继续真实验证 Gradle APK 与 Xcode archive

详细实现见 `builder/template-builder/README.md`。

### 📎 附件与版本

- 应用 → 平台 → 版本三级管理
- APK / IPA 上传与结构校验
- 安装包信息解析
- 更新日志和历史版本
- 图片库与图片分类

### 🔒 安全与备份

- CSRF
- PDO 预处理语句
- 登录防爆破
- Session 超时与修改密码后的旧 Session 失效
- Apache / Nginx 敏感目录保护
- Android Keystore 密码 AES-256-GCM 存储
- Mobileconfig 私钥 AES-256-GCM 存储
- 私钥不回传浏览器
- Gradle 构建命令行不携带签名密码
- v3 加密备份（AES-256-GCM + Argon2id / PBKDF2 fallback）
- ZIP 路径穿越 / ZIP Bomb / 异常压缩比限制
- SQLite 自动备份与保留策略

详细安全说明见 [`SECURITY.md`](SECURITY.md)，升级注意事项见 [`UPGRADE.md`](UPGRADE.md)。

## 🛠 技术栈

| 组件 | 技术 |
|---|---|
| 后端 | PHP 8.0+ |
| 数据库 | SQLite |
| 公开分发页 | 原生 JavaScript + CSS + Landing Templates 2.0 |
| 管理后台 | Vue 3 + TypeScript + Vite + Vue Router + Pinia |
| 后台图标 | `@lucide/vue`（Vite 打包） |
| 公开页图标 | Font Awesome 7.1.0（本地） |
| 默认 APK/IPA 快速构建 | Template Builder 2.0 Docker Runtime + APKEditor + Uber APK Signer + zsign |
| Android 完整编译（高级） | OpenJDK 17 + Android SDK + Gradle 8.5 |
| iOS 完整编译（高级） | Docker-OSX + Xcode + KVM |

## 🚀 安装

### 环境要求

基础运行：

- PHP 8.0+
- `pdo_sqlite`
- `fileinfo`
- Nginx 或 Apache

推荐扩展：

- `zip`：APK/IPA 结构解析、导入导出
- `openssl`：证书、Mobileconfig 签名、备份加密
- `sodium`：优先使用 Argon2id 备份 KDF
- `curl`：OCSP 查询
- `gd`：图片转换和压缩

生产部署不需要：

- MySQL
- Composer
- Node.js / npm（仅开发者重新构建 `admin-ui/` 时需要 Node 22）
- Redis

### Admin 2.0 前端开发构建

普通部署不需要 Node。只有修改 `admin-ui/` 源码时才执行：

```bash
cd admin-ui
npm ci
npm run build
```

Vite 会把生产资产写入 `admin/vue/`。GitHub CI 会重新构建并比较 SHA256，源码与提交的生产产物不一致时 CI 会失败。

### 部署代码

```bash
cd /www/wwwroot/你的域名
git clone https://github.com/nljie1103/appdown.git .
```

或从 GitHub Release / Code → Download ZIP 下载后解压到网站根目录。

### 初始化

访问：

```text
https://你的域名/install/
```

安装程序会检测环境、初始化 SQLite、创建管理员和 `install.lock`。

安装后后台：

```text
https://你的域名/admin/
```

## 🌐 Nginx / 宝塔配置

**Nginx 不读取 `.htaccess`。**

仓库提供：

```text
nginx-security.conf.example
```

宝塔用户：

```text
网站 → 设置 → 伪静态
```

把该文件中的规则加入当前站点 `server {}` 范围内，然后执行：

```bash
nginx -t
nginx -s reload
```

规则会保护：

- `/data/`
- `/includes/`
- `/tools/`
- `/android-template/`
- `/ios-template/`
- `/uploads/certs/`
- `/uploads/keystores/`
- 安装锁 / 安装日志
- `.key/.pem/.p12/.pfx/.jks/.keystore/.bks`
- 上传目录中的 PHP 文件

升级后建议直接测试：

```text
https://你的域名/data/app.db
https://你的域名/uploads/certs/test.key
https://你的域名/uploads/keystores/test.jks
https://你的域名/tools/build-worker.php
```

均应返回 403 或 404。

Apache 用户使用仓库根目录 `.htaccess`，并确保站点允许 `AllowOverride`。

## 🔐 主密钥与迁移

新版会使用：

```text
data/.secret.key
```

加密 Android Keystore 密码和 Mobileconfig 私钥。

如果直接手工复制 `app.db` 到另一台服务器，需要同步安全迁移 `.secret.key`；更推荐从后台导出带密码的 `.enc` 完整备份，在目标服务器导入。完整加密备份会在目标服务器重新使用目标主密钥保存 Secrets。

请不要把：

```text
data/.secret.key
uploads/certs/
uploads/keystores/
```

加入公开仓库或公开下载目录。

## ⚡ Template Builder 2.0 快速构建环境（推荐）

默认快速 Builder 需要 Docker，但不需要在宿主机安装 Android SDK、Gradle、macOS 或 Xcode。首次部署执行：

```bash
sudo bash tools/setup-template-builder.sh
```

该脚本会构建与当前服务器架构匹配的 Builder Runtime，并安装固定的 root-owned Runner。后台“生成应用”显示 Template Builder Ready 后即可使用快速 APK / IPA 构建。

如只想移除 Template Builder 镜像而保留 Docker、签名材料和构建产物，可使用：

```bash
sudo bash tools/uninstall-template-builder.sh
```

## 🤖 Android 完整编译环境（高级，可选）

推荐：

```bash
sudo bash tools/setup-android-env.sh
```

手动环境至少需要 JDK 17 与 Android SDK。

可通过环境变量指定：

```bash
export JAVA_HOME=/path/to/jdk
export ANDROID_HOME=/path/to/android-sdk
```

Gradle 发行包会缓存到：

```text
data/gradle-cache/gradle-8.5-bin.zip
```

构建签名密码不会再通过 `-PksPwd` / `-PksKeyPwd` 进入进程命令行。

## 🍎 iOS 完整编译环境（高级，可选）

需要：

- **x86_64 / amd64 Linux 宿主机**（Docker-OSX 不支持 ARM/aarch64 作为这条 KVM 路线的宿主）
- KVM（`/dev/kvm`）
- Docker
- 可启动并可通过 SSH 登录的 Docker-OSX/macOS
- Xcode
- 建议 ≥ 8GB 内存
- 建议预留 ≥ 50GB 磁盘

`tools/setup-ios-env.sh` 只有在 Docker、KVM、容器和 **macOS SSH 全部真实通过**后才返回成功；不会再因为“容器已经创建”就把 iOS 环境标成完成。默认 `sickcodes/docker-osx:auto` 是官方预制 Catalina CLI 镜像，适合验证自动化链路；现代 Xcode 应使用已经完成 macOS 安装并启用 SSH 的较新自定义镜像。

环境脚本：

```bash
sudo bash tools/setup-ios-env.sh
sudo bash tools/setup-ios-xcode.sh
```

后台“系统信息”也提供对应的检测 / 部署入口。

如果允许 PHP 用户触发特权脚本，只给**明确脚本路径**最小化 sudo 权限，不要给 `www-data ALL=(ALL) NOPASSWD: ALL`。

## 📂 主要目录

```text
appdown/
├── index.html                       # 分发首页渲染内核
├── api/                             # 公共 API
│   ├── config.php                   # 首页配置 + 模板 CSS
│   ├── plist.php
│   ├── mobileconfig.php
│   └── track.php
├── admin/                           # 后台
│   ├── templates.php                # 分发首页模板选择
│   ├── update.php                   # GitHub Release 在线升级
│   ├── apps.php
│   ├── attachments.php
│   ├── generate.php
│   └── api/
│       └── templates.php            # 模板 API
├── includes/
│   ├── landing_templates.php        # 首页模板目录与 CSS
│   ├── updater.php                  # 在线升级内核
│   ├── version.php                  # 当前版本 / edition
│   ├── db.php
│   ├── auth.php
│   ├── security.php
│   └── ...
├── data/                            # SQLite、缓存、主密钥、备份（禁止公网访问）
├── uploads/
│   ├── certs/                       # 禁止公网访问
│   ├── keystores/                   # 禁止公网访问
│   └── ...
├── android-template/
├── ios-template/
├── tools/
├── nginx-security.conf.example
├── SECURITY.md
├── UPGRADE.md
└── CHANGELOG.md
```

## 🔄 在线升级

从 v1.2.0 开始，单用户版后台新增：

```text
/admin/update.php
```

进入“在线升级”后会自动读取官方 GitHub 仓库 `nljie1103/appdown` 的最新正式 Release。单用户版只识别 `vX.Y.Z`，不会误装 `saas-vX.Y.Z`。

点击升级时系统会：

1. 从固定 GitHub 仓库下载对应 tag 的源码 ZIP。
2. 校验 ZIP 路径、符号链接、文件数量、大小以及 `edition/version`。
3. 自动把即将覆盖/删除的程序文件备份到 `data/update-backups/`。
4. 原子覆盖程序文件，并保留 `data/` 运行数据、`uploads/` 用户文件、`install/install.lock` 和服务器本地配置。
5. 如果升级中途失败，自动尝试恢复代码备份。
6. 记录已安装程序文件清单，后续升级可以安全清理已经从 Release 删除的旧程序文件。

在线升级要求 PHP `ZipArchive`；推荐同时启用 `curl`。公开仓库无需 GitHub Token，如频繁检查遇到 GitHub API 限流，可在服务器环境变量设置 `APPDOWN_GITHUB_TOKEN`，该值不会显示到后台。

仍然支持 Git 部署：

```bash
git pull
```

无论使用哪种更新方式，生产环境仍建议先做站点/服务器完整备份，并在升级后检查 `UPGRADE.md`、`nginx-security.conf.example`、首页、APK/IPA 上传与备份。

## 🌿 分支说明

| 分支 | 用途 |
|---|---|
| `main` | 单用户 / 单分发站版本 |
| `saas` | 多租户版本：根欢迎页、`/super` 超级后台、`/用户名` 独立分发站 |

从 **v1.3.1** 开始两条 Release 线使用相同数字版本：同一批功能/修复同时发布为 `vX.Y.Z` 与 `saas-vX.Y.Z`。例如本次为 `v1.3.1` / `saas-v1.3.1`；tag 前缀继续区分 edition，数字版本保持同步。

开发过程中的 `agent/*` 分支只是临时工作分支，正式发布后会清理，不应作为部署分支使用。

## 📄 License

MIT License

---

如果发现安全问题，请先查看 [`SECURITY.md`](SECURITY.md) 中的报告建议，不要在公开 Issue 中粘贴私钥、证书密码、数据库或敏感日志。
