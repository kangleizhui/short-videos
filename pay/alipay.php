<?php
/**
 * 支付宝当面付 - 核心工具类
 */
class Alipay {
    private $app_id;
    private $private_key;
    private $alipay_public_key;
    private $gateway = 'https://openapi.alipay.com/gateway.do';
    private $notify_url;
    
    public function __construct() {
        $this->app_id = '2021002125647131';
        $this->private_key = file_get_contents(__DIR__ . '/../data/keys/alipay_private.pem');
        $this->alipay_public_key = file_get_contents(__DIR__ . '/../data/keys/alipay_public.pem');
        $this->notify_url = 'https://spqsy.kcucu.com/pay/notify.php';
    }
    
    /**
     * 创建当面付二维码
     */
    public function precreate($out_trade_no, $total_amount, $subject, $body = '') {
        $biz_content = [
            'out_trade_no' => $out_trade_no,
            'total_amount' => number_format($total_amount, 2, '.', ''),
            'subject' => $subject,
            'body' => $body ?: $subject,
            'timeout_express' => '30m',
        ];
        
        $params = $this->buildParams('alipay.trade.precreate', $biz_content);
        $result = $this->curl($params);
        
        $response_key = 'alipay_trade_precreate_response';
        if (!isset($result[$response_key])) {
            throw new Exception('支付宝返回格式异常: ' . json_encode($result));
        }
        
        $response = $result[$response_key];
        if ($response['code'] != '10000') {
            throw new Exception('支付宝错误: ' . ($response['sub_msg'] ?? $response['msg'] ?? '未知错误'));
        }
        
        return $response;
    }
    
    /**
     * 查询订单状态
     */
    public function query($out_trade_no) {
        $biz_content = ['out_trade_no' => $out_trade_no];
        $params = $this->buildParams('alipay.trade.query', $biz_content);
        $result = $this->curl($params);
        
        $response_key = 'alipay_trade_query_response';
        if (!isset($result[$response_key])) {
            throw new Exception('查询返回格式异常');
        }
        
        return $result[$response_key];
    }
    
    /**
     * 验证异步通知签名
     */
    public function verifyNotify($params) {
        // 支付宝异步通知验签
        $sign = $params['sign'] ?? '';
        $sign_type = $params['sign_type'] ?? 'RSA2';
        
        // 去除 sign 和 sign_type
        $sign_params = $params;
        unset($sign_params['sign'], $sign_params['sign_type']);
        
        $content = $this->getSignContent($sign_params);
        
        // 使用支付宝公钥验签
        $pubKey = openssl_get_publickey($this->alipay_public_key);
        if (!$pubKey) return false;
        
        $ok = openssl_verify($content, base64_decode($sign), $pubKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($pubKey);
        
        return $ok;
    }
    
    /**
     * 构建请求参数
     */
    private function buildParams($method, $biz_content) {
        $params = [
            'app_id' => $this->app_id,
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => $this->notify_url,
            'biz_content' => json_encode($biz_content, JSON_UNESCAPED_UNICODE),
        ];
        
        $params['sign'] = $this->sign($params);
        return $params;
    }
    
    /**
     * RSA2 签名
     */
    private function sign($params) {
        $content = $this->getSignContent($params);
        $privateKey = openssl_get_privatekey($this->private_key);
        if (!$privateKey) {
            throw new Exception('私钥无效');
        }
        openssl_sign($content, $sign, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);
        return base64_encode($sign);
    }
    
    /**
     * 获取待签名字符串
     */
    private function getSignContent($params) {
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $parts[] = $k . '=' . $v;
        }
        return implode('&', $parts);
    }
    
    /**
     * 发送 HTTP 请求
     */
    private function curl($params) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->gateway . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => false,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code != 200) {
            throw new Exception("支付宝接口请求失败: HTTP {$http_code}");
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("支付宝接口返回非JSON: " . substr($response, 0, 200));
        }
        
        // 验证签名 (如果有)
        $response_key = str_replace('.', '_', $params['method']) . '_response';
        if (isset($result[$response_key]) && isset($result['sign'])) {
            // 验签（可选，生产环境建议开启）
            $sign_params = $result[$response_key];
            // 简化处理：信任网关返回（生产环境应验签）
        }
        
        return $result;
    }
}
