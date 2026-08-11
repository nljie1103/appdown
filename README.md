<p align="center">
  <img src="https://img.shields.io/badge/Edition-SaaS-6D5DFB?style=flat-square" alt="SaaS Edition">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.0+">
  <img src="https://img.shields.io/badge/Database-SQLite-003B57?style=flat-square&logo=sqlite" alt="SQLite">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=flat-square" alt="MIT">
</p>

<h1 align="center">AppDown SaaS</h1>
<p align="center"><strong>一个安装实例 · 多个独立 IPA / APK 分发站 · 超级后台统一管理</strong></p>
<p align="center">仍然保持 PHP + SQLite + 原生 JavaScript/CSS，不引入 MySQL、Redis、Node.js 或 Composer</p>

---

> 你现在看到的是 **`saas` 多租户分支**。如果只需要自己搭一个分发站，请使用 [`main`](../../tree/main) 单用户版。

## SaaS 版是什么

AppDown SaaS 在单用户版完整能力之上增加了租户层。平台根域名不再直接展示某一个人的应用，而是作为平台欢迎页；超级管理员创建租户后，每个租户获得自己独立的分发页面、后台数据、SQLite 数据库、上传目录与加密主密钥。

典型访问结构：

```text
https://appdown.com/                 平台欢迎页
https://appdown.com/super/           超级后台
https://appdown.com/admin/           租户统一后台登录
https://appdown.com/leon/            leon 的公开分发页
https://appdown.com/leon/admin       leon 后台登录便捷入口
https://appdown.com/alice/           alice 的公开分发页
```

`/用户名/admin` 只是便捷入口，会进入共享的 `/admin` 后台代码；登录成功后 Session 固定绑定对应租户，不能通过 query 参数切换到别的租户。

## 🔐 多租户隔离方式

AppDown SaaS 没有给原来几十张业务表机械地增加 `tenant_id`，而是利用项目本身低依赖、SQLite 的优势，让每个租户拥有独立数据库和文件空间。

```text
data/
├── saas.db                         # 中央控制库：超级管理员、租户账号
└── tenants/
    ├── leon/
    │   ├── app.db                  # leon 独立业务数据库
    │   ├── .secret.key             # leon 独立 AES 主密钥
    │   └── ...缓存/备份/日志
    └── alice/
        ├── app.db
        └── .secret.key

uploads/
└── tenants/
    ├── leon/
    │   ├── images/
    │   ├── apps/
    │   ├── apks/
    │   ├── ipas/
    │   ├── mobileconfigs/
    │   ├── certs/                  # 禁止公网访问
    │   └── keystores/              # 禁止公网访问
    └── alice/
        └── ...
```

因此：

- A 租户查询的是 A 自己的 SQLite，不会通过漏写 `WHERE tenant_id = ?` 读到 B 的业务表。
- APK / IPA / 图片 / Mobileconfig / Keystore 均写入当前租户目录。
- 每个租户拥有独立 `.secret.key`；正常情况下 B 租户不能解密 A 租户密文。
- APK/IPA Builder 通过 `APPDOWN_TENANT=<slug>` 继承固定租户上下文。
- 构建时图标、启动图等源文件只允许来自当前租户 uploads 目录。
- 包信息解析、备份、维护、孤儿文件扫描均限制在当前租户目录。

## 👑 超级后台 `/super`

首次安装时创建的是 **超级管理员**，不是普通租户。

超级后台支持：

- 创建租户
- 查看全部租户
- 查看启用 / 停用状态
- 修改租户显示名称
- 重置租户密码
- 停用 / 重新启用租户
- 直接打开租户公开页
- 进入租户登录页
- 查看租户磁盘占用
- 永久删除租户及其数据库、主密钥、上传文件

永久删除要求再次输入完整租户用户名确认。

### 用户名 / Slug 规则

租户用户名同时是公开 URL，所以采用严格规则：

```text
3 - 32 位
小写字母 a-z
数字 0-9
下划线 _
短横线 -
首字符必须是字母或数字
```

例如：

```text
leon
company_01
app-team
```

