<?php
/**
 * AppDown 官方 GitHub Release 在线升级器。
 *
 * 安全原则：
 * - 固定仓库 nljie1103/appdown，不接受用户提供的仓库或下载 URL。
 * - main 只识别 vX.Y.Z；saas 只识别 saas-vX.Y.Z。
 * - 下载官方 tag 源码 ZIP 后检查 ZIP 安全、版本文件和 edition。
 * - 覆盖程序文件前生成代码备份；data/、uploads/ 和安装锁等运行时文件不会被覆盖。
 * - 逐文件同目录临时写入后 rename，失败时从备份恢复。
 */

require_once __DIR__ . '/version.php';

function updater_project_root(): string {
    $testRoot = getenv('APPDOWN_UPDATE_TEST_ROOT');
    if (PHP_SAPI === 'cli' && is_string($testRoot) && trim($testRoot) !== '') {
        return rtrim($testRoot, '/\\');
    }
    return dirname(__DIR__);
}

function updater_repo(): string {
    return defined('APPDOWN_GITHUB_REPO') ? APPDOWN_GITHUB_REPO : 'nljie1103/appdown';
}

function updater_release_prefix(): string {
    return APPDOWN_EDITION === 'saas' ? 'saas-v' : 'v';
}

function updater_version_from_tag(string $tag): ?string {
    $prefix = preg_quote(updater_release_prefix(), '/');
    if (!preg_match('/^' . $prefix . '(\\d+\\.\\d+\\.\\d+)$/', $tag, $m)) return null;
    return $m[1];
}

function updater_select_latest_release(array $releases): ?array {
    $best = null;
    $bestVersion = null;
    foreach ($releases as $release) {
        if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease'])) continue;
        $tag = (string)($release['tag_name'] ?? '');
        $version = updater_version_from_tag($tag);
        if ($version === null) continue;
        if ($bestVersion === null || version_compare($version, $bestVersion, '>')) {
            $bestVersion = $version;
            $best = [
                'tag' => $tag,
                'version' => $version,
                'name' => (string)($release['name'] ?? $tag),
                'notes' => (string)($release['body'] ?? ''),
                'published_at' => (string)($release['published_at'] ?? ''),
                'html_url' => (string)($release['html_url'] ?? ''),
            ];
        }
    }
    return $best;
}

function updater_http_json(string $url): array {
    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: AppDown-Updater/' . APPDOWN_VERSION,
    ];
    $token = getenv('APPDOWN_GITHUB_TOKEN');
    if (is_string($token) && trim($token) !== '') $headers[] = 'Authorization: Bearer ' . trim($token);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $body === '' || $status < 200 || $status >= 300) {
            throw new RuntimeException('GitHub 请求失败' . ($error !== '' ? '：' . $error : '，HTTP ' . $status));
        }
    } else {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('服务器未启用 cURL，且 allow_url_fopen 已关闭');
        }
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 20, 'header' => implode("\r\n", $headers) . "\r\n"]]);
        $body = @file_get_contents($url, false, $ctx);
        if (!is_string($body) || $body === '') throw new RuntimeException('无法连接 GitHub API');
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) throw new RuntimeException('GitHub 返回了无法解析的数据');
    return $decoded;
}

function updater_cache_path(): string {
    return updater_project_root() . '/data/update-release-cache-' . APPDOWN_EDITION . '.json';
}

