#!/usr/bin/env python3
import glob
import json
import os
import plistlib
import re
import shutil
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path

APKEDITOR = "/opt/appdown/APKEditor.jar"
UBER_SIGNER = "/opt/appdown/uber-apk-signer.jar"
ANDROID_NS = "http://schemas.android.com/apk/res/android"
ET.register_namespace("android", ANDROID_NS)


class BuildError(RuntimeError):
    pass


def log(message):
    print(message, flush=True)


def run(args, *, cwd=None, env=None, capture=False):
    safe = []
    redacted_next = False
    for arg in [str(x) for x in args]:
        if redacted_next:
            safe.append("***")
            redacted_next = False
            continue
        safe.append(arg)
        if arg in {"--ksPass", "--ksKeyPass", "-p"}:
            redacted_next = True
    log("$ " + " ".join(safe))
    proc = subprocess.run(
        [str(x) for x in args],
        cwd=cwd,
        env=env,
        text=True,
        stdout=subprocess.PIPE if capture else None,
        stderr=subprocess.STDOUT if capture else None,
    )
    if capture and proc.stdout:
        print(proc.stdout.rstrip(), flush=True)
    if proc.returncode != 0:
        raise BuildError(f"command failed ({proc.returncode})")
    return proc.stdout or ""


def require_file(path, label):
    p = Path(path)
    if not p.is_file() or p.stat().st_size <= 0:
        raise BuildError(f"{label} missing: {path}")
    return p


def read_secret(path, label):
    p = require_file(path, label)
    return p.read_text(encoding="utf-8").rstrip("\r\n")


def validate_url(url):
    if not re.match(r"^https?://[^\s]+$", url or "", re.I):
        raise BuildError("invalid target URL")


def validate_identifier(value, label):
    if not re.match(r"^[A-Za-z][A-Za-z0-9_-]*(\.[A-Za-z0-9_-]+)+$", value or ""):
        raise BuildError(f"invalid {label}")


def safe_xml_write(tree, path):
    tree.write(path, encoding="utf-8", xml_declaration=True)


def find_one(base, patterns, label):
    hits = []
    for pattern in patterns:
        hits.extend(Path(base).glob(pattern))
    hits = [p for p in hits if p.is_file()]
    if not hits:
        raise BuildError(f"{label} not found")
    return hits[0]


def convert_icon(src, dst, size):
    require_file(src, "icon")
    Path(dst).parent.mkdir(parents=True, exist_ok=True)
    run([
        "convert", str(src),
        "-auto-orient", "-alpha", "on",
        "-resize", f"{size}x{size}^",
        "-gravity", "center", "-extent", f"{size}x{size}",
        str(dst),
    ])


def patch_android(req, work):
    template = require_file(req["template"], "android template")
    output = Path(req["output"])
    output.parent.mkdir(parents=True, exist_ok=True)

    app_name = str(req["app_name"]).strip()
    package = str(req["package_name"]).strip()
    version_name = str(req.get("version_name", "1.0.0")).strip() or "1.0.0"
    version_code = int(req.get("version_code", 1))
    url = str(req["url"]).strip()
    validate_url(url)
    validate_identifier(package, "package name")
    if not app_name or version_code < 1:
        raise BuildError("invalid app name/version code")

    decoded = work / "decoded"
    unsigned = work / "unsigned.apk"
    run(["java", "-jar", APKEDITOR, "d", "-t", "xml", "-dex", "-f",
         "-i", str(template), "-o", str(decoded)])

    manifest = require_file(decoded / "AndroidManifest.xml", "decoded AndroidManifest")
    tree = ET.parse(manifest)
    root = tree.getroot()
    root.set("package", package)
    root.set(f"{{{ANDROID_NS}}}versionName", version_name)
    root.set(f"{{{ANDROID_NS}}}versionCode", str(version_code))
    safe_xml_write(tree, manifest)

    strings_files = list(decoded.glob("**/res/values/strings.xml"))
    changed_label = False
    for strings in strings_files:
        try:
            st = ET.parse(strings)
            sr = st.getroot()
            file_changed = False
            for node in sr.findall("string"):
                if node.attrib.get("name") == "app_name":
                    node.text = app_name
                    changed_label = True
                    file_changed = True
            if file_changed:
                safe_xml_write(st, strings)
        except ET.ParseError:
            continue
    if not changed_label:
        raise BuildError("app_name resource not found")

    config = find_one(decoded, ["**/assets/config.json"], "Android config.json")
    cfg = json.loads(config.read_text(encoding="utf-8"))
    cfg.update({
        "url": url,
        "app_name": app_name,
        "splash_color": req.get("splash_color", "#FFFFFF"),
        "status_bar_color": req.get("status_bar_color", "#000000"),
        "enable_splash": bool(req.get("enable_splash", True)),
        "splash_duration": int(req.get("splash_duration", 1200)),
    })
    config.write_text(json.dumps(cfg, ensure_ascii=False, indent=2), encoding="utf-8")

    icon = str(req.get("icon") or "")
    if icon:
        launchers = [p for p in decoded.glob("**/res/mipmap*/ic_launcher.png") if p.is_file()]
        if not launchers:
            raise BuildError("patchable Android launcher PNG not found in template")
        density_sizes = {
            "mipmap-mdpi": 48,
            "mipmap-hdpi": 72,
            "mipmap-xhdpi": 96,
            "mipmap-xxhdpi": 144,
            "mipmap-xxxhdpi": 192,
            "mipmap": 192,
        }
        for launcher in launchers:
            size = density_sizes.get(launcher.parent.name, 192)
            convert_icon(icon, launcher, size)

    run(["java", "-jar", APKEDITOR, "b", "-i", str(decoded), "-o", str(unsigned)])
    require_file(unsigned, "rebuilt APK")

    ks = require_file(req["keystore"], "keystore")
    alias = str(req["keystore_alias"]).strip()
    store_pass = read_secret(req["keystore_password_file"], "keystore password")
    key_pass = read_secret(req["key_password_file"], "key password")
    signed_dir = work / "signed"
    signed_dir.mkdir()

    run([
        "java", "-jar", UBER_SIGNER,
        "-a", str(unsigned),
        "--ks", str(ks),
        "--ksAlias", alias,
        "--ksPass", store_pass,
        "--ksKeyPass", key_pass,
        "--allowResign",
        "--skipZipAlign",
        "--out", str(signed_dir),
    ])

    candidates = sorted(signed_dir.glob("*.apk"), key=lambda p: p.stat().st_mtime, reverse=True)
    if not candidates:
        raise BuildError("signed APK not produced")
    shutil.copy2(candidates[0], output)

    run(["java", "-jar", UBER_SIGNER, "-a", str(output), "--onlyVerify", "--skipZipAlign"])
    with zipfile.ZipFile(output) as zf:
        if "assets/config.json" not in zf.namelist():
            raise BuildError("final APK missing config.json")
        embedded = json.loads(zf.read("assets/config.json").decode("utf-8"))
        if embedded.get("url") != url:
            raise BuildError("final APK config verification failed")
    return output


