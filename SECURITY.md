# AppDown 安全部署说明

AppDown 的定位是个人/小团队自建 IPA/APK 分发站。安全改动以“不增加复杂依赖、不改变现有 PHP + SQLite 部署方式”为原则。

## Web 服务器规则

### Apache

仓库根目录 `.htaccess` 会保护：

- `data/`、`includes/`、`tools/`、Android/iOS 模板源码
- `install/install.lock` 与安装访问日志
- `uploads/keystores/` 与 `uploads/certs/`
- 上传目录中的 PHP 文件
- `.key/.pem/.p12/.pfx/.jks/.keystore/.bks` 等敏感文件

Apache 必须允许项目目录读取 `.htaccess`（通常为 `AllowOverride All` 或至少允许 Rewrite/Options/FileInfo/AuthConfig）。

### Nginx / 宝塔

Nginx **不会读取 `.htaccess`**。将仓库中的 `nginx-security.conf.example` 内容放进该站点的 `server {}`。

宝塔面板可在：**网站 → 设置 → 伪静态** 中加入这些 `location` 规则。AppDown 本身不依赖漂亮 URL 重写；这里的“伪静态”主要用于安全访问控制。

修改后执行：

```bash
nginx -t && nginx -s reload
```

至少验证下列 URL 返回 403/404：

```text
/data/app.db
/data/.secret.key
/uploads/keystores/test.jks
/uploads/certs/test.key
/tools/build-worker.php
```

## 签名密钥

Android Keystore 的 `store_password` / `key_password` 与 `mc_certificates` 中的 Mobileconfig 私钥均使用 AES-256-GCM 保存。第一次使用密钥管理功能时会创建：

```text
data/.secret.key
```

文件权限会尝试设置为 `0600`，并已加入 `.gitignore`。也可以通过环境变量提供固定 32 字节主密钥：

```text
APPDOWN_MASTER_KEY=<32字节密钥的Base64或64位Hex>
```

不要把 `data/.secret.key` 提交到 Git。旧版数据库中的明文 Keystore 密码会在打开密钥管理页或执行 APK 构建时自动迁移；旧版 `mc_certificates` 明文私钥会在读取/使用证书时自动迁移。

## 备份与恢复

推荐给完整备份设置密码。v3 加密备份使用：

- AES-256-GCM
- Argon2id（PHP sodium 可用时）
- PBKDF2-HMAC-SHA256 600,000 次（无 sodium 时回退）

v3 文件头会记录实际 KDF，旧版 AES-GCM + SHA-256(password) 备份仍可导入。

选择“应用数据”时：

- 始终自动带上 APK/IPA/Mobileconfig 版本历史
- **有密码的 `.enc` 备份**会同时带上 Keystore 与 Mobileconfig 签名材料，可跨服务器恢复
- **无密码 ZIP**会主动省略私钥/Keystore 密码，并写入 `SECURITY-NOTICE.txt`

`tools/maintenance.php` 的每日 SQLite 快照是本机灾备。若只手工复制 `data/app.db` 到另一台服务器，同时使用了加密密钥，还需要复制原服务器的 `data/.secret.key`；更推荐使用有密码的 AppDown `.enc` 完整备份迁移。

## 反向代理与真实 IP

AppDown 默认只把 `127.0.0.1` / `::1` 视为可信反代。只有请求确实来自可信反代时，才读取 `CF-Connecting-IP`、`X-Real-IP`、`X-Forwarded-For`，防止客户端伪造头绕过登录/统计限流。

如果 PHP 看到的 `REMOTE_ADDR` 是 Cloudflare、负载均衡器或另一台反代服务器，请优先在 Nginx/Apache 正确配置 real IP；也可给 PHP-FPM 设置：

```text
APPDOWN_TRUSTED_PROXIES="10.0.0.0/8,192.168.0.0/16,你的反代CIDR"
```

不要把 `0.0.0.0/0` 或 `::/0` 配成可信代理。

## iOS Builder SSH

IPA Builder 连接本机 Docker-OSX 时使用独立的：

```text
data/ios_known_hosts
```

首次连接采用 `StrictHostKeyChecking=accept-new` 记录主机密钥；之后如果密钥变化会拒绝连接。若你主动重建了 Docker-OSX 容器，可删除该文件后重新连接。

## 日常维护

管理员每天第一次正常访问后台时，会尝试后台运行一次：

```bash
php tools/maintenance.php --quiet
```

也可自己放进 cron：

```cron
17 4 * * * cd /path/to/appdown && /usr/bin/php tools/maintenance.php --quiet
```

维护任务包括：

- 一致性 SQLite 快照，保留最近 30 份
- 访问记录 90 天、下载记录 180 天
- 登录失败记录 7 天
- 已结束构建任务 30 天
- 老 Worker/安装日志 30 天
- 上传孤儿文件扫描（默认只报告）
- 30 天内到期的 Mobileconfig 证书提醒数据
- APK/IPA/附件版本历史摘要

报告保存为：

```text
data/maintenance-report.json
```

只有显式执行下面命令时才会删除超过 7 天的孤儿文件：

```bash
php tools/maintenance.php --delete-orphans
```
