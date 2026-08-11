# Changelog

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
