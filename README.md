# AppDown - APP下载中心

自托管、全后台可配置的APP下载落地页，带完整管理后台。基于 PHP + SQLite 构建，零外部依赖，开箱即用。

## 功能特性

- **全后台管理** — 所有内容通过管理面板配置，无需修改代码
- **多应用支持** — 可添加无限个应用，每个应用独立配置图标、主题色、下载按钮、轮播截图
- **iOS 安装引导** — 自动生成 iOS 企业证书安装引导页（支持微信/QQ打开Safari提示）
- **访问统计** — 页面访问量、各应用/平台下载次数、来源追踪、7天趋势图表
- **自定义代码** — 通过后台注入统计脚本、自定义 CSS/JS，内置 11 种一键特效
- **内置特效** — 全屏樱花、雪花、全站灰色（悼念）、节日灯笼、欢迎弹窗、背景音乐、右键美化、禁止查看源码、粒子背景、鼠标星星拖尾、彩带背景
- **字体管理** — 上传自定义字体或使用系统内置字体
- **特色卡片 & 友情链接** — 拖拽排序的内容模块
- **响应式设计** — 移动端优先，适配所有设备
- **安全防护** — CSRF保护、预处理语句、文件上传校验、输入过滤
- **环境检测** — 安装时自动检测 PHP 版本、扩展、目录权限，不符合要求禁止安装
- **系统信息** — 后台可查看运行环境、数据库状态、PHP 扩展等完整信息
- **安装锁定** — 安装完成后自动生成 `install.lock`，防止重复安装，无需手动删除文件

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
│   ├── app-edit.php        # 应用编辑（下载按钮+轮播图+iOS配置）
│   ├── settings.php        # 站点设置
│   ├── features.php        # 特色卡片
│   ├── links.php           # 友情链接
│   ├── fonts.php           # 字体管理
│   ├── custom-code.php     # 自定义代码注入（含内置特效预设）
│   ├── system.php          # 系统信息与环境检测
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

1. **下载项目**到服务器网站根目录：

   **方式一：Git 克隆**
   ```bash
   cd /www/wwwroot/你的域名
   git clone https://github.com/nljie1103/appdown.git .
   ```

   **方式二：下载压缩包**

   点击本页面右上角绿色 **Code** 按钮 → **Download ZIP**，解压后将所有文件上传到网站根目录。

2. **打开浏览器**访问 `https://你的域名/install/`
   - 自动检测运行环境（PHP版本、扩展、目录权限），不通过会红色标示并禁止安装
   - 设置管理员账号和站点名称
   - 安装完成后自动生成 `install.lock` 锁定文件，无需手动删除
   - 如未安装直接访问首页，会提示跳转到安装页面

3. **登录后台** — 访问 `https://你的域名/admin/`

### Nginx 安全规则

在 Nginx 配置的 server 块中添加（宝塔面板：网站 → 你的站点 → 设置 → 配置文件，在 `location / { }` 之前添加）：

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

### PHP 配置

确保以下扩展已开启（宝塔面板：PHP → 设置 → 安装扩展）：
- `pdo_sqlite` — SQLite 数据库支持
- `fileinfo` — 文件 MIME 检测

如需上传大文件（如安装包），可在 PHP 配置中调整 `upload_max_filesize` 和 `post_max_size`。程序会自动读取 PHP 配置的上传限制，无额外限制。

### 验证安装

1. 访问 `https://你的域名/admin/` → 使用安装时设置的账号登录
2. 在后台添加/编辑应用、上传图片
3. 访问 `https://你的域名/` → 确认首页正常加载
4. 检查 `https://你的域名/api/config.php` → 返回 JSON 数据

### 安全建议

- 定期备份 `data/app.db` 数据库文件
- 确保 `data/` 目录不可通过 Web 访问
- 启用 HTTPS（宝塔：网站 → SSL）
- 如需重新安装，删除 `install/install.lock` 文件即可

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
| 自定义代码 | 在 head 或 footer 注入自定义 CSS/JS，内置 11 种一键特效预设 |
| 系统信息 | 环境检测（绿色对号/红色叉号）、运行环境、数据统计、PHP扩展列表 |

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

## 开源协议

[MIT](LICENSE)
