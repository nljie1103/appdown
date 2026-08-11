from pathlib import Path
import os

edition = os.environ.get('APPDOWN_DOC_EDITION', 'main').strip().lower()
readme_path = Path('README.md')
changelog_path = Path('CHANGELOG.md')
readme = readme_path.read_text(encoding='utf-8')
changelog = changelog_path.read_text(encoding='utf-8')


def replace_section(text: str, start: str, end: str, replacement: str) -> str:
    a = text.find(start)
    if a < 0:
        raise SystemExit(f'missing section start: {start}')
    b = text.find(end, a)
    if b < 0:
        raise SystemExit(f'missing section end: {end}')
    return text[:a] + replacement.rstrip() + '\n\n' + text[b:]


admin_section_main = '''### 🧭 AppDown Admin 2.0

`/admin/` 现在默认进入 **Vue 3 + TypeScript** 管理后台，而不是把每个功能做成互相独立的 PHP 页面。视觉基准为 70% shadcn New York + 20% Linear + 10% Vercel / Stripe，并支持浅色、深色、跟随系统三种主题。

Admin 2.0 已接入真实 PHP API，而不是静态演示数据，覆盖：

- 仪表盘真实访问 / 下载趋势与来源统计
- 应用 CRUD、iOS / Android 版本、下载方式、截图上传
- 附件平台 / 版本 / 文件管理
- APK / IPA Builder、实时任务进度、构建产物重命名 / 关联附件 / 删除
- Android Keystore 创建 / 导入 / 加密密码维护
- Mobileconfig 与签名证书
- 分发首页模板、特色卡片 / 分类 / 友情链接、字体、自定义代码
- 站点设置、备份 / 恢复、Android/iOS 构建环境、账户安全
- 单用户版在线升级

源码位于 `admin-ui/`，生产构建产物位于 `admin/vue/`。正式部署 / Release 已直接包含构建后的 JS/CSS，**生产服务器不需要安装 Node.js**。PHP + SQLite 继续负责认证、权限、业务逻辑、文件、Builder 与数据安全边界。
'''

admin_section_saas = '''## 🧭 租户后台 Admin 2.0

`/admin/` 与 `/<slug>/admin` 登录后现在默认进入 **Vue 3 + TypeScript** 的 AppDown Admin 2.0。单用户版与 SaaS 租户后台共享同一套 Vue Design System 和生产构建产物，但租户身份仍完全由 PHP Session 在服务端解析，不接受浏览器传入任意 tenant 参数切换上下文。

租户 Admin 2.0 已覆盖真实业务能力：应用、下载方式、截图、附件、APK/IPA Builder、构建产物、Keystore、Mobileconfig、页面模板、内容组件、字体、站点设置、自定义代码、备份恢复、环境检测和账户安全。

源码位于 `admin-ui/`，生产构建产物位于 `admin/vue/`；正式部署不需要 Node.js。**租户后台没有整个平台的在线升级权限**，SaaS 平台更新继续只允许超级管理员通过 `/super/update.php` 执行。
'''

landing_main = '''### 🎨 分发首页模板 2.0

这里的模板是用户真正看到的 **App 分发页布局**。2.0 不再只是覆盖颜色：模板可以重新组织 Hero、应用选择器、下载按钮、截图、统计、特色卡片等 DOM 与组件，同时继续共用同一套下载、轮播、统计、浏览器检测与安全逻辑。

后台进入 Admin 2.0 的“页面模板”，当前内置 9 套：

| 模板 | 结构特点 |
|---|---|
| 经典 | 保留 AppDown 原始居中分发结构，作为兼容基线 |
| 玻璃工作台 | 独立玻璃 Hero、悬浮应用选择器、分层内容区 |
| 编辑部 | 杂志式超大标题、细线导航、大留白与横向截图 |
| 开发者终端 | 深色左侧应用控制栏 + 右侧产品舞台 |
| 品牌发布 | 独立 Aurora Showcase，大品牌 Hero + 控制面板 + 沉浸内容舞台 |
| App Store | 左侧应用列表 + 右侧应用详情 / 下载 / 截图 |
| Bento 展示 | Hero、统计、下载、截图和特色能力组成 Bento 网格 |
| Split 产品页 | 左侧固定品牌 / 应用栏 + 右侧当前应用舞台 |
| Mobile First | 手机产品页式窄内容流、触控优先组件与纵向截图 |

结构引擎位于 `static/landing-layouts.js`，公共布局 CSS 位于 `static/landing-layouts.css`；Aurora Showcase 另有 `static/landing-showcase.css`。用户自己的 Head CSS 仍然排在内置模板 token 后面，可继续覆盖内置样式。
'''

