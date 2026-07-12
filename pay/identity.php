<?php
/**
 * 身份码工具函数
 * 用于生成4位短身份码 + 获取身份完整信息
 */

/**
 * 生成唯一4位身份码（大写字母+数字，去掉易混淆的 O/0/I/1/L）
 */
function generateIdentityCode($db) {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $maxRetries = 50;
    for ($i = 0; $i < $maxRetries; $i++) {
        $code = '';
        for ($j = 0; $j < 4; $j++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $db->prepare("SELECT id FROM api_keys WHERE key_string = ?");
        $stmt->bindValue(1, $code, SQLITE3_TEXT);
        $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$r) return $code;
    }
    return 'Z' . substr(str_shuffle('ABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 3);
}

/**
 * 获取身份码的完整信息
 */
function getIdentityInfo($db, $identityCode) {
    $stmt = $db->prepare("SELECT * FROM api_keys WHERE key_string = ?");
    $stmt->bindValue(1, $identityCode, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;

    $info = [
        'identity_code' => $row['key_string'],
        'status' => $row['is_active'] ? 'active' : 'disabled',
        'created_at' => $row['created_at'],
        'note' => $row['note'],
    ];

    $now = time();
    $current_plan = $row['type'];
    $expires_at = $row['expires_at'] ? strtotime($row['expires_at']) : null;

    if ($current_plan === 'monthly' || $current_plan === 'daily') {
        if ($expires_at && $expires_at > $now) {
            $info['current_plan'] = $current_plan;
            $info['expires_at'] = $row['expires_at'];
            $info['days_remaining'] = ceil(($expires_at - $now) / 86400);
            $info['unlimited'] = true;
            $info['frozen_count'] = intval($row['frozen_remain']);
        } else {
            $frozen = intval($row['frozen_remain']);
            $total = intval($row['total_count']);
            $used = intval($row['used_count']);
            $info['current_plan'] = 'count';
            $info['total_bought'] = $total;
            $info['used_count'] = $used;
            $info['remaining'] = $frozen;
            $info['unlimited'] = false;
            $info['auto_restored'] = true;
        }
    } elseif ($current_plan === 'lifetime') {
        $info['current_plan'] = 'lifetime';
        $info['unlimited'] = true;
        $info['frozen_count'] = intval($row['frozen_remain']);
    } elseif ($current_plan === 'lifetime_daily') {
        $info['current_plan'] = 'lifetime_daily';
        $info['unlimited'] = true;
        $info['daily_limit'] = intval($row['daily_limit']);
        $today = date('Y-m-d');
        $st = $db->prepare("SELECT used_count FROM key_daily_usage WHERE key_id = ? AND date = ?");
        $st->bindValue(1, $row['id'], SQLITE3_INTEGER);
        $st->bindValue(2, $today, SQLITE3_TEXT);
        $dr = $st->execute()->fetchArray(SQLITE3_ASSOC);
        $info['daily_used'] = $dr ? intval($dr['used_count']) : 0;
        $info['daily_remaining'] = $info['daily_limit'] - $info['daily_used'];
    } else {
        $remain = intval($row['total_count']) - intval($row['used_count']);
        $info['current_plan'] = 'count';
        $info['total_bought'] = intval($row['total_count']);
        $info['used_count'] = intval($row['used_count']);
        $info['remaining'] = $remain;
        $info['unlimited'] = false;
    }

    return $info;
}
