# Changelog

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
