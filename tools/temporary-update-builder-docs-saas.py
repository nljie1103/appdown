from pathlib import Path


def swap(path, old, new):
    p = Path(path)
    text = p.read_text()
    if text.count(old) != 1:
        raise SystemExit(f"unexpected marker count in {path}")
    p.write_text(text.replace(old, new, 1))

readme = "README.md"
swap(readme,
     "- URL → Android WebView APK\n- URL → iOS WKWebView IPA\n- Android Keystore 创建 / 导入\n- APK / IPA 构建任务与历史",
     "- URL → Android WebView APK：默认使用 Template Builder 2.0 母包 Patch + 当前租户 JKS 重签\n- URL → iOS WKWebView IPA：默认使用 Template Builder 2.0 母包 Patch + 当前租户 P12/PFX + Provisioning Profile + zsign 重签\n- Android Keystore 与 Apple P12/PFX / Provisioning Profile 均按租户独立保存\n- APK / IPA 构建任务与历史按租户独立保存")

swap(readme,
     "构建环境（Android SDK / Gradle / Docker-OSX）可以共享，**业务数据不能共享**：",
     "Template Builder 2.0 的 Runtime 与固定 Runner 属于平台共享基础设施，由平台管理员统一维护；租户只能使用和查看状态。**租户业务数据与签名材料不能共享**：")

swap(readme,
     "共享：JDK、Android SDK、Gradle cache、Docker-OSX/Xcode 环境\n独立：租户 SQLite、Keystore、证书、输入图片、APK/IPA 结果、构建日志",
     "共享（平台维护）：Template Builder Runtime、固定 Runner、JDK、Android SDK、Gradle cache、Docker-OSX/Xcode 环境\n独立（每租户）：SQLite、.secret.key、JKS、P12/PFX、Provisioning Profile、输入图片、APK/IPA 结果、构建日志")

swap(readme,
     "```bash\nphp tests/smoke_templates.php\nphp tests/smoke_saas.php\n```",
     "```bash\nphp tests/smoke_templates.php\nphp tests/smoke_saas.php\nphp tests/smoke_template_builder.php\n```")

p = Path("CHANGELOG.md")
text = p.read_text()
marker = "# Changelog\n\n"
entry = """## Unreleased — SaaS Builder 2.0

- 租户 Builder 默认新增 Template Builder 2.0 快速路线：Android 使用母 APK + 租户 JKS 重签，iOS 使用母 IPA + 租户 P12/PFX + Provisioning Profile + zsign 重签。
- 快速 Runtime 支持 linux/amd64 与 linux/arm64；原 Gradle 与 Docker-OSX/Xcode 完整编译路线继续作为高级模式保留。
- 共享 Runtime / Runner 由平台统一维护；JKS、P12/PFX、Profile、租户数据库、输入文件与构建产物继续按租户隔离。
- 新增 SaaS Template Builder 隔离 smoke 与永久 Android/iOS 真重签 CI。
- 修复 iOS 快速签名后验证参数错误和 build number 被二次覆盖的问题。

"""
if "## Unreleased — SaaS Builder 2.0" not in text:
    if not text.startswith(marker):
        raise SystemExit("unexpected changelog header")
    p.write_text(marker + entry + text[len(marker):])
