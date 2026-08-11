<?php
/** AppDown Admin 2.0 online update API (single-site edition only). */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/updater.php';
require_auth();

if (defined('APPDOWN_EDITION') && APPDOWN_EDITION !== 'main') {
    json_response(['error' => '当前版本不允许租户后台升级整个平台'], 403);
}

$method = get_request_method();
if ($method === 'GET') {
    try {
        $force = isset($_GET['refresh']) && $_GET['refresh'] === '1';
        $latest = updater_check_release($force);
        json_response([
            'ok' => true,
            'current' => [
                'version' => APPDOWN_VERSION,
                'tag' => APPDOWN_RELEASE_TAG,
                'edition' => APPDOWN_EDITION,
            ],
            'latest' => $latest,
        ]);
    } catch (Throwable $e) {
        error_log('[AppDown updater API] ' . $e);
        json_response(['error' => $e->getMessage()], 502);
    }
}

if ($method === 'POST') {
    csrf_validate();
    $data = get_json_input();
    $action = (string)($data['action'] ?? '');
    if ($action !== 'update') json_response(['error' => '无效操作'], 400);

    try {
        $latest = updater_check_release(true);
        $expected = (string)($data['tag'] ?? '');
        if ($expected === '' || !hash_equals((string)$latest['tag'], $expected)) {
            json_response(['error' => 'GitHub 最新版本已发生变化，请刷新后重试'], 409);
        }
        if (empty($latest['update_available'])) json_response(['error' => '当前已经是最新版'], 400);
        $result = updater_perform($latest);
        json_response(['ok' => true, 'result' => $result]);
    } catch (Throwable $e) {
        error_log('[AppDown updater API] ' . $e);
        json_response(['error' => $e->getMessage()], 500);
    }
}

json_response(['error' => 'method not allowed'], 405);
