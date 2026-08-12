<?php
/**
 * AppDown Template Builder 2.0 common helpers.
 * Normal URL → APK/IPA builds use precompiled mother packages and re-signing.
 */

function template_builder_root(): string {
    return dirname(__DIR__);
}

function template_builder_data_dir(): string {
    if (function_exists('tenant_data_dir') && function_exists('current_tenant') && current_tenant(true)) {
        $dir = tenant_data_dir() . '/template-builder';
    } else {
        $dir = template_builder_root() . '/data/template-builder';
    }
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    return $dir;
}

function template_builder_jobs_root(): string {
    $dir = template_builder_root() . '/data/template-jobs';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    return $dir;
}

function template_builder_output_dir(string $platform): string {
    $leaf = $platform === 'ios' ? 'ipas' : 'apks';
    if (function_exists('tenant_upload_dir') && function_exists('current_tenant') && current_tenant(true)) {
        $dir = tenant_upload_dir() . '/' . $leaf;
    } else {
        $dir = template_builder_root() . '/uploads/' . $leaf;
    }
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function template_builder_output_url(string $platform, string $filename): string {
    $leaf = $platform === 'ios' ? 'ipas' : 'apks';
    if (function_exists('current_tenant') && ($tenant = current_tenant(true))) {
        return 'uploads/tenants/' . $tenant['slug'] . '/' . $leaf . '/' . rawurlencode($filename);
    }
    return 'uploads/' . $leaf . '/' . rawurlencode($filename);
}

function ensure_template_builder_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ios_signing_identities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            p12_path TEXT NOT NULL,
            p12_password TEXT NOT NULL,
            cert_subject TEXT NOT NULL DEFAULT '',
            cert_serial TEXT NOT NULL DEFAULT '',
            cert_expires TEXT NOT NULL DEFAULT '',
            cert_sha256 TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS ios_provisioning_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            profile_path TEXT NOT NULL,
            uuid TEXT NOT NULL DEFAULT '',
            team_id TEXT NOT NULL DEFAULT '',
            app_identifier TEXT NOT NULL DEFAULT '',
            bundle_pattern TEXT NOT NULL DEFAULT '',
            profile_type TEXT NOT NULL DEFAULT '',
            expires_at TEXT NOT NULL DEFAULT '',
            device_count INTEGER NOT NULL DEFAULT 0,
            cert_sha256_json TEXT NOT NULL DEFAULT '[]',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");
}

function template_builder_runner(): string {
    return '/usr/local/libexec/appdown-template-builder-runner';
}

function template_builder_status(PDO $pdo): array {
    $runner = template_builder_runner();
    $bootstrapped = is_file($runner) && is_executable($runner);
    $runnerOk = false;
    $runnerText = '';
    if ($bootstrapped) {
        $out = [];
        @exec('sudo -n ' . escapeshellarg($runner) . ' status 2>&1', $out, $code);
        $runnerOk = $code === 0;
        $runnerText = trim(implode("\n", $out));
    }
    $androidTemplate = template_builder_root() . '/builder/templates/android-webview-template.apk';
    $iosTemplate = template_builder_root() . '/builder/templates/ios-webview-template.ipa';
    return [
        'architecture' => php_uname('m'),
        'bootstrapped' => $bootstrapped,
        'runner_ok' => $runnerOk,
        'runner_message' => $runnerText,
        'image_ok' => $runnerOk,
        'android_template' => [
            'ok' => is_file($androidTemplate) && filesize($androidTemplate) > 0,
            'size' => is_file($androidTemplate) ? filesize($androidTemplate) : 0,
        ],
        'ios_template' => [
            'ok' => is_file($iosTemplate) && filesize($iosTemplate) > 0,
            'size' => is_file($iosTemplate) ? filesize($iosTemplate) : 0,
        ],
        'all_ok' => $runnerOk
            && is_file($androidTemplate) && filesize($androidTemplate) > 0
            && is_file($iosTemplate) && filesize($iosTemplate) > 0,
        'install_status' => get_setting($pdo, 'template_builder_install_status', 'idle'),
    ];
}

function template_builder_profile_matches(string $pattern, string $bundleId): bool {
    $pattern = trim($pattern);
    if ($pattern === '') return false;
    if ($pattern === '*') return true;
    if (str_ends_with($pattern, '.*')) {
        return str_starts_with($bundleId, substr($pattern, 0, -1));
    }
    return hash_equals($pattern, $bundleId);
}

