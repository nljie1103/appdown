<?php
/**
 * AppDown 安全与维护辅助函数
 */

function appdown_master_key(): string {
    static $keys = [];

    $env = trim((string)getenv('APPDOWN_MASTER_KEY'));
    if ($env !== '') {
        $cacheId = 'env:' . hash('sha256', $env);
        if (isset($keys[$cacheId])) return $keys[$cacheId];
        if (preg_match('/^[a-f0-9]{64}$/i', $env)) $decoded = hex2bin($env);
        else $decoded = base64_decode($env, true);
        if (is_string($decoded) && strlen($decoded) === 32) return $keys[$cacheId] = $decoded;
        throw new RuntimeException('APPDOWN_MASTER_KEY 必须是 32 字节密钥的 Base64 或 64 位十六进制字符串');
    }

    $keyFile = function_exists('tenant_secret_key_path') && current_tenant(true)
        ? tenant_secret_key_path()
        : (__DIR__ . '/../data/.secret.key');
    if (isset($keys[$keyFile])) return $keys[$keyFile];

    $dataDir = dirname($keyFile);
    if (!is_dir($dataDir) && !@mkdir($dataDir, 0750, true) && !is_dir($dataDir)) {
        throw new RuntimeException('无法创建主密钥目录');
    }
    if (!file_exists($keyFile)) {
        $raw = random_bytes(32);
        if (@file_put_contents($keyFile, base64_encode($raw) . "\n", LOCK_EX) === false) {
            throw new RuntimeException('无法创建租户主密钥');
        }
        @chmod($keyFile, 0600);
        return $keys[$keyFile] = $raw;
    }

    $rawText = trim((string)@file_get_contents($keyFile));
    $decoded = base64_decode($rawText, true);
    if (!is_string($decoded) || strlen($decoded) !== 32) throw new RuntimeException('主密钥格式无效');
    return $keys[$keyFile] = $decoded;
}

function is_encrypted_secret(string $value): bool {
    return str_starts_with($value, 'enc:v1:');
}

function encrypt_secret(string $plaintext): string {
    if ($plaintext === '' || is_encrypted_secret($plaintext)) return $plaintext;
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('PHP OpenSSL 扩展不可用，无法加密敏感数据');
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', appdown_master_key(), OPENSSL_RAW_DATA, $iv, $tag, 'appdown-secret-v1', 16);
    if ($cipher === false) throw new RuntimeException('敏感数据加密失败');
    return 'enc:v1:' . base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(?string $value): string {
    $value = (string)$value;
    if ($value === '' || !is_encrypted_secret($value)) return $value;
    $raw = base64_decode(substr($value, 7), true);
    if (!is_string($raw) || strlen($raw) < 29) throw new RuntimeException('敏感数据密文格式无效');
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', appdown_master_key(), OPENSSL_RAW_DATA, $iv, $tag, 'appdown-secret-v1');
    if ($plain === false) throw new RuntimeException('敏感数据解密失败：租户主密钥可能已变化');
    return $plain;
}

function redact_sensitive_text(string $text, array $secrets = []): string {
    foreach ($secrets as $secret) {
        $secret = (string)$secret;
        if ($secret !== '') $text = str_replace($secret, '******', $text);
    }
    $patterns = [
        '/(-P(?:ksPwd|ksKeyPwd)=)(?:\'[^\']*\'|"[^"]*"|\S+)/i',
        '/((?:storePassword|keyPassword|store_password|key_password)\s*[=:]\s*)(?:\'[^\']*\'|"[^"]*"|\S+)/i',
        '/((?:password|passwd|passphrase)\s*[=:]\s*)(?:\'[^\']*\'|"[^"]*"|\S+)/i',
    ];
    foreach ($patterns as $pattern) $text = preg_replace($pattern, '$1******', $text) ?? $text;
    return $text;
}

function appdown_password_kdf(string $password, string $salt, string $algorithm = 'auto'): string {
    if ($algorithm === 'auto') $algorithm = function_exists('sodium_crypto_pwhash') ? 'argon2id' : 'pbkdf2';
    if ($algorithm === 'argon2id') {
        if (!function_exists('sodium_crypto_pwhash')) throw new RuntimeException('该备份使用 Argon2id 加密，但当前 PHP 未启用 sodium 扩展');
        return sodium_crypto_pwhash(32, $password, $salt, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    }
    if ($algorithm === 'pbkdf2') return hash_pbkdf2('sha256', $password, $salt, 600000, 32, true);
    throw new InvalidArgumentException('未知的备份 KDF');
}

function validate_app_archive(string $path, string $ext): array {
    $ext = strtolower($ext);
    if (!in_array($ext, ['apk', 'ipa'], true)) return ['ok' => true];
    if (!class_exists('ZipArchive')) return ['ok' => false, 'error' => '服务器未安装 PHP zip 扩展，无法校验安装包结构'];

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return ['ok' => false, 'error' => strtoupper($ext) . ' 文件不是有效 ZIP 容器'];
    $ok = false;
    if ($ext === 'apk') {
        $ok = $zip->locateName('AndroidManifest.xml') !== false;
    } else {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (preg_match('#^Payload/[^/]+\.app/Info\.plist$#', $name)) { $ok = true; break; }
        }
    }
    $zip->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => $ext === 'apk' ? 'APK 缺少 AndroidManifest.xml' : 'IPA 缺少 Payload/*.app/Info.plist'];
}