def patch_ios(req, work):
    template = require_file(req["template"], "iOS template")
    output = Path(req["output"])
    output.parent.mkdir(parents=True, exist_ok=True)

    app_name = str(req["app_name"]).strip()
    bundle_id = str(req["bundle_id"]).strip()
    version_name = str(req.get("version_name", "1.0.0")).strip() or "1.0.0"
    build_number = str(max(1, int(req.get("version_code", 1))))
    url = str(req["url"]).strip()
    validate_url(url)
    validate_identifier(bundle_id, "bundle id")
    if not app_name:
        raise BuildError("invalid app name")

    extracted = work / "ipa"
    with zipfile.ZipFile(template) as zf:
        zf.extractall(extracted)
    apps = list((extracted / "Payload").glob("*.app"))
    if len(apps) != 1:
        raise BuildError("template IPA must contain exactly one .app")
    app = apps[0]

    config = require_file(app / "config.json", "iOS config.json")
    cfg = json.loads(config.read_text(encoding="utf-8"))
    cfg.update({
        "url": url,
        "app_name": app_name,
        "status_bar_color": req.get("status_bar_color", "#000000"),
    })
    config.write_text(json.dumps(cfg, ensure_ascii=False, indent=2), encoding="utf-8")

    info = require_file(app / "Info.plist", "Info.plist")
    with info.open("rb") as fh:
        plist = plistlib.load(fh)
    plist["CFBundleIdentifier"] = bundle_id
    plist["CFBundleDisplayName"] = app_name
    plist["CFBundleName"] = app_name
    plist["CFBundleShortVersionString"] = version_name
    plist["CFBundleVersion"] = build_number
    with info.open("wb") as fh:
        plistlib.dump(plist, fh, fmt=plistlib.FMT_XML, sort_keys=False)

    unsigned = work / "prepared.ipa"
    with zipfile.ZipFile(unsigned, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for path in sorted(extracted.rglob("*")):
            if path.is_file():
                zf.write(path, path.relative_to(extracted))

    p12 = require_file(req["p12"], "PKCS#12 identity")
    profile = require_file(req["mobileprovision"], "provisioning profile")
    password = read_secret(req["p12_password_file"], "PKCS#12 password")

    cmd = [
        "zsign",
        "-k", str(p12),
        "-p", password,
        "-m", str(profile),
        "-b", bundle_id,
        "-n", app_name,
        "-r", version_name,
        "-o", str(output),
    ]
    icon = str(req.get("icon") or "")
    if icon:
        cmd.extend(["-I", icon])
    cmd.append(str(unsigned))
    run(cmd)
    require_file(output, "signed IPA")

    run(["zsign", "-c", str(output)])
    with zipfile.ZipFile(output) as zf:
        names = zf.namelist()
        info_names = [n for n in names if re.match(r"^Payload/[^/]+\.app/Info\.plist$", n)]
        profile_names = [n for n in names if re.match(r"^Payload/[^/]+\.app/embedded\.mobileprovision$", n)]
        if not info_names or not profile_names:
            raise BuildError("final IPA missing Info.plist/provisioning profile")
        final_plist = plistlib.loads(zf.read(info_names[0]))
        if final_plist.get("CFBundleIdentifier") != bundle_id:
            raise BuildError("final IPA bundle id verification failed")
    return output


def main():
    if len(sys.argv) != 2:
        print("usage: appdown-template-builder request.json", file=sys.stderr)
        return 2
    req_path = require_file(sys.argv[1], "request")
    req = json.loads(req_path.read_text(encoding="utf-8"))
    platform = req.get("platform")
    with tempfile.TemporaryDirectory(prefix="appdown-template-") as td:
        work = Path(td)
        if platform == "android":
            output = patch_android(req, work)
        elif platform == "ios":
            output = patch_ios(req, work)
        else:
            raise BuildError("platform must be android or ios")
        result = {"ok": True, "platform": platform, "output": str(output), "size": output.stat().st_size}
        print("APPDOWN_RESULT=" + json.dumps(result, ensure_ascii=False), flush=True)
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except BuildError as exc:
        print("APPDOWN_ERROR=" + str(exc), file=sys.stderr, flush=True)
        raise SystemExit(1)