landing_saas = '''## 🎨 每个租户独立选择分发首页模板 2.0

这里的模板是用户打开 `/<slug>/` 后真正看到的 App 分发页面。2.0 不再只是换背景或颜色，而会改变 Hero、应用选择器、下载区、截图、统计和特色组件的真实结构。

每个租户可以独立选择 9 套布局：经典、玻璃工作台、编辑部、开发者终端、品牌发布、App Store、Bento 展示、Split 产品页、Mobile First。

所有模板继续共用同一份租户应用数据、下载追踪、轮播与浏览器检测逻辑；模板切换不会改变数据库中的应用、下载地址或附件。SaaS 租户页会把新布局脚本与静态资源固定从根 `/static/` 加载，避免 `/<slug>/static/...` 路径错误。用户自己的 Head CSS 仍位于内置模板样式之后。
'''

if edition == 'main':
    readme = readme.replace('  <img src="https://img.shields.io/badge/Framework-None-green?style=flat-square" alt="No Framework">', '  <img src="https://img.shields.io/badge/Admin-Vue_3-42B883?style=flat-square&logo=vuedotjs&logoColor=white" alt="Vue 3 Admin">')
    readme = readme.replace('<p align="center">PHP + SQLite + 原生 JavaScript/CSS，无 Composer / Node.js / MySQL 依赖</p>', '<p align="center">PHP + SQLite 后端 · Vue 3 + TypeScript 管理后台 · 生产环境无需 Node.js</p>')
    if '### 🧭 AppDown Admin 2.0' not in readme:
        anchor = '### 分发首页\n'
        if anchor not in readme:
            raise SystemExit('main README core feature anchor missing')
        readme = readme.replace(anchor, admin_section_main + '\n' + anchor, 1)
    readme = replace_section(readme, '### 🎨 分发首页模板', '### 📱 iOS 分发', landing_main)
    readme = readme.replace('| 前端 | 原生 JavaScript + CSS |', '| 公开分发页 | 原生 JavaScript + CSS + Landing Templates 2.0 |')
    readme = readme.replace('| 后台 | 自定义 UI + Chart.js |', '| 管理后台 | Vue 3 + TypeScript + Vite + Vue Router + Pinia |')
    readme = readme.replace('| 图标 | Font Awesome 7.1.0（本地） |', '| 后台图标 | `@lucide/vue`（Vite 打包） |\n| 公开页图标 | Font Awesome 7.1.0（本地） |')
    readme = readme.replace('不需要：\n\n- MySQL\n- Composer\n- Node.js / npm\n- Redis', '生产部署不需要：\n\n- MySQL\n- Composer\n- Node.js / npm（仅开发者重新构建 `admin-ui/` 时需要 Node 22）\n- Redis')
    build_note = '''### Admin 2.0 前端开发构建

普通部署不需要 Node。只有修改 `admin-ui/` 源码时才执行：

```bash
cd admin-ui
npm ci
npm run build
```

Vite 会把生产资产写入 `admin/vue/`。GitHub CI 会重新构建并比较 SHA256，源码与提交的生产产物不一致时 CI 会失败。

'''
    if '### Admin 2.0 前端开发构建' not in readme:
        readme = readme.replace('### 部署代码\n', build_note + '### 部署代码\n', 1)

    unreleased = '''## Unreleased - Admin 2.0 / Landing Templates 2.0

- 管理后台直接升级为 Vue 3 + TypeScript + Vite SPA；`/admin/` 与登录成功后的默认入口进入 Admin 2.0。
- Admin 2.0 接入真实 PHP API，覆盖应用、附件、APK/IPA Builder、构建产物、Keystore、Mobileconfig、模板、内容组件、字体、设置、自定义代码、备份、系统、账户与在线升级。
- Vue Router 路由懒加载，生产资产提交在 `admin/vue/`；生产服务器无需 Node.js。
- 新增浅色 / 深色 / 跟随系统主题与 shadcn New York + Linear + Vercel/Stripe 风格 Design System。
- 分发首页模板升级为 9 套真实结构布局，不再仅通过 CSS 换色；Aurora 使用独立 Showcase 结构和样式。
- `api/config.php` 将应用下载、截图、特色卡片从 N+1 查询改为批量查询并分组。
- CI 新增 Vue TypeScript/Vite 可重复构建、Admin 2.0 smoke、结构模板 smoke 与 Nginx 配置语法检查。
'''
else:
    readme = readme.replace('<p align="center">仍然保持 PHP + SQLite + 原生 JavaScript/CSS，不引入 MySQL、Redis、Node.js 或 Composer</p>', '<p align="center">PHP + SQLite 多租户后端 · Vue 3 + TypeScript 租户后台 · 生产环境无需 Node.js</p>')
    if '## 🧭 租户后台 Admin 2.0' not in readme:
        anchor = '## 🎨 每个租户独立选择分发首页模板\n'
        if anchor not in readme:
            raise SystemExit('SaaS README template anchor missing')
        readme = readme.replace(anchor, admin_section_saas + '\n' + anchor, 1)
    readme = replace_section(readme, '## 🎨 每个租户独立选择分发首页模板', '## 📱 IPA / APK 分发与封装', landing_saas)
    readme = readme.replace('租户自己的密码在 `/admin/account.php` 修改；修改后其他旧 Session 自动失效。', '租户自己的密码现在可在 Admin 2.0 的“账户管理”中修改；修改后其他旧 Session 自动失效。')
    unreleased = '''## Unreleased - SaaS Admin 2.0 / Landing Templates 2.0

- 租户 `/admin/` 直接升级为 Vue 3 + TypeScript + Vite SPA，并与单用户版共用同一套 Admin 2.0 Design System。
- 租户身份继续由服务端 Session 固定解析；Vue 不获得任意切换 tenant 的能力。
- 租户 Admin 2.0 覆盖应用、附件、APK/IPA Builder、构建产物、Keystore、Mobileconfig、9 套页面模板、内容组件、字体、设置、备份、系统与账户能力。
- SaaS 租户 capability 明确关闭整个平台在线升级；平台更新仍只允许 `/super/update.php` 的超级管理员。
- 每个租户可独立选择 9 套真实结构分发模板；租户页面同时修正新布局 JS/CSS 的 `/static/` 根路径加载。
- 租户 `api/config.php` 将应用下载、截图、特色卡片从 N+1 查询改为批量查询并保持租户路径重写。
- CI 新增 Vue TypeScript/Vite 可重复构建、Admin 2.0 smoke、结构模板 smoke 与真实 Nginx 配置语法检查。
'''

if '## Unreleased - Admin 2.0 / Landing Templates 2.0' not in changelog and '## Unreleased - SaaS Admin 2.0 / Landing Templates 2.0' not in changelog:
    if not changelog.startswith('# Changelog\n'):
        raise SystemExit('unexpected changelog header')
    changelog = '# Changelog\n\n' + unreleased.strip() + '\n\n' + changelog[len('# Changelog\n'):].lstrip('\n')

readme_path.write_text(readme, encoding='utf-8')
changelog_path.write_text(changelog, encoding='utf-8')
print(f'patched README/CHANGELOG for {edition}')
