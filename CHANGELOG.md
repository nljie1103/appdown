# Changelog

## saas-v1.1.1 - 2026-08-11

在线升级维护补丁。

- 将 `data/update-backups/`、GitHub Release 缓存、安装文件清单和升级锁加入 `.gitignore`，避免 Git 部署站点在线升级后出现运行时文件未跟踪提示。
- SaaS 运行逻辑与 saas-v1.1.0 保持一致。
- 本版本用于实际验证 saas-v1.1.0 → saas-v1.1.1 的 GitHub 在线下载与代码覆盖升级链路。

## saas-v1.1.0 - 2026-08-11

新增由超级管理员控制的平台在线升级能力。

### Online Update

- 新增 `/super/update.php`，只有超级管理员可以更新整个平台。
- SaaS 只识别 `saas-vX.Y.Z` 正式 Release，绝不会把单用户 `vX.Y.Z` 当成更新。
- 升级前自动备份平台程序代码，保留 `data/saas.db`、`data/tenants/`、租户主密钥、所有租户上传文件和安装锁。
- Release ZIP 校验 edition/version、路径穿越、符号链接、数量、大小与异常压缩比。
- 使用同目录临时文件 + rename 覆盖代码，失败时尝试自动回滚。
- 新增安装清单，后续升级可安全删除新 Release 已移除的旧程序文件。
- 新增 `tests/smoke_updater.php`，离线验证 SaaS Release 过滤、数据保护和 ZIP 安全。
- README 与 GitHub CI 同步更新。

## saas-v1.0.1 - 2026-08-11

测试与文档补丁版本，不改变 SaaS 运行时业务逻辑。

### Validation

- 修复 SaaS 分支 `tests/smoke_templates.php` 单独运行时缺少租户上下文却可能以状态码 0 提前退出的问题。
- SaaS 下运行 `smoke_templates.php` 现在会委托给覆盖模板渲染、双租户 SQLite、主密钥隔离和 ZipArchive 的 `smoke_saas.php`，测试不会再假阳性。
- 在独立 PHP 8.4.23 容器中使用离线 `pdo_sqlite` / `sqlite3` / `zip` / `mbstring` / `curl` / `gd` 扩展，对 `v1.1.0` 与 `saas-v1.0.0` Release 源码重新执行原仓库 smoke test 并通过。
- README 更新真实测试环境与测试入口说明，移除已经过时的附件未挂载说明。

## saas-v1.0.0 - 2026-08-11

AppDown 首个多租户版本。保留 PHP + SQLite + 原生前端架构，在单用户版之上加入中央控制平面、独立租户分发站和完整文件/密钥隔离。

### Multi-tenant

- 根目录 `/` 改为平台欢迎页，不再直接显示某一个分发站。
- 新增 `/super` 超级后台，可创建、停用/启用、修改显示名、重置密码和永久删除租户。
- 每个租户拥有 `/<用户名>/` 独立公开分发页。
- `/<用户名>/admin` 提供租户后台登录便捷入口，实际后台代码继续复用 `/admin`。
- 租户用户名全局唯一，并保护 `admin`、`super`、`api`、`install`、`static` 等系统保留路径。

### Isolation

- 中央 `data/saas.db` 仅保存超级管理员与租户账号。
- 每个租户使用 `data/tenants/<slug>/app.db` 独立 SQLite 数据库。
- 每个租户使用独立 `data/tenants/<slug>/.secret.key` 加密签名密码和私钥。
- 上传文件隔离到 `uploads/tenants/<slug>/`。
- APK/IPA Worker 通过 `APPDOWN_TENANT=<slug>` 固定租户上下文。
- Builder 的图标/启动图读取限制在当前租户 uploads 根目录。
- Keystore、Mobileconfig、图片库、附件重命名、包解析、维护、孤儿文件扫描均改为租户路径。
- 租户证书解析不再允许读取其他租户目录或服务器任意 `/etc/ssl` 文件。

### Public routing

- Apache `.htaccess` 新增 SaaS rewrite 与嵌套租户 Secrets 防护。
- `nginx-security.conf.example` 新增 Nginx/宝塔 SaaS rewrite。
- 新增租户级 `config`、`track`、`plist`、`mobileconfig` 公共路由。
- 租户 `privacy.php` / `terms.php` 返回对应租户首页。
- `/index.html` 不再作为 SaaS 根首页使用。

### Landing Templates

- SaaS 继承单用户版 5 套公开分发首页模板：经典、玻璃拟态、极简白、午夜深色、极光渐变。
- 每个租户可独立选择模板。
- 用户自定义 Head CSS 继续在模板 CSS 后加载，拥有覆盖权。

### Backup