系统路径不能被注册成用户名，例如：

```text
admin
super
api
install
static
uploads
data
tools
ios
android
```

当前版本**普通租户不能自己改用户名**。用户名同时涉及公开 URL、目录、缓存和数据路径，统一由超级管理员控制可以避免错误迁移。超级后台当前提供显示名称和密码管理；如果未来加入“改 slug”，应作为专门的数据迁移操作实现，而不是普通字段编辑。

## 👤 租户后台 `/admin`

每个租户继续拥有单用户版的大部分后台能力：

- 应用管理
- 下载按钮
- 轮播截图
- iOS plist
- Mobileconfig
- APK / IPA 文件与版本
- 附件管理
- APK / IPA WebView 封装
- 特色卡片
- 友情链接
- 图片库
- 字体
- 背景与特效
- 自定义 CSS / JavaScript
- 页面统计
- 备份 / 恢复
- 账户密码
- 分发首页模板

租户自己的密码在 `/admin/account.php` 修改；修改后其他旧 Session 自动失效。

## 🎨 每个租户独立选择分发首页模板

这里的“页面模板”是公开下载页面的视觉风格，不是 Android/iOS 安装页模板。

后台：

```text
/admin/templates.php
```

内置 5 套：

| 模板 | 风格 |
|---|---|
| 经典 | AppDown 原始浅色渐变、圆角卡片 |
| 玻璃拟态 | 透明玻璃、柔光、悬浮层次 |
| 极简白 | 留白、细边框、内容优先 |
| 午夜深色 | 深色开发者风格 |
| 极光渐变 | 高饱和渐变与品牌感玻璃卡片 |

模板只覆盖视觉层，所有模板共用同一套下载、轮播、统计和浏览器检测逻辑。用户自己的 Head CSS 排在模板 CSS 后面，仍可覆盖内置样式。

## 📱 IPA / APK 分发与封装

SaaS 分支继续保留：

- 企业 OTA plist 动态生成
- IPA / Bundle ID / 版本维护
- Mobileconfig WebClip
- Mobileconfig SSL 签名
- 租户独立证书与私钥
- URL → Android WebView APK
- URL → iOS WKWebView IPA
- Android Keystore 创建 / 导入
- APK / IPA 构建任务与历史
- APK / IPA 真实 ZIP 结构校验

### Builder 隔离

构建环境（Android SDK / Gradle / Docker-OSX）可以共享，**业务数据不能共享**：

```text
共享：JDK、Android SDK、Gradle cache、Docker-OSX/Xcode 环境
独立：租户 SQLite、Keystore、证书、输入图片、APK/IPA 结果、构建日志
```

## 🚀 全新安装

### 环境要求

基础：

- PHP 8.0+
- `pdo_sqlite`
- `fileinfo`
- Nginx 或 Apache

强烈推荐：

- `openssl`
- `zip` / `ZipArchive`
- `mbstring`
- `sodium`
- `curl`
- `gd`

Ubuntu / Debian 示例：

```bash
sudo apt update
sudo apt install php-cli php-sqlite3 php-zip php-mbstring php-curl php-gd
```

### 拉取 SaaS 分支

```bash
git clone -b saas https://github.com/nljie1103/appdown.git /www/wwwroot/appdown
cd /www/wwwroot/appdown
```

然后访问：

```text
https://你的域名/install/
```

安装器只创建：

```text
data/saas.db
超级管理员
install/install.lock
```

安装成功后：

```text
https://你的域名/super/
```

在超级后台创建第一个租户。

## ⚠️ 不要直接把正在运行的 `main` 单用户站切换成 `saas`

当前 SaaS 版的安装模型、根路由、账号模型和数据路径都与单用户版不同。因此对已经投入使用的 `main` 实例，**不建议直接在原网站执行：**

```bash
git switch saas
git pull
```

然后期望自动无损变成 SaaS。

推荐路线：

