<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.0+">
  <img src="https://img.shields.io/badge/Database-SQLite-003B57?style=flat-square&logo=sqlite&logoColor=white" alt="SQLite">
  <img src="https://img.shields.io/badge/Framework-None-green?style=flat-square" alt="No Framework">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=flat-square" alt="MIT License">
</p>

<h1 align="center">AppDown</h1>
<p align="center"><strong>自托管 APP 下载落地页 · 全后台可配置 · 开箱即用</strong></p>
<p align="center">基于 PHP + SQLite，零外部依赖，无需 Composer / Node.js / MySQL</p>

---

## ✨ 功能一览

<table>
<tr>
<td width="50%">

**🏢 后台管理**
- 全可视化配置，无需改代码
- 多应用管理，独立图标 / 主题色 / 下载按钮
- 拖拽排序，实时预览

**📱 iOS 企业签分发**
- 自动生成 plist 文件
- 双模板可选（毛玻璃 / 仿 App Store）
- 内置安装图文教程引导
- 微信 / QQ 自动提示跳转 Safari

**📊 数据统计**
- 页面访问量 / 下载次数
- 来源追踪 / 7天趋势图

</td>
<td width="50%">

**🎨 高度自定义**
- 自定义代码注入（CSS / JS）
- 11 种内置一键特效（樱花、雪花、灯笼、粒子等）
- 节日欢迎弹窗（22个中国节日 + 自定义祝福语）
- 背景音乐播放
- 自定义字体上传（自动识别字体名称）
- 特色卡片（分类管理、FA图标/自定义图片图标）
- 友情链接（支持图标：FA图标/自定义图片、按链接开关显示）

**📎 附件管理**
- 按应用 → 平台 → 版本三级管理
- 拖拽上传 + XHR 进度条
- 文件自动命名（应用名-版本号）
- **安装包详细信息解析**（APK / IPA）：签名信息、版本号、包名 / Bundle ID、证书有效期、权限列表等
- 公共图片库（分类管理、备注、一键复制链接）

**🔒 安全防护**
- CSRF 保护 + 预处理语句
- 登录防爆破（5次锁定15分钟）
- 可选算术验证码
- 安装指纹机制，重装后旧会话自动失效
- 环境检测，安装锁定

</td>
</tr>
</table>

## 🛠 技术栈

| 组件 | 技术 |
|:---|:---|
| 后端 | PHP 8.0+（无框架、无 Composer） |
| 数据库 | SQLite（单文件，零配置） |
| 前端 | 原生 JS + CSS（无构建步骤） |
| 管理面板 | 自定义 UI + Chart.js (CDN) |
| 图标 | Font Awesome 7.1.0（本地） |

## 🚀 快速开始

### 环境要求

- PHP 8.0+ 且启用 `pdo_sqlite` 和 `fileinfo` 扩展
- 导入导出功能需启用 `zip` 扩展
- 安装包解析功能需启用 `zip` 和 `openssl` 扩展
- Nginx 或 Apache
- **无需** MySQL、Composer、Node.js

### 一键部署

**方式一：Git 克隆**
```bash
cd /www/wwwroot/你的域名
git clone https://github.com/nljie1103/appdown.git
```

**方式二：下载压缩包**

点击页面右上角绿色 **Code** → **Download ZIP**，解压后将所有文件上传到网站根目录。

### 开始安装

1. 浏览器访问 `https://你的域名/install/`
2. 系统自动检测环境（PHP版本、扩展、目录权限），不通过会红色标示
3. 设置管理员账号和站点名称
4. 安装完成自动锁定，无需手动删除文件

> 💡 安装后访问 `https://你的域名/admin/` 进入后台管理

## 📂 项目结构

```
appdown/
├── index.html              # 前端主页（API动态加载）
├── install/                # 安装程序（安装后自动锁定）
├── api/                    # 公共API
│   ├── config.php          #   站点配置JSON（带缓存）
│   ├── plist.php           #   iOS plist动态生成
│   └── track.php           #   访问/下载事件追踪
├── ios/                    # iOS安装引导页
│   ├── index.php           #   路由（根据模板分发）
│   ├── template-modern.php #   毛玻璃风格模板
│   ├── template-classic.php#   仿App Store模板
│   └── static/             #   安装教程截图
├── includes/               # PHP公共库（禁止Web访问）
├── admin/                  # 管理后台
│   ├── dashboard.php       #   统计仪表盘
│   ├── apps.php            #   应用列表
│   ├── app-edit.php        #   应用编辑（下载+轮播+iOS）
│   ├── attachments.php     #   附件管理 + 公共图片库
│   ├── features.php        #   特色卡片（分类管理）
│   ├── links.php           #   友情链接（图标管理）
│   ├── fonts.php           #   字体管理
│   ├── settings.php        #   站点设置
│   ├── custom-code.php     #   自定义代码 + 特效配置
│   ├── backup.php          #   数据导入导出
│   ├── system.php          #   系统信息
│   └── api/                #   后台AJAX接口
├── static/                 # 静态资源（FontAwesome）
├── data/                   # SQLite数据库（自动创建）
└── uploads/                # 用户上传文件
```

