<?php
header('Content-Type: application/json');
$url = $_GET['url'] ?? '';
if (empty($url)) { die(json_encode(['error'=>'no url'])); }
$tmp = '/tmp/dy_dl_' . md5($url) . '.mp4';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Referer: https://www.douyin.com/',
    ],
]);
$data = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
if ($http == 200 && strlen($data) > 1000) {
    file_put_contents($tmp, $data);
    echo json_encode(['ok'=>true, 'path'=>$tmp, 'size'=>strlen($data)]);
} else {
    echo json_encode(['ok'=>false, 'http'=>$http, 'err'=>$err, 'size'=>strlen($data??'')]);
}
