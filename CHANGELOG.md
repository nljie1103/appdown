# Changelog

## Unreleased - Admin 2.0 / Landing Templates 2.0

- 管理后台直接升级为 Vue 3 + TypeScript + Vite SPA；`/admin/` 与登录成功后的默认入口进入 Admin 2.0。
- Admin 2.0 接入真实 PHP API，覆盖应用、附件、APK/IPA Builder、构建产物、Keystore、Mobileconfig、模板、内容组件、字体、设置、自定义代码、备份、系统、账户与在线升级。
- Vue Router 路由懒加载，生产资产提交在 `admin/vue/`；生产服务器无需 Node.js。
- 新增浅色 / 深色 / 跟随系统主题与 shadcn New York + Linear + Vercel/Stripe 风格 Design System。
- 分发首页模板升级为 9 套真实结构布局，不再仅通过 CSS 换色；Aurora 使用独立 Showcase 结构和样式。
- `api/config.php` 将应用下载、截图、特色卡片从 N+1 查询改为批量查询并分组。
- CI 新增 Vue TypeScript/Vite 可重复构建、Admin 2.0 smoke、结构模板 smoke 与 Nginx 配置语法检查。

## v1.2.1 - 2026-08-11

在线升级维护补丁。

- 将 `data/update-backups/`、GitHub Release 缓存、安装文件清单和升级锁加入 `.gitignore`，避免 Git 部署站点在线升级后出现运行时文件未跟踪提示。
- 运行时逻辑与 v1.2.0 保持一致。
- 本版本用于实际验证 v1.2.0 → v1.2.1 的 GitHub 在线下载与代码覆盖升级链路。

## v1.2.0 - 2026-08-11

新增官方 GitHub Release 在线升级功能。

### Online Update

- 后台新增 `/admin/update.php` 在线升级栏目。
- 固定同步 `nljie1103/appdown`，单用户版只识别 `vX.Y.Z` 正式 Release。
- 下载源码 ZIP 后校验 edition/version、路径穿越、符号链接、文件数量、单文件/总大小和异常压缩比。
- 升级前自动备份程序文件到 `data/update-backups/`，失败时尝试自动回滚。
- 明确保留 `data/` 运行数据、`uploads/` 用户文件、安装锁与服务器本地配置。
- 引入安装文件清单，完成一次在线升级后可在后续版本安全清理已经从 Release 删除的旧程序文件。
- 新增 `tests/smoke_updater.php`，离线验证版本线隔离、程序文件替换、运行数据保护和恶意 ZIP 拒绝。
- README 与 CI 同步更新。

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