function template_builder_profile_xml(string $path): string {
    $tmp = tempnam(sys_get_temp_dir(), 'appdown_profile_');
    if ($tmp === false) throw new RuntimeException('无法创建临时文件');
    try {
        $commands = [
            'openssl smime -inform der -verify -noverify -in ' . escapeshellarg($path) . ' -out ' . escapeshellarg($tmp) . ' 2>/dev/null',
            'openssl cms -verify -inform DER -noverify -in ' . escapeshellarg($path) . ' -out ' . escapeshellarg($tmp) . ' 2>/dev/null',
        ];
        foreach ($commands as $cmd) {
            exec($cmd, $out, $code);
            if ($code === 0 && is_file($tmp) && filesize($tmp) > 0) {
                $xml = file_get_contents($tmp);
                if (is_string($xml) && str_contains($xml, '<plist')) return $xml;
            }
        }
        throw new RuntimeException('Provisioning Profile 无法解析或签名容器无效');
    } finally {
        @unlink($tmp);
    }
}

function template_builder_plist_scalar(string $xml, string $key, string $tag = 'string'): string {
    $k = preg_quote($key, '#');
    if (preg_match('#<key>\s*' . $k . '\s*</key>\s*<' . $tag . '>(.*?)</' . $tag . '>#s', $xml, $m)) {
        return html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
    return '';
}

function template_builder_parse_profile(string $path): array {
    $xml = template_builder_profile_xml($path);
    $uuid = template_builder_plist_scalar($xml, 'UUID');
    $name = template_builder_plist_scalar($xml, 'Name');
    $expires = template_builder_plist_scalar($xml, 'ExpirationDate', 'date');
    $appIdentifier = template_builder_plist_scalar($xml, 'application-identifier');
    $teamId = '';
    if (preg_match('#<key>\s*TeamIdentifier\s*</key>\s*<array>\s*<string>(.*?)</string>#s', $xml, $m)) {
        $teamId = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
    if ($teamId === '' && str_contains($appIdentifier, '.')) $teamId = explode('.', $appIdentifier, 2)[0];
    $bundlePattern = $appIdentifier;
    if ($teamId !== '' && str_starts_with($bundlePattern, $teamId . '.')) {
        $bundlePattern = substr($bundlePattern, strlen($teamId) + 1);
    }

    $devices = [];
    if (preg_match('#<key>\s*ProvisionedDevices\s*</key>\s*<array>(.*?)</array>#s', $xml, $m)) {
        preg_match_all('#<string>(.*?)</string>#s', $m[1], $dm);
        $devices = $dm[1] ?? [];
    }
    $allDevices = (bool)preg_match('#<key>\s*ProvisionsAllDevices\s*</key>\s*<true\s*/>#s', $xml);
    $getTaskAllow = (bool)preg_match('#<key>\s*get-task-allow\s*</key>\s*<true\s*/>#s', $xml);
    $type = $allDevices ? 'enterprise' : (count($devices) ? ($getTaskAllow ? 'development' : 'adhoc') : 'appstore');

    $certHashes = [];
    if (preg_match('#<key>\s*DeveloperCertificates\s*</key>\s*<array>(.*?)</array>#s', $xml, $m)) {
        preg_match_all('#<data>(.*?)</data>#s', $m[1], $cm);
        foreach ($cm[1] ?? [] as $data) {
            $der = base64_decode(preg_replace('/\s+/', '', $data), true);
            if ($der !== false) $certHashes[] = strtoupper(hash('sha256', $der));
        }
    }
    return [
        'name' => $name,
        'uuid' => $uuid,
        'team_id' => $teamId,
        'app_identifier' => $appIdentifier,
        'bundle_pattern' => $bundlePattern,
        'profile_type' => $type,
        'expires_at' => $expires,
        'device_count' => count($devices),
        'cert_sha256' => array_values(array_unique($certHashes)),
    ];
}

function template_builder_safe_name(string $name, string $fallback = 'app'): string {
    $name = preg_replace('/[^\w\x{4e00}-\x{9fff}\-.]+/u', '_', trim($name));
    $name = trim((string)$name, '._-');
    return $name !== '' ? mb_substr($name, 0, 80) : $fallback;
}

function template_builder_resolve_project_file(string $value): string {
    $value = trim($value);
    if ($value === '' || preg_match('#^https?://#i', $value)) return '';
    if (str_contains($value, '..')) return '';
    $root = realpath(template_builder_root());
    $path = realpath(template_builder_root() . '/' . ltrim($value, '/'));
    return ($root && $path && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) ? $path : '';
}