function updater_check_release(bool $force = false): array {
    $cachePath = updater_cache_path();
    if (!$force && is_file($cachePath) && (time() - (int)filemtime($cachePath)) < 600) {
        $cached = json_decode((string)@file_get_contents($cachePath), true);
        if (is_array($cached) && !empty($cached['tag'])) {
            $cached['from_cache'] = true;
            $cached['update_available'] = version_compare((string)$cached['version'], APPDOWN_VERSION, '>');
            return $cached;
        }
    }

    $url = 'https://api.github.com/repos/' . updater_repo() . '/releases?per_page=30';
    $latest = updater_select_latest_release(updater_http_json($url));
    if (!$latest) throw new RuntimeException('没有找到适用于当前版本的正式 GitHub Release');
    $latest['checked_at'] = date(DATE_ATOM);
    $latest['from_cache'] = false;
    $latest['update_available'] = version_compare($latest['version'], APPDOWN_VERSION, '>');

    $dir = dirname($cachePath);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    @file_put_contents($cachePath, json_encode($latest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($cachePath, 0600);
    return $latest;
}

function updater_download_release(string $tag): string {
    if (updater_version_from_tag($tag) === null) throw new RuntimeException('非法 Release tag');
    if (!class_exists('ZipArchive')) throw new RuntimeException('在线升级需要 PHP ZipArchive 扩展');

    $tmp = tempnam(sys_get_temp_dir(), 'appdown-update-');
    if ($tmp === false) throw new RuntimeException('无法创建升级临时文件');
    $url = 'https://api.github.com/repos/' . updater_repo() . '/zipball/' . rawurlencode($tag);
    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: AppDown-Updater/' . APPDOWN_VERSION,
    ];
    $token = getenv('APPDOWN_GITHUB_TOKEN');
    if (is_string($token) && trim($token) !== '') $headers[] = 'Authorization: Bearer ' . trim($token);

    $fp = fopen($tmp, 'wb');
    if (!$fp) { @unlink($tmp); throw new RuntimeException('无法写入升级临时文件'); }
    try {
        if (function_exists('curl_init')) {
            $maxBytes = 100 * 1024 * 1024;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_XFERINFOFUNCTION => static function ($resource, $downloadTotal, $downloaded) use ($maxBytes) {
                    return $downloaded > $maxBytes ? 1 : 0;
                },
            ]);
            $ok = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($ok !== true || $status < 200 || $status >= 300) {
                throw new RuntimeException('Release 下载失败' . ($error !== '' ? '：' . $error : '，HTTP ' . $status));
            }
        } else {
            fclose($fp);
            $fp = null;
            $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 120, 'follow_location' => 1, 'header' => implode("\r\n", $headers) . "\r\n"]]);
            $in = @fopen($url, 'rb', false, $ctx);
            if (!$in) throw new RuntimeException('无法下载 Release');
            $fp = fopen($tmp, 'wb');
            $written = stream_copy_to_stream($in, $fp, 100 * 1024 * 1024 + 1);
            fclose($in);
            if ($written === false || $written > 100 * 1024 * 1024) throw new RuntimeException('Release 文件过大');
        }
    } finally {
        if (is_resource($fp)) fclose($fp);
    }
    if (!is_file($tmp) || filesize($tmp) < 1000) { @unlink($tmp); throw new RuntimeException('下载到的 Release 文件无效'); }
    return $tmp;
}

function updater_normalize_relpath(string $path): ?string {
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    $path = ltrim((string)$path, '/');
    if ($path === '' || str_contains($path, "\0")) return null;
    $parts = explode('/', $path);
    foreach ($parts as $part) if ($part === '' || $part === '.' || $part === '..') return null;
    return implode('/', $parts);
}

function updater_is_runtime_protected(string $rel): bool {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '.env' || $rel === '.user.ini' || $rel === 'install/install.lock' || $rel === 'install/access.log') return true;
    if (str_starts_with($rel, 'uploads/')) return true;
    if (str_starts_with($rel, 'data/') && $rel !== 'data/index.php') return true;
    if (str_starts_with($rel, 'android-template/.gradle/') || $rel === 'android-template/local.properties') return true;
    return false;
}

function updater_zip_is_symlink(ZipArchive $zip, int $index): bool {
    if (!method_exists($zip, 'getExternalAttributesIndex')) return false;
    $opsys = 0; $attr = 0;
    if (!$zip->getExternalAttributesIndex($index, $opsys, $attr)) return false;
    if ($opsys !== ZipArchive::OPSYS_UNIX) return false;
    $mode = ($attr >> 16) & 0xF000;
    return $mode === 0xA000;
}

