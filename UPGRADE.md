# Upgrade to v1.0.0

升级不需要重建数据库，也不需要重新安装。

1. 更新代码。
2. **Nginx/宝塔用户**：把 `nginx-security.conf.example` 同步到当前站点的 `server {}` / 伪静态设置；`.htaccess` 对 Nginx 无效。
3. Apache 用户确认 `.htaccess` 可生效。
4. 登录后台并打开一次“生成应用 → 签名密钥”，并正常访问/生成一次 Mobileconfig。旧的明文 Keystore 密码和 `mc_certificates` 私钥会自动迁移为 AES-256-GCM。
5. 确认 `data/.secret.key` 已生成且不可通过 HTTP 访问。
6. 建议创建一份**带密码**的新 v3 完整备份。
7. 可手工检查维护任务：

```bash
php tools/maintenance.php
```

如果使用 Docker-OSX，第一次 IPA 构建会创建 `data/ios_known_hosts`。若之后主动重建容器导致 SSH 主机密钥改变，删除这个文件后重新构建即可。

## 回滚注意

新版本仍能读取旧版明文 Keystore 密码和 Mobileconfig 私钥，但旧版本不认识 `enc:v1:` 加密字段。因此一旦完成密钥迁移，不建议直接用旧代码连接同一份数据库。回滚前请先使用 v1.0.0 创建备份。
