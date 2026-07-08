<?php
$url = $_GET['url'] ?? '';
if (!$url || !str_starts_with($url, 'https://')) {
    http_response_code(400);
    exit('bad url');
}
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36',
    CURLOPT_REFERER => 'https://www.douyin.com/',
]);
$data = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http != 200 || empty($data) || strlen($data) < 1000) {
    http_response_code(502);
    exit("proxy failed: http=$http len=" . strlen($data));
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
header('Content-Type: ' . (finfo_buffer($finfo, $data) ?: 'video/mp4'));
header('Content-Length: ' . strlen($data));
header('X-Proxy: zjcdn');
echo $data;