function updater_archive_map(string $zipPath, string $expectedEdition, string $expectedVersion): array {
    $zip = new ZipArchive();
    $opened = $zip->open($zipPath);
    if ($opened !== true) throw new RuntimeException('无法打开 Release ZIP');
    try {
        if ($zip->numFiles < 1 || $zip->numFiles > 6000) throw new RuntimeException('Release ZIP 文件数量异常');
        $rootPrefix = null;
        $map = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat)) throw new RuntimeException('无法读取 Release ZIP 条目');
            $name = str_replace('\\', '/', (string)$stat['name']);
            if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                throw new RuntimeException('Release ZIP 包含危险路径');
            }
            if (updater_zip_is_symlink($zip, $i)) throw new RuntimeException('Release ZIP 不允许符号链接');
            $firstSlash = strpos($name, '/');
            if ($firstSlash === false) throw new RuntimeException('Release ZIP 根目录结构异常');
            $prefix = substr($name, 0, $firstSlash + 1);
            if ($rootPrefix === null) $rootPrefix = $prefix;
            if ($prefix !== $rootPrefix) throw new RuntimeException('Release ZIP 包含多个根目录');
            $relRaw = substr($name, strlen($rootPrefix));
            if ($relRaw === '' || str_ends_with($relRaw, '/')) continue;
            $rel = updater_normalize_relpath($relRaw);
            if ($rel === null) throw new RuntimeException('Release ZIP 包含非法文件路径');
            $size = (int)($stat['size'] ?? 0);
            $compressed = max(1, (int)($stat['comp_size'] ?? $size));
            if ($size < 0 || $size > 120 * 1024 * 1024) throw new RuntimeException('Release ZIP 单文件大小异常');
            if ($size > 10 * 1024 * 1024 && ($size / $compressed) > 200) throw new RuntimeException('Release ZIP 压缩比异常');
            $total += $size;
            if ($total > 600 * 1024 * 1024) throw new RuntimeException('Release ZIP 解压总大小异常');
            if (isset($map[$rel])) throw new RuntimeException('Release ZIP 包含重复文件路径');
            $mode = 0644;
            if (method_exists($zip, 'getExternalAttributesIndex')) {
                $opsysMode = 0; $attrMode = 0;
                if ($zip->getExternalAttributesIndex($i, $opsysMode, $attrMode) && $opsysMode === ZipArchive::OPSYS_UNIX) {
                    $candidate = ($attrMode >> 16) & 0777;
                    if ($candidate > 0) $mode = $candidate;
                }
            }
            $map[$rel] = ['index' => $i, 'size' => $size, 'mode' => $mode];
        }
        if (!isset($map['README.md'], $map['includes/version.php'])) throw new RuntimeException('Release ZIP 缺少 AppDown 标识文件');

        $versionSource = $zip->getFromIndex($map['includes/version.php']['index']);
        if (!is_string($versionSource)) throw new RuntimeException('无法读取 Release 版本信息');
        if (!preg_match("/APPDOWN_EDITION'\\s*,\\s*'([^']+)'/", $versionSource, $em)) throw new RuntimeException('Release 缺少 edition 信息');
        if (!preg_match("/APPDOWN_VERSION'\\s*,\\s*'([^']+)'/", $versionSource, $vm)) throw new RuntimeException('Release 缺少 version 信息');
        if ($em[1] !== $expectedEdition || $vm[1] !== $expectedVersion) throw new RuntimeException('Release 版本/版本线与当前站点不匹配');
        return ['root' => (string)$rootPrefix, 'files' => $map];
    } finally {
        $zip->close();
    }
}

function updater_previous_manifest(): array {
    $path = updater_project_root() . '/data/update-installed-manifest.json';
    if (!is_file($path)) return [];
    $data = json_decode((string)@file_get_contents($path), true);
    if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) return [];
    return array_values(array_filter(array_map('strval', $data['files'])));
}

function updater_write_manifest(string $tag, string $version, array $files): void {
    $path = updater_project_root() . '/data/update-installed-manifest.json';
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $payload = ['edition' => APPDOWN_EDITION, 'tag' => $tag, 'version' => $version, 'installed_at' => date(DATE_ATOM), 'files' => array_values($files)];
    if (@file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
        throw new RuntimeException('升级已写入，但无法保存升级文件清单');
    }
    @chmod($path, 0600);
}

function updater_backup_files(array $files, string $fromVersion, string $toVersion): string {
    $root = updater_project_root();
    $dir = $root . '/data/update-backups';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('无法创建升级备份目录');
    $path = $dir . '/code-' . date('Ymd-His') . '-' . preg_replace('/[^0-9A-Za-z._-]/', '_', $fromVersion . '-to-' . $toVersion) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('无法创建升级代码备份');
    $added = [];
    foreach ($files as $rel) {
        $rel = updater_normalize_relpath((string)$rel);
        if ($rel === null || updater_is_runtime_protected($rel)) continue;
        $full = $root . '/' . $rel;
        if (is_file($full)) {
            if (!$zip->addFile($full, $rel)) { $zip->close(); @unlink($path); throw new RuntimeException('备份程序文件失败：' . $rel); }
            $added[] = $rel;
        }
    }
    $zip->addFromString('__appdown_update__/meta.json', json_encode([
        'edition' => APPDOWN_EDITION, 'from' => $fromVersion, 'to' => $toVersion,
        'created_at' => date(DATE_ATOM), 'files' => $added,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $zip->close();
    @chmod($path, 0600);
    return $path;
}

function updater_atomic_write_stream($stream, string $dest, ?int $sourceMode = null): void {
    $dir = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('无法创建目录：' . $dir);
    if (!is_writable($dir)) throw new RuntimeException('目录不可写：' . $dir);
    if (is_file($dest) && !is_writable($dest)) throw new RuntimeException('文件不可写：' . $dest);
    $tmp = $dir . '/.appdown-update-' . bin2hex(random_bytes(6)) . '.tmp';
    $out = fopen($tmp, 'wb');
    if (!$out) throw new RuntimeException('无法创建临时更新文件');
    try {
        if (stream_copy_to_stream($stream, $out) === false) throw new RuntimeException('写入升级文件失败');
        fflush($out);
    } finally {
        fclose($out);
    }
    $mode = is_file($dest) ? ((int)@fileperms($dest) & 0777) : ($sourceMode ?? 0644);
    if ($mode <= 0) $mode = 0644;
    @chmod($tmp, $mode);
    if (!@rename($tmp, $dest)) {
        @unlink($tmp);
        throw new RuntimeException('替换程序文件失败：' . $dest);
    }
    if (function_exists('opcache_invalidate')) @opcache_invalidate($dest, true);
}

function updater_restore_backup(string $backupPath): void {
    if (!is_file($backupPath)) return;
    $root = updater_project_root();
    $zip = new ZipArchive();
    if ($zip->open($backupPath) !== true) return;
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat)) continue;
            $rel = updater_normalize_relpath((string)$stat['name']);
            if ($rel === null || str_starts_with($rel, '__appdown_update__/') || updater_is_runtime_protected($rel)) continue;
            $stream = $zip->getStream((string)$stat['name']);
            if (!is_resource($stream)) continue;
            try { updater_atomic_write_stream($stream, $root . '/' . $rel); } finally { fclose($stream); }
        }
    } finally { $zip->close(); }
}

