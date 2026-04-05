# 部署指南 (宝塔面板 + Nginx)

## 1. 上传文件

将整个项目上传到宝塔网站根目录，如 `/www/wwwroot/ysapp.jiuliu.org/`

## 2. 创建必要目录

```bash
mkdir -p /www/wwwroot/ysapp.jiuliu.org/data
mkdir -p /www/wwwroot/ysapp.jiuliu.org/uploads/images
mkdir -p /www/wwwroot/ysapp.jiuliu.org/uploads/fonts
mkdir -p /www/wwwroot/ysapp.jiuliu.org/uploads/apps
chmod 755 /www/wwwroot/ysapp.jiuliu.org/data
chmod 755 /www/wwwroot/ysapp.jiuliu.org/uploads
```

## 3. Nginx 安全规则

在宝塔面板 → 网站 → 你的站点 → 设置 → 配置文件(Nginx)，找到 `server { ... }` 块内，在 `location / { }` 之前添加：

```nginx
# ===== 安全规则 =====

# 禁止访问数据库和includes目录
location ~* ^/(data|includes)/ {
    deny all;
    return 404;
}

# 禁止访问隐藏文件
location ~ /\. {
    deny all;
    return 404;
}

# 禁止uploads目录执行PHP
location ~* ^/uploads/.*\.php$ {
    deny all;
    return 404;
}

# 禁止访问install.php(安装完成后)
# 取消注释下面两行:
# location = /install.php {
#     deny all;
# }
```

## 4. PHP配置

在宝塔面板 → PHP 8.4 → 设置 → 配置修改，确保以下扩展已开启：
- `pdo_sqlite` (SQLite数据库)
- `fileinfo` (文件MIME检测)

可通过 PHP → 设置 → 安装扩展 中查看和安装。

上传限制配置（PHP → 设置 → 配置修改）：
```ini
upload_max_filesize = 200M
post_max_size = 210M
```

## 5. 运行安装脚本

浏览器访问：`https://你的域名/install.php`

- 首次访问会初始化数据库、种子数据、创建管理员账号
- **重要：记住页面显示的管理员密码**
- 安装成功后，**立即删除 install.php**：

```bash
rm /www/wwwroot/ysapp.jiuliu.org/install.php
```

或取消注释上面 Nginx 配置中的 `location = /install.php` 规则。

## 6. 验证

1. 访问 `https://你的域名/admin/` → 用安装时的密码登录
2. 在后台添加/编辑应用、上传图片
3. 访问 `https://你的域名/` → 确认首页从API加载
4. 检查 `https://你的域名/api/config.php` → 返回JSON数据

## 7. 安全建议

- 登录后台后，立即在 **站点设置** 中修改管理员密码（需在数据库中操作或添加修改密码功能）
- 定期备份 `data/app.db` 数据库文件
- 确保 `data/` 目录不可通过Web访问
- 启用HTTPS (宝塔 → 网站 → SSL)
