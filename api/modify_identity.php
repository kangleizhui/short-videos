<?php
/**
 * 修改身份码 API
 * 
 * 用法:
 *   POST /api/modify_identity.php
 *   Body: old_code=X7K3M&new_code=ABC12
 * 
 * 限制:
 *   - 新身份码必须5位，仅限数字+字母（不限大小写）
 *   - 记录修改者 IP + UA 到 code_change_log
 *   - 同 IP + UA 有防抖（60秒内不可重复修改同一个旧码）
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$db_path = __DIR__ . '/../data/keys.db';

function getDB() {
    global $db_path;
    for ($i = 0; $i < 3; $i++) {
        try {
            $db = new SQLite3($db_path);
            $db->exec('PRAGMA journal_mode=WAL');
            $db->exec('PRAGMA foreign_keys=ON');
            $db->exec('PRAGMA busy_timeout=5000');
            return $db;
        } catch (Exception $e) {
            if ($i >= 2) throw $e;
            usleep(100000 * ($i + 1));
        }
    }
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $first = trim($ips[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

try {
    $db = getDB();

    // 确保 code_change_log 表存在
    $db->exec("CREATE TABLE IF NOT EXISTS code_change_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identity_id INTEGER NOT NULL,
        old_code TEXT NOT NULL,
        new_code TEXT NOT NULL,
        ip TEXT,
        user_agent TEXT,
        created_at TEXT
    )");

    // 只接受 POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['code' => 405, 'msg' => '请使用 POST 请求'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $old_code = trim($_POST['old_code'] ?? '');
    $new_code = trim($_POST['new_code'] ?? '');

    if (!$old_code || !$new_code) {
        echo json_encode(['code' => 400, 'msg' => '缺少参数: old_code, new_code'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 清理空格换行
    $old_code = preg_replace('/[\s\r\n]+/', '', strtoupper($old_code));
    $new_code = preg_replace('/[\s\r\n]+/', '', strtoupper($new_code));

    // 校验新码：必须5位，仅数字+字母
    if (strlen($new_code) !== 5) {
        echo json_encode(['code' => 400, 'msg' => '新身份码必须是5位'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!preg_match('/^[A-Z0-9]+$/', $new_code)) {
        echo json_encode(['code' => 400, 'msg' => '新身份码只能包含数字和字母'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 查旧身份码
    $stmt = $db->prepare('SELECT * FROM identities WHERE code = ?');
    $stmt->bindValue(1, $old_code, SQLITE3_TEXT);
    $identity = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$identity) {
        echo json_encode(['code' => 404, 'msg' => '旧身份码不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!$identity['is_active']) {
        echo json_encode(['code' => 403, 'msg' => '旧身份码已停用，无法修改'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 新旧码相同
    if ($old_code === $new_code) {
        echo json_encode(['code' => 400, 'msg' => '新旧身份码相同，无需修改'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 查新码是否已被占用
    $stmt = $db->prepare('SELECT id FROM identities WHERE code = ?');
    $stmt->bindValue(1, $new_code, SQLITE3_TEXT);
    if ($stmt->execute()->fetchArray()) {
        echo json_encode(['code' => 409, 'msg' => '该身份码已被使用，请换一个'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 防抖：同 IP + 同旧码 60秒内不能改两次
    $ip = getClientIP();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM code_change_log WHERE old_code = ? AND ip = ? AND created_at > datetime('now', '-60 seconds')");
    $stmt->bindValue(1, $old_code, SQLITE3_TEXT);
    $stmt->bindValue(2, $ip, SQLITE3_TEXT);
    $recent = (int)$stmt->execute()->fetchArray(SQLITE3_ASSOC)['cnt'];
    if ($recent > 0) {
        echo json_encode(['code' => 429, 'msg' => '操作太频繁，请60秒后再试'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 执行修改
    $now = date('Y-m-d H:i:s');
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $db->exec('BEGIN TRANSACTION');
    try {
        // 记录修改日志
        $stmt = $db->prepare("INSERT INTO code_change_log (identity_id, old_code, new_code, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $identity['id'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $old_code, SQLITE3_TEXT);
        $stmt->bindValue(3, $new_code, SQLITE3_TEXT);
        $stmt->bindValue(4, $ip, SQLITE3_TEXT);
        $stmt->bindValue(5, $ua, SQLITE3_TEXT);
        $stmt->bindValue(6, $now, SQLITE3_TEXT);
        $stmt->execute();

        // 更新 identities 表
        $stmt = $db->prepare("UPDATE identities SET code = ?, last_ip = ?, last_used_at = ? WHERE id = ?");
        $stmt->bindValue(1, $new_code, SQLITE3_TEXT);
        $stmt->bindValue(2, $ip, SQLITE3_TEXT);
        $stmt->bindValue(3, $now, SQLITE3_TEXT);
        $stmt->bindValue(4, $identity['id'], SQLITE3_INTEGER);
        $stmt->execute();

        $db->exec('COMMIT');

        // 返回当前剩余权益信息
        $benefits = $db->query('SELECT type, total_count, used_count, daily_limit, expires_at FROM identity_benefits WHERE identity_id = ' . (int)$identity['id'] . ' ORDER BY id');
        $benefits_list = [];
        while ($b = $benefits->fetchArray(SQLITE3_ASSOC)) {
            $benefits_list[] = $b;
        }

        echo json_encode([
            'code' => 200,
            'msg' => '修改成功',
            'data' => [
                'old_code' => $old_code,
                'new_code' => $new_code,
                'ip' => $ip,
                'changed_at' => $now,
                'benefits' => $benefits_list,
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        echo json_encode(['code' => 500, 'msg' => '修改失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    echo json_encode(['code' => 500, 'msg' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
}
