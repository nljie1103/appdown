# AppDown - APP下载中心

自托管、全后台可配置的APP下载落地页，带完整管理后台。基于 PHP + SQLite 构建，零外部依赖，开箱即用。

## 功能特性

- **全后台管理** — 所有内容通过管理面板配置，无需修改代码
- **多应用支持** — 可添加无限个应用，每个应用独立配置图标、主题色、下载按钮、轮播截图
- **iOS 安装引导** — 自动生成 iOS 企业证书安装引导页（支持微信/QQ打开Safari提示）
- **访问统计** — 页面访问量、各应用/平台下载次数、来源追踪、7天趋势图表
- **自定义代码** — 通过后台注入统计脚本、自定义 CSS/JS
- **字体管理** — 上传自定义字体或使用系统内置字体
- **特色卡片 & 友情链接** — 拖拽排序的内容模块
- **响应式设计** — 移动端优先，适配所有设备
- **安全防护** — CSRF保护、预处理语句、文件上传校验、输入过滤

## 技术栈

| 组件 | 技术 |
|------|------|
| 后端 | PHP 8.0+（无框架、无 Composer） |
| 数据库 | SQLite（单文件，零配置） |
| 前端 | 原生 JS + CSS（无构建步骤） |
| 管理面板 | 自定义 CSS + Chart.js (CDN) |
| 图标 | Font Awesome 7.1.0（本地） |

## 项目结构

```
appdown/
├── index.html              # 前端主页（通过API动态加载配置）
├── privacy.php             # 隐私政策（动态读取站点名称）
├── terms.php               # 用户协议（动态读取站点名称）
├── style.css               # 协议页面公共样式
├── install/                # 安装程序
│   └── index.php           # 安装脚本（安装后自动锁定）
│
├── api/                    # 公共API
│   ├── config.php          # GET: 返回完整站点配置JSON（带缓存）
│   └── track.php           # POST: 访问/下载事件追踪
│
├── ios/
│   └── index.php           # iOS安装引导页（/ios/?app=应用标识）
│
├── includes/               # PHP公共库（禁止Web访问）
│   ├── db.php              # SQLite连接 + 建表
│   ├── auth.php            # 会话认证
│   ├── csrf.php            # CSRF令牌校验
│   ├── helpers.php         # 工具函数
│   ├── upload.php          # 文件上传处理
│   ├── init.php            # 引导文件
│   └── layout.php          # 后台页面布局
│
├── admin/                  # 管理后台
│   ├── login.php           # 登录页
│   ├── dashboard.php       # 统计仪表盘
│   ├── apps.php            # 应用列表管理
│   ├── app-edit.php        # 应用编辑（下载按钮+轮播���+iOS配置）
│   ├── settings.php        # 站点设置
│   ├── features.php        # 特色卡片
│   ├── links.php           # 友情链接
│   ├── fonts.php           # 字体管理
│   ├── custom-code.php     # 自定义代码注入
│   ├── api/                # 后台AJAX接口
│   └── assets/             # 后台CSS/JS资源
│
├── static/                 # 静态资源
│   └── fontawesome-free-7.1.0-web/
│
├── data/                   # SQLite数据库（自动创建）
│   └── app.db
│
└── uploads/                # 用户上传文件
    ├── images/
    ├── fonts/
    └── apps/
```

## 快速开始

### 环境要求

- PHP 8.0+ 且启用 `pdo_sqlite` 和 `fileinfo` 扩展
- Nginx 或 Apache
- **无需** MySQL、Composer、Node.js

### 安装步骤

1. **上传**项目文件到服务器网站根目录

2. **创建必要目录**（如不存在）：
   ```bash
   mkdir -p data uploads/images uploads/fonts uploads/apps
   chmod 755 data uploads
   ```

3. **运行安装程序** — 浏览器访问 `https://你的域名/install/`
   - 自动创建数据库并导入示例数据
   - 设置管理员账号和站点名称
   - 安装完成后自动生成 `install.lock` 锁定文件，无需手动删除

4. **登录后台** — 访问 `https://你的域名/admin/`

### Nginx 安全规则

在 Nginx 配置的 server 块中添加：

```nginx
# 禁止访问数据库、公共库和安装锁定文件
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
```

### 宝塔面板用户

详见 [DEPLOY.md](DEPLOY.md) 宝塔面板部署教程。

## 后台功能

| 页面 | 功能 |
|------|------|
| 仪表盘 | 今日访问/下载量、7天趋势图、来源TOP10 |
| 应用管理 | 添加/编辑/删除/排序应用，管理下载按钮和轮播截图 |
| 应用编辑 | 配置下载链接、截图、iOS安装引导页 |
| 站点设置 | 站点名称、Logo、公告、统计数字、轮播间隔 |
| 特色卡片 | 管理首页特色亮点卡片（拖拽排序） |
| 友情链接 | 管理页脚友情链接（拖拽排序） |
| 字体管理 | 上传自定义字体或选择系统字体 |
| 自定义代码 | 在 head 或 footer 注入自定义 CSS/JS（如统计脚本） |

## API 接口

### 公共接口

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/config.php` | 返回完整站点配置JSON |
| POST | `/api/track.php` | 记录访问/下载事件 |

### 后台接口（需登录 + CSRF令牌）

| 路径 | 方法 | 说明 |
|------|------|------|
| `/admin/api/apps.php` | GET/POST/PUT/DELETE | 应用增删改查 |
| `/admin/api/downloads.php` | GET/POST/PUT/DELETE | 下载按钮增删改查 |
| `/admin/api/images.php` | GET/POST/DELETE | 轮播图增删改查 |
| `/admin/api/settings.php` | GET/POST | 站点设置读写 |
| `/admin/api/features.php` | GET/POST/PUT/DELETE | 特色卡片增删改查 |
| `/admin/api/links.php` | GET/POST/PUT/DELETE | 友情链接增删改查 |
| `/admin/api/custom-code.php` | GET/POST | 自定义代码读写 |
| `/admin/api/upload.php` | POST | 文件上传（图片/字体/安装包） |
| `/admin/api/reorder.php` | POST | 拖拽排序 |
| `/admin/api/dashboard.php` | GET | 仪表盘统计数据 |

## 本地开发

项目基于 PHP，本地开发需要 PHP 环境：

**方式一：PHP 内置服务器**（推荐）
```bash
cd /path/to/appdown
php -S localhost:8000
```
然后访问 `http://localhost:8000`

**方式二：VS Code + PHP Server 扩展**
1. 安装 [PHP](https://www.php.net/downloads)
2. 安装 [PHP Server](https://marketplace.visualstudio.com/items?itemName=brapifra.phpserver) VS Code 扩展
3. 右键 `index.html` → "PHP Server: Serve Project"

**方式三：Docker**
```bash
docker run -d -p 8080:80 -v $(pwd):/var/www/html php:8.2-apache
```

## 上传限制

| 类型 | 大小限制 | 允许格式 |
|------|---------|---------|
| 图片 | 5 MB | jpg, jpeg, png, gif, webp, svg, ico |
| 字体 | 10 MB | ttf, woff, woff2, otf |
| 安装包 | 200 MB | apk, ipa, exe, dmg, zip |

## 开源协议

[MIT](LICENSE)