## 🖥 后台功能

| 页面 | 说明 |
|:---|:---|
| 📊 仪表盘 | 今日访问/下载量、7天趋势图、来源 TOP10 |
| 📱 应用管理 | 添加/编辑/排序应用，支持自定义图标（FA图标或上传图片） |
| ✏️ 应用编辑 | 下载按钮（图标可自定义）、轮播截图、iOS安装页配置 |
| 📎 附件管理 | 按平台分类管理安装包，拖拽上传带进度条，公共图片库 |
| ⭐ 特色卡片 | 首页亮点卡片，分类管理，FA图标/自定义图片图标 |
| 🔗 友情链接 | 页脚链接管理，支持图标（FA/自定义图片），按链接开关图标显示 |
| 🔤 字体管理 | 上传自定义字体（自动识别字体名称）或选择系统字体 |
| ⚙️ 站点设置 | 站名/Logo/公告/统计数字/轮播间隔/登录验证码开关 |
| 🎭 特效配置 | 11种内置特效（参数可调）、节日欢迎弹窗、背景音乐 |
| 💻 自定义代码 | head/footer 注入 CSS/JS |
| 💾 导入导出 | 按数据类别选择性备份，支持 AES-256-GCM 加密，含上传文件 |
| 🖧 系统信息 | 运行环境检测、数据库状态、PHP扩展一览 |

## 🔧 Nginx 安全规则

在 Nginx server 块中添加（宝塔面板：网站 → 设置 → 伪静态）：

```nginx
# 禁止访问数据库和公共库
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

## 📡 API 接口

### 公共接口

| 方法 | 路径 | 说明 |
|:---|:---|:---|
| GET | `/api/config.php` | 返回完整站点配置 JSON |
| GET | `/api/plist.php?app=slug` | 动态生成 iOS 安装 plist |
| POST | `/api/track.php` | 记录访问 / 下载事件 |

### 后台接口（需登录 + CSRF）

| 路径 | 方法 | 说明 |
|:---|:---|:---|
| `/admin/api/apps.php` | CRUD | 应用管理 |
| `/admin/api/downloads.php` | CRUD | 下载按钮 |
| `/admin/api/images.php` | CRUD | 轮播图 |
| `/admin/api/attachments.php` | CRUD | 附件平台分类 |
| `/admin/api/attachment-files.php` | POST/DELETE | 附件文件上传删除 |
| `/admin/api/package-info.php` | GET | 安装包详细信息解析（APK/IPA） |
| `/admin/api/features.php` | CRUD | 特色卡片 + 分类 |
| `/admin/api/links.php` | CRUD | 友情链接 |
| `/admin/api/image-library.php` | CRUD | 公共图片库 |
| `/admin/api/fonts.php` | CRUD | 字体管理 |
| `/admin/api/settings.php` | GET/POST | 站点设置 |
| `/admin/api/custom-code.php` | GET/POST | 自定义代码 |
| `/admin/api/backup.php` | POST | 数据导入导出 |
| `/admin/api/upload.php` | POST | 文件上传 |
| `/admin/api/reorder.php` | POST | 拖拽排序 |

## 💡 常见操作

| 操作 | 方法 |
|:---|:---|
| 重新安装 | 删除 `install/install.lock` 后访问 `/install/` |
| 修改上传限制 | 修改 PHP 配置的 `upload_max_filesize` 和 `post_max_size` |
| 备份数据 | 后台「导入导出」页面，支持选择性导出 + 加密 |
| 手动备份 | 复制 `data/app.db` 和 `uploads/` 目录 |
| 开启验证码 | 后台「站点设置 → 安全设置」开启 |

## 📜 开源协议

[MIT](LICENSE) — 自由使用，欢迎 Star ⭐
