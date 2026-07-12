# API 文档 — 身份码版

> Base URL: `http://spqsy.kcucu.com`
> 身份码 = 5位大写字母数字，首次调用自动生成

## 核心变化（v1.0.28+）

- ❌ 再无长密钥 `XXXX-XXXX-XXXX-XXXX-XXXX`
- ❌ 再无固定测试密钥 `TEST1-...`
- ✅ 5位身份码：`X7K3M`，简洁不易截断
- ✅ 首次调用无需身份码，API 自动生成
- ✅ 所有操作使用**同一个身份码**，购买叠加权益

## 1. 解析视频（首次调用）

```bash
# 首次调用：不传 key，自动生成5位身份码
GET /api/parse.php?url=https://v.douyin.com/xxx/

# 后续调用：用已有身份码
GET /api/parse.php?key=X7K3M&url=https://v.douyin.com/xxx/
```

### 响应 JSON

```json
{
  "code": 200,
  "data": {
    "type": "video",
    "title": "标题",
    "author": {"name": "作者名"},
    "url": "https://...",
    "images": ["https://..."],
    "live_photo": [{"video": "...", "image": "..."}],
    "music": {"title": "歌名", "author": "歌手", "url": "https://..."},
    "stats": {"liked": "12.3w", "comment": "4567", "share": "890"}
  },
  "identity_info": {
    "code": "X7K3M",
    "is_new": true,
    "plans": ["免费每日"],
    "remaining": null,
    "daily_remaining": 10,
    "expires_at": null
  }
}
```

### identity_info 字段说明

| 字段 | 说明 |
|------|------|
| `code` | 5位身份码 |
| `is_new` | true=首次生成的新身份码 |
| `plans` | 当前生效的套餐列表 |
| `remaining` | 计次剩余次数（null=无限制） |
| `daily_remaining` | 今日剩余免费次数 |
| `expires_at` | 到期时间（null=永不过期） |

### 错误码

| HTTP | msg | 说明 |
|------|-----|------|
| 400 | 缺少参数: url | 未传视频链接 |
| 403 | 身份码无效 | 身份码不存在 |
| 429 | 今日调用次数已达上限 | 每天10次已用完 |
| 500 | 解析服务异常 | 内部错误 |

## 2. 身份码信息查询（零消耗）

```bash
GET /api/identity_info.php?code=X7K3M
```

```json
{
  "code": 200,
  "data": {
    "code": "X7K3M",
    "is_active": true,
    "plans": ["免费每日", "计次"],
    "benefits": [
      {"type": "free_daily", "daily_limit": 10, "note": "免费每日体验"},
      {"type": "count", "total_count": 50, "used_count": 3}
    ],
    "remaining": 47,
    "daily_remaining": 8,
    "daily_used_today": 2,
    "expires_at": null
  }
}
```

## 3. 下单

```bash
POST /pay/create.php
Content-Type: application/json

{"id": "count_10", "name": "体验套餐", "type": "count", "count": 10, "price": 0.50}

# 如果用户已有身份码，传入叠加：
{"id": "count_50", "name": "按次 50次", "type": "count", "count": 50, "price": 9.90, "identity_code": "X7K3M"}
```

```json
{"success": true, "order_id": "SV20260707XXXX", "qr_code": "https://qr.alipay.com/xxx", "amount": 0.50}
```

⚠️ 收到 `qr_code` 后必须 `qrencode -o /tmp/qr.png "URL"` 生成二维码图片。

## 4. 查支付状态

```bash
GET /pay/query.php?order_id=SV20260707XXXX
```

```json
{
  "success": true,
  "status": "paid",
  "identity_code": "X7K3M",
  "identity_info": {
    "code": "X7K3M",
    "plans": ["计次"],
    "remaining": 10,
    "daily_remaining": null,
    "expires_at": null
  }
}
```

### identity_info 各类型展示

| plans | 展示 |
|-------|------|
| 免费每日 | `今日剩余 8/10 次（免费）` |
| 计次 | `剩余 47 次` |
| 包月 | `不限总次数，还剩约22天` |
| 永久 | `不限总次数，永不过期` |

## 5. 套餐列表

```bash
GET /pay/plans.php
```

## 6. CDN 代理下载

```bash
GET /api/svproxyurl.php?proxyurl=<base64_url>&type=<type>
```