1. 保留现有单用户站及完整备份。
2. 新域名 / 子域名 / 新目录部署 `saas` 分支。
3. 初始化超级管理员。
4. 创建目标租户。
5. 将需要迁移的站点数据通过租户备份方式导入并检查文件路径。
6. 完成测试后再切 DNS / 域名。

在正式提供自动“main → SaaS”迁移器之前，不要把生产站当作原地升级测试环境。

## 🌐 Nginx / 宝塔：必须配置 SaaS 路由

**Nginx 不读取 `.htaccess`。**

SaaS 分支的：

```text
nginx-security.conf.example
```

已经包含多租户 rewrite 与嵌套 Secrets 防护。

### 宝塔直接这样配置

```text
网站 → 设置 → 伪静态
```

如果这个站点没有你自己额外写的伪静态规则，可以直接把 `nginx-security.conf.example` **文件里面的全部内容**复制到宝塔“伪静态”文本框中；不要再额外套一层 `server {}`。宝塔会把该文件作为当前站点 `server {}` 内的一段配置 include 进去。

如果已经有自己的 rewrite / location，请把 AppDown 规则合并进去，避免覆盖现有业务规则。

保存时宝塔会自动执行 Nginx 配置检查；也建议在 SSH 再执行：

```bash
/www/server/nginx/sbin/nginx -t
```

成功时应看到：

```text
syntax is ok
test is successful
```

然后重载 Nginx。

> **saas-v1.1.1 已知问题：** 旧版示例中四条含 `{2,31}` 的 `rewrite` 正则没有加引号，在常见 Nginx 版本会报 `directive "rewrite" is not terminated by ";"`（通常指向第 63 行）。该问题已在 **saas-v1.1.2** 修复；新示例把整段 rewrite 正则用双引号包裹，并且 CI 会真实运行 `nginx -t`。

主要路由包括：

```text
/<slug>/                              -> tenant.php
/<slug>/api/config.php               -> /api/config.php?tenant=<slug>
/<slug>/api/track.php                -> /api/track.php?tenant=<slug>
/<slug>/api/plist.php                -> /api/plist.php?tenant=<slug>
/<slug>/api/mobileconfig.php         -> /api/mobileconfig.php?tenant=<slug>
/<slug>/admin                        -> /admin/login.php?tenant=<slug>
/<slug>/privacy.php                  -> /privacy.php?tenant=<slug>
/<slug>/terms.php                    -> /terms.php?tenant=<slug>
```

并禁止：

```text
/data/
/uploads/tenants/*/certs/
/uploads/tenants/*/keystores/
/includes/
/tools/
安装锁与日志
上传目录 PHP
.key/.pem/.p12/.pfx/.jks/.keystore/.bks
```

### Apache

SaaS 路由与安全规则已经写在根 `.htaccess`。请确保 Apache 启用了 `mod_rewrite`，并允许对应目录使用 `AllowOverride`。

## 💾 SaaS 备份规则

租户后台的备份是**租户级备份**：

包含：

- 当前租户应用与版本
- 站点配置
- 页面模板
- 图片 / 安装包等上传文件（按选项）
- Keystore / Mobileconfig 签名材料（仅完整加密备份）

不包含：

- 超级管理员账号
- `data/saas.db` 中的租户密码
- 其他租户数据

原因是登录账号属于中央控制平面，由 `/super` 管理。

完整 `.enc` 备份仍使用 AES-256-GCM + Argon2id/PBKDF2，并支持恢复到另一个租户用户名；恢复时租户本地 `uploads/tenants/<旧slug>/...` 会改写成当前租户路径。

## 🧪 测试

仓库包含两类 smoke test：

```bash
php tests/smoke_templates.php
php tests/smoke_saas.php
```

`smoke_saas.php` 会创建临时的两个租户，并实际验证：

- 两套 SQLite 数据库不混用
- 两个上传根目录不同
- 两套 `.secret.key` 不同
- B 租户不能用自己的主密钥解开 A 租户密文
- 公共配置 API 不串租户应用
- `/用户名/` 页面正确改写租户 API
- Builder / 备份 / 包解析的关键租户路径保护
- 使用真实 `ZipArchive` 创建并检查 APK fixture
- 无 `AndroidManifest.xml` 的 APK 被拒绝
- 使用真实 `ZipArchive` 创建并检查 IPA fixture
- 带 `../` 路径遍历的恶意 ZIP 被拒绝

