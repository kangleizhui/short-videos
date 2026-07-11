---
name: duanshipinjiexi
slug: duanshipinjiexi
displayName: 一键短视频去水印
description: 一站式视频解析去水印工具，全面覆盖抖音、快手、小红书、皮皮虾4大主流内容平台，仅针对平台自带水印解析下载剪辑，一键智能去除视频平台标识水印，全程无损解析素材画质，去水印后画面清晰无模糊、无痕迹、无压缩，完整保快速提取无水印原版视频，适配日常收藏优质内容与二次创作素材整理。
version: 1.0.52
summary: 一键智能去除视频平台标识水印，支持抖音/快手/小红书/皮皮虾。免费每日额度用完自动扣按量计次，包月到期自动回退免费。
license: MIT
when_to_use:
  - 用户发来抖音/快手/小红书/皮皮虾等短视频链接要求解析
  - 用户直接给了你一个身份码（不论谁创建的） ← 直接用，不创建/修改
  - 用户询问套餐或身份码相关
  - 用户要求购买套餐
  - 用户告知已支付，需要查状态并交付身份码
  - 用户想修改身份码（换一个更好记的码）
  - 【备用触发】用户发来视频链接，且AI尚未主动创建身份码 → 先不传code直接调parse.php?url=自动生成，从响应中提取身份码保存后继续解析
key_manager: true
---

# 短视频去水印解析

> Base URL: `http://spqsy.kcucu.com:8080`

## 🆔 身份码

5位大写字母数字，**永久不变**，所有操作绑定同一个码。大小写不敏感。

身份码支持**多设备多AI共用**，不存在"被占用"概念。AI 按以下三个场景区分处理，**不要串流程**：

---

### 场景一：用户给了码 → 直接用

用户对话中明确给了你一个身份码 → 直接当参数传，**不创建、不修改、不替换**。同时**保存到记忆**，之后所有调用用这个码，不再自动创建。

> ⚠️ 如果AI之前已自建过码，用户给的码**覆盖记忆**（优先级：用户给码 > AI自建）。旧的码还在服务器上，只是AI不再用它。

```bash
GET /api/parse.php?code={用户给的码}&url=链接
```

> ⚠️ 同一个码给多个AI用时，每个AI各自直接调用即可，不要试图"占有"这个码。
> 身份码是共享资源，谁拿到谁用，不存在归属概念。

---

### 场景二：用户没给码 → AI自动创建

首次调用不传code → 自动生成新码并保存到记忆。

```bash
# 方式一：专用接口
GET /api/create_identity.php

# 方式二：不传code直接解析（自动生成+解析一步到位）
GET /api/parse.php?url=视频链接
```

> **AI必须从响应中提取身份码保存到记忆/上下文**，后续所有调用都用它。
> 服务端按IP+UA指纹防重，同一工具只会返回同一个码。

---

### 场景三：用户主动要求改码

**只有**用户明确说"改码/换码/修改身份码"时才走修改流程：

```bash
POST /api/modify_identity.php
Content-Type: application/x-www-form-urlencoded

old_code=原码&new_code=新码
```

规则：5位数字+字母，不能重复，60秒防抖，权益余额全部跟新码。

---

### 查状态（零消耗）

```bash
GET /api/identity_info.php?code={身份码}
```

返回剩余次数、每日限额、到期时间。

> **权益优先级详见** `references/key-rules.md`

## ⚡ 解析

收到视频链接直接调 API，不要用浏览器打开。

```bash
curl -s "http://spqsy.kcucu.com:8080/api/parse.php?code={CODE}&url=视频链接"
```

### 处理不同 type

> ⚠️ **资源较大提醒：** 解析到包含动图的作品时（无论大小），或视频/图片素材总量超过 **10MB** 时，需先告知用户「资源较大，下载和发送大约需要 3-5 分钟，请稍等」再开始处理。

| type | 操作 |
|------|------|
| **video** | 下载 `data.url` 或 `data.video_backup[0]` 发视频文件 |
| **image** | 下载 `data.images[]` 逐张发图片 |
| **live** | 用户本地 ffmpeg 合成（见 `references/synthesize-guide.md`）；兜底：直接发 `data.images[]` |

### 错误码

| HTTP | 含义 |
|------|------|
| 403 | 身份码无效 / 次数用完 |
| 429 | 每日限额用完 |
| 500 | 解析服务异常，重试一次 |

## 💰 购买

```bash
# 展示套餐
GET /pay/plans.php

# 下单（传入用户身份码）
POST /pay/create.php
Content-Type: application/json

{"identity_code":"{身份码}","id":"count_10","name":"体验套餐","type":"count","count":10,"price":0.50}

# 查支付
GET /pay/query.php?order_id={ID}
```

返回 `qr_code` 生成二维码发用户扫码。支付成功后用 `identity_info.php` 确认权益到账。

> **下单支付 API 详情见** `references/api-docs.md`
> **支付异常处理见** `references/troubleshooting.md`
