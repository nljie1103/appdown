<?php
/**
 * 签名密钥管理 API
 */

require_once __DIR__ . '/../../includes/init.php';
require_auth();

$pdo = get_db();
$method = get_request_method();

// GET - 列出所有密钥（隐藏密码）
if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM keystores ORDER BY created_at DESC')->fetchAll();
    // 隐藏密码字段
    foreach ($rows as &$row) {
        $row['store_password'] = $row['store_password'] ? '******' : '';
        $row['key_password'] = $row['key_password'] ? '******' : '';
    }
    json_response($rows);
}

csrf_validate();

// POST - 生成或上传密钥
if ($method === 'POST') {
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    // 上传已有keystore
    if ($action === 'upload') {
        if (empty($_FILES['file'])) {
            json_response(['error' => '请上传keystore文件'], 400);
        }
        $result = handle_upload('file', 'keystore');
        if (!$result['ok']) {
            json_response($result, 400);
        }
        $name = trim($_POST['name'] ?? '');
        $alias = trim($_POST['alias'] ?? '');
        $storePwd = $_POST['store_password'] ?? '';
        $keyPwd = $_POST['key_password'] ?? '';

        if (empty($name) || empty($alias) || empty($storePwd) || empty($keyPwd)) {
            delete_upload($result['url']);
            json_response(['error' => '名称、别名和密码为必填项'], 400);
        }

        $stmt = $pdo->prepare('INSERT INTO keystores (name, file_url, alias, store_password, key_password) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $result['url'], $alias, $storePwd, $keyPwd]);
        json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }

    // 生成新keystore
    if ($action === 'generate') {
        $data = get_json_input();
        $name = trim($data['name'] ?? '');
        $alias = trim($data['alias'] ?? '');
        $storePwd = $data['store_password'] ?? '';
        $keyPwd = $data['key_password'] ?? '';
        $validity = max(1, (int)($data['validity_years'] ?? 25));
        $cn = mb_substr(trim($data['common_name'] ?? ''), 0, 64);
        $ou = mb_substr(trim($data['org_unit'] ?? ''), 0, 64);
        $org = mb_substr(trim($data['org_name'] ?? ''), 0, 64);
        $loc = mb_substr(trim($data['locality'] ?? ''), 0, 64);
        $st = mb_substr(trim($data['state_name'] ?? ''), 0, 64);
        $c = mb_substr(trim($data['country'] ?? ''), 0, 2);

        if (empty($name) || empty($alias)) {
            json_response(['error' => '名称和别名为必填项'], 400);
        }
        if (strlen($storePwd) < 6 || strlen($keyPwd) < 6) {
            json_response(['error' => '密码至少6个字符'], 400);
        }

        $dir = __DIR__ . '/../../uploads/keystores';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $safeName = preg_replace('/[^\w\-]/', '_', $name);
        $filename = $safeName . '_' . time() . '.jks';
        $filepath = $dir . '/' . $filename;

        // 构建dname
        $dnameParts = [];
        if ($cn) $dnameParts[] = 'CN=' . $cn;
        if ($ou) $dnameParts[] = 'OU=' . $ou;
        if ($org) $dnameParts[] = 'O=' . $org;
        if ($loc) $dnameParts[] = 'L=' . $loc;
        if ($st) $dnameParts[] = 'ST=' . $st;
        if ($c) $dnameParts[] = 'C=' . $c;
        $dname = implode(', ', $dnameParts) ?: 'CN=Unknown';

        $cmd = sprintf(
            'keytool -genkeypair -v -keystore %s -alias %s -keyalg RSA -keysize 2048 -validity %d -storepass %s -keypass %s -dname %s 2>&1',
            escapeshellarg($filepath),
            escapeshellarg($alias),
            $validity * 365,
            escapeshellarg($storePwd),
            escapeshellarg($keyPwd),
            escapeshellarg($dname)
        );

        exec($cmd, $output, $retCode);

        if ($retCode !== 0 || !file_exists($filepath)) {
            json_response(['error' => '生成失败: ' . implode("\n", $output)], 500);
        }

        $fileUrl = 'uploads/keystores/' . $filename;
        $stmt = $pdo->prepare('INSERT INTO keystores (name, file_url, alias, store_password, key_password, validity_years, org_name, org_unit, country, state_name, locality, common_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $fileUrl, $alias, $storePwd, $keyPwd, $validity, $org, $ou, $c, $st, $loc, $cn]);
        json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }

    json_response(['error' => '无效的action'], 400);
}

// PUT - 更新密钥元数据
if ($method === 'PUT') {
    $data = get_json_input();
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_response(['error' => '缺少id'], 400);

    $fields = [];
    $params = [];
    foreach (['name', 'alias', 'org_name', 'org_unit', 'country', 'state_name', 'locality', 'common_name'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = trim($data[$f]);
        }
    }
    // 密码单独处理（只在非空时更新）
    if (!empty($data['store_password']) && $data['store_password'] !== '******') {
        $fields[] = "store_password = ?";
        $params[] = $data['store_password'];
    }
    if (!empty($data['key_password']) && $data['key_password'] !== '******') {
        $fields[] = "key_password = ?";
        $params[] = $data['key_password'];
    }

    if (empty($fields)) json_response(['error' => '无更新字段'], 400);

    $fields[] = "updated_at = datetime('now')";
    $params[] = $id;
    $pdo->prepare("UPDATE keystores SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    json_response(['ok' => true]);
}

// DELETE - 删除密钥
if ($method === 'DELETE') {
    $data = get_json_input();
    $id = (int)($data['id'] ?? 0);
    if (!$id) json_response(['error' => '缺少id'], 400);

    // 检查是否被引用
    $count = $pdo->prepare('SELECT COUNT(*) FROM generated_apks WHERE keystore_id = ?');
    $count->execute([$id]);
    if ($count->fetchColumn() > 0) {
        json_response(['error' => '该密钥已被生成的APK使用，无法删除'], 400);
    }

    // 删除文件
    $ks = $pdo->prepare('SELECT file_url FROM keystores WHERE id = ?');
    $ks->execute([$id]);
    $row = $ks->fetch();
    if ($row) {
        delete_upload($row['file_url']);
    }

    $pdo->prepare('DELETE FROM keystores WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'method not allowed'], 405);
