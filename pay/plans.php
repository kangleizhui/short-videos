<?php
/**
 * 套餐列表 API
 * GET /pay/plans.php
 * 返回可用套餐列表 — 数据源：plans_data.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/plans_data.php';

echo json_encode([
    "code" => 200,
    "data" => $ALL_PLANS
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
