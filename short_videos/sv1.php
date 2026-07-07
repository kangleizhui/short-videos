<?php
/**
*@Author: JH-Ahua
*@CreateTime: 2025/8/6 上午12:56
*@email: admin@bugpk.com
*@blog: www.jiuhunwl.cn
*@Api: api.bugpk.com
*@tip: 短视频聚合解析
*/
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 输入验证与过滤
$url = $_REQUEST['url'] ?? null;
$url = trim($url ?? '');

// 检查URL是否为空或无效
if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode([
        'code' => 400,
        'msg' => '请输入有效的链接'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 平台配置：统一管理匹配关键词和API地址
$platforms = [
    'douyin' => [
        'keywords' => ['douyin'],
        'api_url' => 'https://api.bugpk.com/api/douyin?url='
    ],
    'kuaishou' => [
        'keywords' => ['kuaishou'],
        'api_url' => 'https://api.bugpk.com/api/ksjx?url='
    ],
    'weishi' => [
        'keywords' => ['weishi'],
        'api_url' => 'https://api.bugpk.com/api/weishi?url='
    ],
    'bilibili' => [
        'keywords' => ['bilibili'],
        'api_url' => 'https://api.bugpk.com/api/bilibili?url='
    ],
    'pipixia' => [
        'keywords' => ['pipix'],
        'api_url' => 'https://api.bugpk.com/api/pipixia?url='
    ],
    'pipigx' => [
        'keywords' => ['ippzone', 'pipigx'],
        'api_url' => 'https://api.bugpk.com/api/pipigx?url='
    ],
    'weibo' => [
        'keywords' => ['weibo'],
        'api_url' => 'https://api.bugpk.com/api/weibo?url='
    ],
    'xhs' => [
        'keywords' => ['xhs', 'xiaohongshu'],
        'api_url' => 'https://api.bugpk.com/api/xhsjx?url='
    ]
];

// 查找匹配的平台
$matchedPlatform = null;
$lowerUrl = strtolower($url);

foreach ($platforms as $platform => $config) {
    foreach ($config['keywords'] as $keyword) {
        if (strpos($lowerUrl, $keyword) !== false) {
            $matchedPlatform = $config;
            break 2; // 找到匹配项，跳出双层循环
        }
    }
}

// 处理请求
if ($matchedPlatform) {
    $apiUrl = $matchedPlatform['api_url'] . urlencode($url);
    $response = requestUrl($apiUrl);

    if ($response !== false) {
        // 确保返回的是JSON格式
        if (isValidJson($response)) {
            echo $response;
        } else {
            echo json_encode([
                'code' => 500,
                'msg' => '接口返回格式不正确',
                'data' => $response
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode([
            'code' => 500,
            'msg' => '请求接口失败'
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'code' => 201,
        'msg' => '不支持您输入的链接平台'
    ], JSON_UNESCAPED_UNICODE);
}
function requestUrl($url, $method = 'GET', $data = [])
{
    // 初始化cURL
    $ch = curl_init();

    // 设置URL
    curl_setopt($ch, CURLOPT_URL, $url);

    // 设置请求方法
    if (strtoupper($method) === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    // 设置超时时间
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    // 不验证SSL证书（生产环境建议开启验证）
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // 返回响应内容而不直接输出
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // 执行请求并获取响应
    $response = curl_exec($ch);

    // 检查是否有错误
    if (curl_errno($ch)) {
        error_log('请求错误: ' . curl_error($ch));
        $response = false;
    }

    // 关闭cURL资源
    curl_close($ch);

    return $response;
}

/**
 * 验证字符串是否为有效的JSON
 * @param string $string 待验证的字符串
 * @return bool 是否为有效JSON
 */
function isValidJson($string)
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}
?>
