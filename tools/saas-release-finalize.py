from pathlib import Path


def replace(path, old, new, expected=1):
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != expected:
        raise SystemExit(f"{path}: expected {expected} matches, got {count}: {old[:120]!r}")
    p.write_text(text.replace(old, new))
    print(f"patched {path}: {count}")


# SaaS tenant backups never include central tenant credentials.
replace(
    'admin/backup.php',
    '<label class="check-item"><input type="checkbox" value="admin_users">管理员账户</label>',
    '',
)
replace(
    'admin/backup.php',
    ",admin_users:'管理员账户'",
    '',
)
replace(
    'admin/backup.php',
    "<div class=\"card\"><h3>备份说明</h3>",
    "<div class=\"card\"><h3>备份说明</h3><p style=\"color:var(--text-secondary);line-height:1.7;margin-bottom:12px\">SaaS 租户备份只包含当前租户的站点数据和上传文件，不包含超级管理员或租户登录密码。账号由 <code>/super</code> 中央管理。</p>",
)

# Strengthen static guardrails after the builder path hardening landed.
old_checks = r'''        'tools/build-worker.php' => ["appdown_upload_dir() . '/apks'", 'APPDOWN_TENANT'],
        'tools/ios-build-worker.php' => ["appdown_upload_dir() . '/ipas'", "'/data/ios-build/' . \$tenantSlug"],'''
new_checks = r'''        'admin/api/generate.php' => ['APPDOWN_TENANT=', 'appdown_data_dir()'],
        'tools/build-worker.php' => ["appdown_upload_dir() . '/apks'", '$tenantRoot = realpath(appdown_upload_dir())'],
        'tools/ios-build-worker.php' => ["appdown_upload_dir() . '/ipas'", "'/data/ios-build/' . \$tenantSlug", '$tenantRoot = realpath(appdown_upload_dir())'],'''
replace('tests/smoke_saas.php', old_checks, new_checks)

# CI: real ZipArchive + SQLite + mbstring runtime and SaaS integration test.
replace(
    '.github/workflows/ci.yml',
    'sudo apt-get install -y php-cli php-sqlite3 php-zip php-curl php-gd >/dev/null',
    'sudo apt-get install -y php-cli php-sqlite3 php-zip php-curl php-gd php-mbstring >/dev/null',
)
replace(
    '.github/workflows/ci.yml',
    "          php -m | grep -qi '^zip$'",
    "          php -m | grep -qi '^zip$'\n          php -m | grep -qi '^mbstring$'",
)
replace(
    '.github/workflows/ci.yml',
    "      - name: Run landing template smoke test\n        shell: bash\n        working-directory: src\n        run: php tests/smoke_templates.php",
    "      - name: Run landing template smoke test\n        shell: bash\n        working-directory: src\n        run: php tests/smoke_templates.php\n\n      - name: Run SaaS tenant isolation and ZipArchive integration test\n        if: ${{ hashFiles('src/tests/smoke_saas.php') != '' }}\n        shell: bash\n        working-directory: src\n        run: php tests/smoke_saas.php",
)

Path('tools/saas-release-finalize.py').unlink(missing_ok=True)
print('SaaS release finalizer applied')