function updater_apply_release_archive(string $zipPath, array $release): array {
    $tag = (string)($release['tag'] ?? '');
    $version = (string)($release['version'] ?? '');
    if (updater_version_from_tag($tag) !== $version || $version === '') throw new RuntimeException('升级版本信息无效');
    if (!version_compare($version, APPDOWN_VERSION, '>') && PHP_SAPI !== 'cli') throw new RuntimeException('目标版本不高于当前版本');

    $root = updater_project_root();
    $validated = updater_archive_map($zipPath, APPDOWN_EDITION, $version);
    $map = $validated['files'];
    $newFiles = [];
    foreach (array_keys($map) as $rel) if (!updater_is_runtime_protected($rel) && !str_starts_with($rel, '.github/')) $newFiles[] = $rel;

    $previousManifest = updater_previous_manifest();
    $deleteFiles = [];
    foreach ($previousManifest as $old) {
        if (!in_array($old, $newFiles, true) && !updater_is_runtime_protected($old) && !str_starts_with($old, '.github/')) $deleteFiles[] = $old;
    }
    $backupTargets = array_values(array_unique(array_merge($newFiles, $deleteFiles)));
    $backupPath = updater_backup_files($backupTargets, APPDOWN_VERSION, $version);
    $oldManifestPath = $root . '/data/update-installed-manifest.json';
    $oldManifest = is_file($oldManifestPath) ? file_get_contents($oldManifestPath) : null;
    $created = [];

    $lockPath = $root . '/data/update.lock';
    $lockDir = dirname($lockPath);
    if (!is_dir($lockDir)) @mkdir($lockDir, 0750, true);
    $lock = fopen($lockPath, 'c+');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('已有升级任务正在运行');

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) { flock($lock, LOCK_UN); fclose($lock); throw new RuntimeException('无法再次打开升级包'); }
    try {
        foreach ($newFiles as $rel) {
            $dest = $root . '/' . $rel;
            $existed = is_file($dest);
            $entry = $map[$rel] ?? null;
            if (!$entry) throw new RuntimeException('升级包文件清单不一致：' . $rel);
            $stat = $zip->statIndex((int)$entry['index']);
            $name = is_array($stat) ? (string)$stat['name'] : '';
            $stream = $zip->getStream($name);
            if (!is_resource($stream)) throw new RuntimeException('无法读取升级文件：' . $rel);
            try { updater_atomic_write_stream($stream, $dest, (int)($entry['mode'] ?? 0644)); } finally { fclose($stream); }
            if (!$existed) $created[] = $rel;
        }
        foreach ($deleteFiles as $rel) {
            $full = $root . '/' . $rel;
            if (is_file($full) && !@unlink($full)) throw new RuntimeException('无法删除旧程序文件：' . $rel);
        }
        updater_write_manifest($tag, $version, $newFiles);
        @unlink(updater_cache_path());
    } catch (Throwable $e) {
        foreach ($created as $rel) @unlink($root . '/' . $rel);
        updater_restore_backup($backupPath);
        if ($oldManifest === null) @unlink($oldManifestPath);
        else @file_put_contents($oldManifestPath, $oldManifest, LOCK_EX);
        throw new RuntimeException('升级失败，已尝试回滚：' . $e->getMessage(), 0, $e);
    } finally {
        $zip->close();
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($lockPath);
    }

    return ['ok' => true, 'version' => $version, 'tag' => $tag, 'backup' => $backupPath, 'updated_files' => count($newFiles), 'removed_files' => count($deleteFiles)];
}

function updater_perform(array $release): array {
    $zipPath = updater_download_release((string)$release['tag']);
    try { return updater_apply_release_archive($zipPath, $release); }
    finally { @unlink($zipPath); }
}
