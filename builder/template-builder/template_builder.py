#!/usr/bin/env python3
import glob
import json
import os
import plistlib
import re
import shutil
import struct
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path

APKEDITOR = "/opt/appdown/APKEditor.jar"
UBER_SIGNER = "/opt/appdown/uber-apk-signer.jar"
ANDROID_NS = "http://schemas.android.com/apk/res/android"
LC_CODE_SIGNATURE = 0x1D
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


def _thin_macho_has_code_signature(data):
    if len(data) < 28:
        return False
    magic = data[:4]
    if magic == b"\xce\xfa\xed\xfe":
        endian, header_size = "<", 28
    elif magic == b"\xfe\xed\xfa\xce":
        endian, header_size = ">", 28
    elif magic == b"\xcf\xfa\xed\xfe":
        endian, header_size = "<", 32
    elif magic == b"\xfe\xed\xfa\xcf":
        endian, header_size = ">", 32
    else:
        return False
    if len(data) < header_size:
        return False
    ncmds = struct.unpack_from(endian + "I", data, 16)[0]
    offset = header_size
    for _ in range(ncmds):
        if offset + 8 > len(data):
            return False
        cmd, cmdsize = struct.unpack_from(endian + "II", data, offset)
        if cmdsize < 8 or offset + cmdsize > len(data):
            return False
        if cmd == LC_CODE_SIGNATURE:
            return True
        offset += cmdsize
    return False


def macho_has_code_signature(data):
    if _thin_macho_has_code_signature(data):
        return True
    if len(data) < 8:
        return False
    magic = data[:4]
    fat_types = {
        b"\xca\xfe\xba\xbe": (">", False),
        b"\xbe\xba\xfe\xca": ("<", False),
        b"\xca\xfe\xba\xbf": (">", True),
        b"\xbf\xba\xfe\xca": ("<", True),
    }
    if magic not in fat_types:
        return False
    endian, is_64 = fat_types[magic]
    nfat = struct.unpack_from(endian + "I", data, 4)[0]
    entry_size = 32 if is_64 else 20
    cursor = 8
    found = 0
    for _ in range(nfat):
        if cursor + entry_size > len(data):
            return False
        if is_64:
            slice_offset, slice_size = struct.unpack_from(endian + "QQ", data, cursor + 8)
        else:
            slice_offset, slice_size = struct.unpack_from(endian + "II", data, cursor + 8)
        end = slice_offset + slice_size
        if slice_offset >= len(data) or end > len(data):
            return False
        if not _thin_macho_has_code_signature(data[slice_offset:end]):
            return False
        found += 1
        cursor += entry_size
    return found > 0


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

    # Metadata is patched exactly once above. zsign is intentionally used only
    # for signing (and optional primary-icon replacement), so CFBundleVersion
    # cannot be accidentally overwritten by its -r convenience option.
    cmd = [
        "zsign",
        "-k", str(p12),
        "-p", password,
        "-m", str(profile),
        "-o", str(output),
    ]
    icon = str(req.get("icon") or "")
    if icon:
        cmd.extend(["-I", icon])
    cmd.append(str(unsigned))
    run(cmd)
    require_file(output, "signed IPA")

    # zsign returns non-zero when signing fails. It has no separate offline IPA
    # verification flag: -c means "certificate path" and -C performs online
    # certificate/OCSP checks. Validate the signed artifact itself instead.
    with zipfile.ZipFile(output) as zf:
        names = zf.namelist()
        info_names = [n for n in names if re.match(r"^Payload/[^/]+\.app/Info\.plist$", n)]
        profile_names = [n for n in names if re.match(r"^Payload/[^/]+\.app/embedded\.mobileprovision$", n)]
        codesig_names = [n for n in names if re.match(r"^Payload/[^/]+\.app/_CodeSignature/CodeResources$", n)]
        config_names = [n for n in names if re.match(r"^Payload/[^/]+\.app/config\.json$", n)]
        if len(info_names) != 1 or len(profile_names) != 1 or len(codesig_names) != 1 or len(config_names) != 1:
            raise BuildError("final IPA missing Info.plist/config/provisioning profile/CodeResources")

        final_plist = plistlib.loads(zf.read(info_names[0]))
        if final_plist.get("CFBundleIdentifier") != bundle_id:
            raise BuildError("final IPA bundle id verification failed")
        if str(final_plist.get("CFBundleShortVersionString", "")) != version_name:
            raise BuildError("final IPA version verification failed")
        if str(final_plist.get("CFBundleVersion", "")) != build_number:
            raise BuildError("final IPA build-number verification failed")

        final_cfg = json.loads(zf.read(config_names[0]).decode("utf-8"))
        if final_cfg.get("url") != url:
            raise BuildError("final IPA config verification failed")

        executable = str(final_plist.get("CFBundleExecutable") or "").strip()
        if not executable:
            raise BuildError("final IPA missing CFBundleExecutable")
        app_prefix = info_names[0][:-len("Info.plist")]
        executable_name = app_prefix + executable
        if executable_name not in names:
            raise BuildError("final IPA executable missing")
        if not macho_has_code_signature(zf.read(executable_name)):
            raise BuildError("final IPA Mach-O missing LC_CODE_SIGNATURE")
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