GitHub CI 使用 PHP 8.0 Docker 对全仓 PHP 做最低版本语法检查，并在 Ubuntu Runner 安装 Nginx，对 `nginx-security.conf.example` 真实执行 `nginx -t` 配置语法检查。

> SaaS 分支中的 `tests/smoke_templates.php` 会自动委托给 `tests/smoke_saas.php`，避免在没有租户上下文时公共配置 API 提前退出却返回状态码 0 的假阳性。
>
> 开发验证还在独立 PHP 8.4.23 容器中使用离线提供的 `pdo_sqlite` / `sqlite3` / `zip` / `mbstring` / `curl` / `gd` 扩展，对正式 Release 源码重新执行了原仓库测试。


## 🔄 超级后台在线升级

从 SaaS v1.1.0 开始，平台升级入口只提供给超级管理员：

```text
/super/update.php
```

普通租户 `/admin` **没有升级整个平台的权限**。超级后台会自动同步官方 GitHub Release，并且 SaaS 只识别 `saas-vX.Y.Z`，不会误装单用户 `vX.Y.Z`。

升级流程：

1. 固定从 `nljie1103/appdown` 获取官方 Release。
2. 校验 Release tag、edition/version、ZIP 路径、符号链接、文件数量和大小。
3. 将当前平台程序文件备份到 `data/update-backups/`。
4. 覆盖平台程序代码；`data/saas.db`、`data/tenants/`、所有租户 `.secret.key`、`uploads/tenants/` 和安装锁都不会被覆盖。
5. 中途失败时尝试自动回滚代码。
6. 后续版本可根据安装清单清理已经从新 Release 删除的旧程序文件，而不会把租户运行数据当成旧代码删除。

在线升级需要 `ZipArchive`；推荐启用 `curl`。如 GitHub API 请求频率较高，可通过服务器环境变量 `APPDOWN_GITHUB_TOKEN` 提高 API 配额。

> SaaS 的平台升级属于高权限操作，`/super` 仍建议放在 Cloudflare Access、VPN 或 IP 白名单后。

## 🛡 安全建议

- `/super` 建议额外放在 Cloudflare Access、VPN 或 IP 白名单后。
- 不要把 `data/` 暴露到公网。
- 不要让 Web Server 直接提供租户 `certs/` / `keystores/`。
- 定期备份 `data/saas.db`；它保存平台租户账号。
- 租户业务数据与中央控制库需要分别考虑备份。
- Docker 权限仍属于高权限能力，不要把后台暴露给不可信管理员。
- 给 PHP 用户配置 sudo 时，只允许明确脚本，不要使用 `NOPASSWD: ALL`。

## 📂 关键文件

```text
index.php                       平台欢迎页
tenant.php                      租户公开分发页路由器
super/                          超级后台（含 update.php 平台在线升级）
admin/                          租户后台
includes/saas.php               SaaS 控制层 / 租户路径与账号
includes/updater.php            GitHub Release 在线升级内核
includes/version.php            SaaS 当前版本 / edition
includes/db.php                 按租户数据库路径缓存 PDO
includes/landing_templates.php  5 套公开页模板
api/config.php                  租户公共配置
api/plist.php                   租户 OTA plist
api/mobileconfig.php            租户 Mobileconfig
nginx-security.conf.example     Nginx/宝塔安全 + SaaS rewrite
.htaccess                       Apache 安全 + SaaS rewrite
tests/smoke_saas.php            双租户 / ZipArchive 集成测试
tests/smoke_updater.php         在线升级离线安全测试
```

## 🌿 分支

| 分支 | 用途 |
|---|---|
| `main` | 单用户 / 单分发站版 |
| `saas` | 多租户 SaaS 版 |

正式发布完成后，仓库只保留这两条长期分支；`agent/*` 为开发过程临时分支，不应部署。

## License

MIT License