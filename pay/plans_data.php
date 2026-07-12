<?php
/**
 * 共享套餐数据 - 编辑此处即可同步更新 API 和套餐页
 * 支持类型：
 *   - count: 按次（需 count 字段）
 *   - monthly: 包月/包季（需 days 字段）
 *   - daily: 包天（需 days 字段）
 *   - lifetime: 永久不限次
 *   - lifetime_daily: 永久每日限次（需 daily_limit 字段）
 */
$ALL_PLANS = [
    [
        "id" => "count_10",
        "name" => "体验套餐",
        "type" => "count",
        "count" => 10,
        "days" => 0,
        "price" => 0.50,
        "popular" => false,
        "desc" => "10次体验，仅需5毛"
    ],
    [
        "id" => "count_50",
        "name" => "按次 50次",
        "type" => "count",
        "count" => 50,
        "days" => 0,
        "price" => 9.90,
        "popular" => false,
        "desc" => "适合偶尔使用"
    ],
    [
        "id" => "count_200",
        "name" => "按次 200次",
        "type" => "count",
        "count" => 200,
        "days" => 0,
        "price" => 29.90,
        "popular" => true,
        "desc" => "适合高频使用"
    ],
    [
        "id" => "count_1000",
        "name" => "按次 1000次",
        "type" => "count",
        "count" => 1000,
        "days" => 0,
        "price" => 99.90,
        "popular" => false,
        "desc" => "适合批量使用"
    ],
    [
        "id" => "daily_7",
        "name" => "包周套餐",
        "type" => "daily",
        "count" => 0,
        "days" => 7,
        "price" => 5.90,
        "popular" => false,
        "desc" => "7天无限次"
    ],
    [
        "id" => "monthly",
        "name" => "包月套餐",
        "type" => "monthly",
        "count" => 0,
        "days" => 30,
        "price" => 19.90,
        "popular" => false,
        "desc" => "30天无限次"
    ],
    [
        "id" => "quarterly",
        "name" => "包季套餐",
        "type" => "monthly",
        "count" => 0,
        "days" => 90,
        "price" => 49.90,
        "popular" => false,
        "desc" => "90天无限次"
    ],
    [
        "id" => "lifetime",
        "name" => "永久套餐",
        "type" => "lifetime",
        "count" => 0,
        "days" => 0,
        "price" => 199.00,
        "popular" => false,
        "desc" => "永久无限次使用"
    ],
    [
        "id" => "lifetime_daily",
        "name" => "终身畅享套餐",
        "type" => "lifetime_daily",
        "count" => 0,
        "days" => 0,
        "price" => 150.00,
        "daily_limit" => 150,
        "popular" => false,
        "desc" => "终身有效，每日限150次"
    ],
];
