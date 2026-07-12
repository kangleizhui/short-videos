<?php
/**
 * 极验行为验证 4.0 服务端二次校验
 * 接收前端验证通过后的参数，向极验API确认
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$captcha_id = '8ae32ba04bfc50cd4f76bcab250e3fc3';
$captcha_key = '29f3375c1127341cb00961dcc1716617';
$api_server = 'http://gcaptcha4.geetest.com';

// 只接受POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '请使用POST请求']);
    exit;
}

$lot_number = $_POST['lot_number'] ?? '';
$captcha_output = $_POST['captcha_output'] ?? '';
$pass_token = $_POST['pass_token'] ?? '';
$gen_time = $_POST['gen_time'] ?? '';

if (!$lot_number || !$captcha_output || !$pass_token || !$gen_time) {
    echo json_encode(['success' => false, 'error' => '验证参数不完整']);
    exit;
}

// 生成签名：HMAC-SHA256(lot_number, captcha_key)
$sign_token = hash_hmac('sha256', $lot_number, $captcha_key);

// 请求极验二次校验接口
$query = [
    'lot_number' => $lot_number,
    'captcha_output' => $captcha_output,
    'pass_token' => $pass_token,
    'gen_time' => $gen_time,
    'sign_token' => $sign_token,
];
$url = $api_server . '/validate?captcha_id=' . urlencode($captcha_id);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($query),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 容灾：接口异常时放行（避免阻碍业务流程）
if ($response === false || $http_code !== 200) {
    echo json_encode([
        'success' => true,
        'geetest_pass' => true,
        '_fallback' => true,
        '_debug' => '极验接口异常，已放行',
    ]);
    exit;
}

$obj = json_decode($response, true);
$passed = ($obj['result'] ?? '') === 'success';

if (!$passed) {
    echo json_encode([
        'success' => false,
        'error' => '验证未通过，请重试',
        'geetest_pass' => false,
        'reason' => $obj['reason'] ?? '',
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'geetest_pass' => true,
]);