- 租户导出只打包当前租户数据与上传目录。
- SaaS 租户备份不包含中央超级管理员或租户登录密码。
- 备份元数据记录源租户 slug；恢复到另一个用户名时自动重写租户本地文件路径。
- 完整加密备份继续使用 AES-256-GCM + Argon2id/PBKDF2。

### Validation

- 新增 `tests/smoke_saas.php` 双租户集成测试。
- 真实创建两个租户、两个 SQLite、两套 `.secret.key` 并验证数据不串库。
- 验证一个租户不能使用自己的主密钥解密另一个租户的密文。
- 在 GitHub Ubuntu Runner 安装系统 `php-zip`，使用真实 `ZipArchive` 现场创建并验证 APK、IPA 和路径遍历恶意 ZIP fixture。
- PHP 8.0 全仓语法检查通过。

### Upgrade note

- SaaS 版建议全新/独立部署。
- 当前不提供正在运行的 `main` 单用户实例到 `saas` 的自动原地迁移器；不要直接在生产目录切分支覆盖。

## v1.1.0 - 2026-08-11

新增可视化“分发首页模板”系统，并建立最低 PHP 版本与集成冒烟测试。

### Landing Templates

- 新增 5 套分发首页模板：经典、玻璃拟态、极简白、午夜深色、极光渐变。
- 新增后台“页面模板”入口 `/admin/templates.php`，支持卡片预览与一键切换。
- 模板只覆盖公开下载首页视觉层，不改变 iOS / Android 安装页模板、应用数据、下载链接和轮播逻辑。
- 模板 CSS 通过现有配置 API / `custom_code.head_css` 注入，避免复制 `index.html` 渲染内核。
- 用户自定义 Head CSS 永远排在内置模板 CSS 后面，继续拥有最终覆盖权。
- 未设置或非法模板名自动回退到原有 `classic` 样式，旧站升级无需迁移。

### Quality

- 新增 `tests/smoke_templates.php`，验证模板目录、CSS、配置 API 注入及自定义 CSS 优先级。
- 新增 GitHub Actions CI：使用 PHP 8.0 Docker 镜像执行全仓 PHP 语法检查，并在真实 SQLite / Zip 扩展环境中执行冒烟测试。
- README 重整并补齐模板、Nginx、主密钥、备份、构建环境和 `main` / `saas` 分支说明。

## v1.0.0 - 2026-08-11

首个正式 Release，集中完成个人自建分发场景下的安全与质量加固。

### Security

- Apache 与 Nginx 安全规则对齐，补齐 `uploads/certs/`、Keystore、私钥和上传 PHP 防护。
- 新增 `nginx-security.conf.example`，可直接用于 Nginx/宝塔站点配置。
- Android Keystore 密码与 Mobileconfig 私钥改为 AES-256-GCM 存储；兼容并自动迁移旧明文数据。
- Mobileconfig `get_cert` 不再向浏览器返回私钥，编辑时空私钥表示保留原值。
- APK Gradle 构建不再把签名密码放入命令行参数，构建日志增加敏感信息脱敏。
- 登录与访问统计统一可信代理真实 IP 解析，阻止伪造 X-Forwarded-For 绕过限流。
- 修改管理员密码后使其他旧 Session 立即失效，同时保留当前会话。
- iOS Builder 改用持久化 `known_hosts` + `StrictHostKeyChecking=accept-new`。

### Upload / Backup

- APK/IPA 上传增加真实 ZIP/包结构检查，图片增加 MIME/可解析性检查，证书与 Keystore 设置独立大小限制。
- Keystore 导入增加 PKCS#12 / keytool 实际解析验证。
- 备份升级为 v3：AES-256-GCM + Argon2id/PBKDF2 KDF，文件头记录 KDF，继续兼容旧备份。
- 加入 ZIP 路径遍历、文件数量、单文件大小、总解压大小和异常压缩比检查。
- ZIP 恢复改为流式复制，避免单个文件整体载入内存。
- 应用备份自动包含 APK/IPA/Mobileconfig 历史；有密码备份可跨服务器迁移签名材料，无密码备份主动排除私钥。

### Maintenance

- 新增 `tools/maintenance.php`：SQLite 自动一致性备份、保留策略、统计/任务/日志清理。
- 新增上传孤儿文件扫描，默认只报告，显式参数才删除。
- 新增证书到期提醒数据与版本历史摘要。
- 管理员每日首次访问后台时自动触发一次轻量维护；也支持 cron。
- 新增运行时私有文件 `.gitignore` 规则。

### Compatibility

- 保持 PHP + SQLite + 原生前端架构，不增加 Redis、MySQL、Node 或额外服务依赖。
- 目标仍为 PHP 8.0+。