function validate_zip_safety(ZipArchive $zip, int $maxFiles = 10000, int $maxTotalBytes = 4294967296, int $maxSingleBytes = 2147483648, float $maxRatio = 250.0): array {
    if ($zip->numFiles > $maxFiles) return ['ok' => false, 'error' => '压缩包文件数量过多'];
    $total = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (!$stat) continue;
        $name = (string)($stat['name'] ?? '');
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:[\\/]#', $name)) return ['ok' => false, 'error' => '压缩包包含非法路径'];
        $parts = preg_split('#[\\/]+#', $name);
        if (in_array('..', $parts ?: [], true)) return ['ok' => false, 'error' => '压缩包包含路径遍历'];
        $size = (int)($stat['size'] ?? 0);
        $comp = (int)($stat['comp_size'] ?? 0);
        if ($size > $maxSingleBytes) return ['ok' => false, 'error' => '压缩包内单个文件过大'];
        $total += $size;
        if ($total > $maxTotalBytes) return ['ok' => false, 'error' => '压缩包解压后总大小过大'];
        if ($comp > 0 && $size > 1048576 && ($size / $comp) > $maxRatio) return ['ok' => false, 'error' => '检测到异常压缩比，已拒绝可能的 ZIP Bomb'];
    }
    return ['ok' => true, 'total_size' => $total, 'file_count' => $zip->numFiles];
}

function secure_temp_secret(string $value, string $prefix = 'appdown_secret_'): string {
    $path = tempnam(sys_get_temp_dir(), $prefix);
    if ($path === false || file_put_contents($path, $value) === false) throw new RuntimeException('无法创建临时安全文件');
    @chmod($path, 0600);
    return $path;
}

function schedule_daily_maintenance(PDO $pdo): void {
    $last = get_setting($pdo, 'maintenance_last_auto', '');
    $today = date('Y-m-d');
    if ($last === $today || !function_exists('exec')) return;
    $script = realpath(__DIR__ . '/../tools/maintenance.php');
    if (!$script) return;
    $tenant = current_tenant(true);
    if (!$tenant) return;
    $php = PHP_BINDIR . '/php';
    if (!is_file($php)) $php = 'php';
    set_setting($pdo, 'maintenance_last_auto', $today);
    $cmd = sprintf(
        'nohup env APPDOWN_TENANT=%s %s %s --quiet > /dev/null 2>&1 &',
        escapeshellarg($tenant['slug']),
        escapeshellarg($php),
        escapeshellarg($script)
    );
    @exec($cmd);
}
