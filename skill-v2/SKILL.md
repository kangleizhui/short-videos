---
name: duanshipinjiexi
slug: duanshipinjiexi
displayName: 一键短视频去水印
description: 一站式视频解析去水印工具，全面覆盖抖音、快手、小红书、皮皮虾4大主流内容平台，仅针对平台自带水印解析下载剪辑，一键智能去除视频平台标识水印，全程无损解析素材画质，去水印后画面清晰无模糊、无痕迹、无压缩，完整保快速提取无水印原版视频，适配日常收藏优质内容与二次创作素材整理。
version: 1.1.2
summary: 一键智能去除视频平台标识水印，支持抖音/快手/小红书/皮皮虾。免费每日额度用完自动扣按量计次，包月到期自动回退免费。
license: MIT
when_to_use:
  - 用户发来抖音/快手/小红书/皮皮虾等短视频链接要求解析
  - 用户直接给了你一个身份码（不论谁创建的） ← 直接用，不创建/修改
  - 用户询问套餐或身份码相关
  - 用户要求购买套餐
  - 用户告知已支付，需要查状态并交付身份码
  - 用户想修改身份码（换一个更好记的码）
---

# 短视频去水印解析

> Base URL: `https://spqsy.kcucu.com`

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
> 系统按IP+UA指纹防重，同一工具只会返回同一个码。

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

> ⚠️ **动图/图文配乐**：`live`（动图）和 `image`（图文）类型都需配乐。详见下方「处理不同 type」表格，按 type 选择素材+音乐组合。

## ⚡ 解析

收到视频链接直接调 API，不要用浏览器打开。**抖音 short 链接先 resolve 再判断**——如果解析返回404/500，可能不是普通视频而是 **图文（slides）**，试试把跳转后的 `iesdouyin.com/share/slides/` 链接喂给聚合器。

```bash
curl -s --max-time 30 "https://spqsy.kcucu.com/api/parse.php?code={CODE}&url=视频链接"
```

> ⚠️ **连接失败兜底**：如果上述请求因 **连接错误** 失败（DNS 解析失败、连接被拒绝、超时），说明解析服务器地址可能已变更。按以下流程自动获取新地址：
>
> ```bash
> # 1️⃣ 尝试从 SkillHub 下载最新技能包
> TEMP_DIR=$(mktemp -d)
> curl -sL "https://api.skillhub.cn/api/v1/download?slug=duanshipinjiexi" -o "$TEMP_DIR/skill.zip"
> unzip -o "$TEMP_DIR/skill.zip" SKILL.md -d "$TEMP_DIR" 2>/dev/null
>
> # 从 SKILL.md 提取 Base URL
> NEW_URL=$(grep 'Base URL:' "$TEMP_DIR/SKILL.md" | sed 's/.*\`\(.*\)\`.*/\1/')
> rm -rf "$TEMP_DIR"
>
> if [ -n "$NEW_URL" ]; then
>   # 用新地址重试
>   curl -s --max-time 30 "$NEW_URL/api/parse.php?code={CODE}&url=视频链接"
>   echo "【提示】解析服务地址已更新，后续使用新地址 $NEW_URL"
> else
>   # 2️⃣ 兜底：从 ClawHub 查
>   JSON=$(curl -s "https://clawhub.ai/api/v1/skills/duanshipinjiexi")
>   DESCRIPTION=$(echo "$JSON" | python3 -c "import sys,json; print(json.load(sys.stdin).get('description',''))" 2>/dev/null)
>   NEW_URL=$(echo "$DESCRIPTION" | grep 'Base URL:' | sed 's/.*\`\(.*\)\`.*/\1/')
>
>   if [ -n "$NEW_URL" ]; then
>     curl -s --max-time 30 "$NEW_URL/api/parse.php?code={CODE}&url=视频链接"
>     echo "【提示】解析服务地址已更新，后续使用新地址 $NEW_URL"
>   else
>     echo "【错误】无法自动获取新解析地址，请手动更新技能或联系开发者"
>   fi
> fi
> ```
```

### 处理不同 type

> ⚠️ **资源较大提醒：** 解析到包含动图的作品时（无论大小），或视频/图片素材总量超过 **10MB** 时，需先告知用户「资源较大，下载和发送大约需要 3-5 分钟，请稍等」再开始处理。

| type | 素材处理 | 音乐处理 |
|------|---------|---------|
| **video** | 下载 `data.url` 或 `data.video_backup[0]` 发视频文件。**视频已自带音轨，不发配乐** |
| **image** | 下载 `data.images[]` 逐张发图片 + `data.music.url`（配乐MP3） |
| **live** | 下载每段 `live_photo[].video`（动图MP4）+ `data.images[]`（静图）+ `data.music.url`（配乐MP3），**全部发给用户**。注意：部分动图原始 CDN 链接需要带 `User-Agent`（如 `Mozilla/5.0 ... Chrome/125.0 Mobile Safari`）和 `Referer: https://www.douyin.com/` 才能正常下载，否则返回 238 字节的 HTML 错误页 |

> 💡 **为什么这样设计？** 视频作品本身带配乐，用户能直接听到。动图（live）和图文（image）是静默内容，单独把配乐作为音频发出去，用户既能看画面又能听声音，体验更好。
> 动图下载细节见 `references/synthesize-guide.md`

### 多平台链接处理

技能名覆盖抖音/快手/小红书/皮皮虾4个平台，但**后端 API 是统一解析**的——所有链接都调同一个 `parse.php`。AI 不需要区分平台，用户发什么链接就传什么链接：

```bash
# 抖音、快手、小红书、皮皮虾，全部一样调用
GET /api/parse.php?code={CODE}&url=用户发的链接
```

> ⚠️ 快手链接可能需要先 resolve 短链接（`v.kuaishou.com` → 真实地址），但大部分情况下 `parse.php` 自己会处理。如果返回404/500，尝试先 `curl -sL -o /dev/null -w '%{url_effective}'` 解短链后再传。

### 错误码

| HTTP | 含义 |
|------|------|
| 403 | 身份码无效 / 次数用完 |
| 429 | 每日限额用完 |
| 500 | 解析服务异常，重试一次 |

### 📎 视频太大或发不出 → 发链接兜底

视频文件可能因大小超过平台限制、网络超时而发不出。遇以下情况**直接发解析链接给用户**：

1. 先尝试下载视频并发送文件
2. 如果文件超过20MB、发送失败、或用户反馈没收到 → **立即发链接，不要重复尝试发送文件**
3. 发链接时说明：这是视频原链接，浏览器打开即可下载

```bash
📹 视频链接（可直接打开下载）：
{data.url}
```

> 💡 `data.url` 是平台原视频直链，用户浏览器打开即可观看或下载。如果链接无法直接访问，尝试用 `{data.video_backup[0]}` 备用链接。

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
