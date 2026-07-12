---
name: duanshipinjiexi
description: 一站式视频解析去水印工具，全面覆盖抖音、快手、B站、小红书、皮皮虾、微博、头条等主流内容平台，仅针对平台自带水印解析下载纯净视频，无需复杂操作、无需繁琐剪辑，一键智能去除视频平台标识水印，全程无损解析素材画质，去水印后画面清晰无模糊、无痕迹、无压缩，完整保留原视频画质观效，操作简单高效，快速提取无水印原版视频，适配日常收藏优质内容与二次创作素材整理。首次调用自动生成5位身份码，每天免费试用10次，后续购买同一身份码叠加权益。支持自动完成套餐展示、下单、支付、交付身份码全流程。
version: 1.0.28
variables:
  DOMAIN: "spqsy.kcucu.com"
  PORT: "8080"
when_to_use:
  - 用户发来抖音/快手/B站/小红书/皮皮虾/微博等短视频链接要求解析
  - 用户询问套餐或身份码相关
  - 用户要求购买套餐
key_manager: true  # AI 自动管理身份码权益切换
---

# 短视频去水印解析 — 自动合成 & 身份码交付

> Base URL = `http://{DOMAIN}:{PORT}`
> 身份码 = 5位大写字母数字，首次调用 API 自动生成，后续一直用同一个
> 免费体验：每天10次，无需测试密钥，直接发链接即可自动获得身份码（响应中 `identity_info.code`）

AI 通过调用后端 API 完成视频解析、动图合成、套餐展示和身份码交付。技术细节见 `references/api-docs.md`，支付流程说明见 `references/payment-flow.md`。

## 核心能力

1. **解析视频** — 输入短视频链接，返回标题、作者、互动数据、无水印视频直链
2. **图片自动代理** — `parse.php` 返回的 `images[]`/`cover`/`avatar` 全部经过 CF Workers(`svproxy.kcucu.com`) 代理，AI 直接 `curl` 即可下载，无需额外处理
3. **动图本地合成（推荐）** — 检测到动图内容时，在 AI 本地用 ffmpeg 合成，不占服务器资源
4. **动图服务端合成（fallback）** — 本地无 ffmpeg 时，自动降级到服务器异步合成
5. **套餐展示与身份码交付** — 展示套餐列表、创建订单、查支付状态、交付身份码（含身份码详情 `identity_info`）
6. **订单查询** — AI 可调用 `query.php` 查订单支付状态，已支付时返回身份码详情
7. **身份码信息查询** — 随时查任意身份码的剩余次数、到期时间和权益状态，不消耗次数（`GET /api/identity_info.php?code=XXXXX`）

## FFmpeg 安装（全平台支持）

动图合成需要 ffmpeg，AI 先检查本地是否已安装：

```bash
ffmpeg -version
```

如果未安装，按系统安装：

| 系统 | 命令 |
|------|------|
| Linux (Debian/Ubuntu) | `apt-get install -y ffmpeg` |
| Linux (CentOS/RHEL) | `yum install -y ffmpeg` |
| macOS | `brew install ffmpeg` |
| Windows | `winget install ffmpeg` 或 https://ffmpeg.org/download.html |

> 安装失败或不允许安装时，自动降级到服务端合成。

## 操作流程

用户发来链接后，AI 只执行一次，完成后结束：

1. 调用 API 解析链接（首次调用自动获得5位身份码）
2. **发文字消息**（标题、作者、平台、类型、互动数据、身份码和剩余信息）
3. **处理视频/图片/动图**（见下方各类型处理方式）
4. 结束，不做后续追加

> 🔑 **设计原则：项目级修复 > AI 级兜底**
> 1. 当 API 返回的媒体（图片/视频）无法直接下载时，先在项目层面加代理层解决（如 CF Workers），而不是给 AI 加一堆"加Referer下载/CDP截图/发链接"的绕路方案。
> 2. **解析器后端接口必须 Nginx 层面锁定** — 平台解析器（douyin.php 等）不带身份码鉴权，必须只允许 localhost 访问，否则任何人都可以绕过 parse.php 直接白嫖。

### 各类型处理方式

| type | 操作 |
|------|------|
| **video** | 下载视频 URL 到本地，发 MEDIA:/tmp/xxx.mp4 |
| image | 图片链接已自动走 CF Workers 代理，直接 `curl -o img.jpg "URL"` 即可 |
| **live** | **优先本地 ffmpeg 合成**，失败则降级服务端 |

> ⚠️ **🔴🔴🔴 绝对规则 🔴🔴🔴**
> **所有 `ffmpeg -ss` 参数必须是 `0`！所有！每一段！**
> ❌ 反例：`-ss 3`、`-ss 6`、`-ss 12` → 每段开头不同 → 切换时画面突变 = "闪"
> ✅ 正解：`-ss 0` → 全部从同一画面开头 → 视觉连续顺滑
> 这条错了合成就是废的，没有例外。

> ⚠️ **🔴🔴🔴 第二条铁律 🔴🔴🔴**
> **必须把 `data.images[]` 中所有图片作为静图段合进去！**
> `live_photo[].image` 只是 `images[]` 的子集（通常9张图但只标3段live_photo）
> ❌ 错误做法：只用 live_photo 对应的3张静图 → 漏掉其他6张
> ✅ 正确做法：每个周期 = 1段动图 + **ALL images[]** 静图
> **只有 live_photo[].video 才是真正的动图素材，images[] 里的全是静图**

检测到 `type=live` 时，**优先在 AI 本地用 ffmpeg 合成**，流程：

1. **从 parse.php 返回的 `data.live_photo[]` 取出所有动图段**（每段含 `video`=动图URL + `image`=关联静图URL）
2. **从 `data.images[]` 取出所有静图**（全部9张都要展示）
3. 下载所有素材到本地 `/tmp/`
4. 运行 ffmpeg 合成，**按 `images[]` 的原始下标顺序遍历**
5. 发送合成好的视频给用户

**合成顺序（关键！）：**
```
每个周期（重复3次）：
  images[0] 静图 → 如有 motion[pi] 匹配 images[0] 则先播动图
  images[1] 静图 → ...
  ...一直到 images[N-1]
```

完整 ffmpeg 合成示例见 `references/synthesize-guide.md`。

### 🎯 动图（live type）— 服务端合成（fallback）

如果 AI 本地无法安装 ffmpeg，自动切换服务器异步合成：

1. parse.php 返回 synthesized_url=null, synthesize_job_id="xxx", synthesize_status="processing"
2. **先发文字消息**给用户
3. 每3-5秒调一次 check_job.php?code=XX&job_id=XX
4. 轮询直到返回 code=200 且 data.url 有值
5. 下载合成视频发 MEDIA:/tmp/xxx.mp4
6. 若连续120秒仍返回 code=202，改发原视频

身份码提示规则：
- 免费身份码 → `📊 今日免费剩余：X次（每天10次）`
- 付费计次 → `📊 剩余：X次`
- 时长/永久 → 不显示剩余

## 获取身份码

- **免费体验** — 直接调用 API（不传 key 或传空 key），服务端自动生成5位身份码并返回，每天10次
- **购买套餐** — 用户发送"套餐"查看可选方案，选择后 AI 下单并生成二维码图片，用户扫码支付后同一身份码自动叠加权益

> ⚠️ 下单后必须生成二维码图片发给用户，禁止直接发支付宝链接。创建订单返回 `qr_code` URL，用 `qrencode -o /tmp/qr.png "{qr_code}"` 生成二维码图片，然后用 `send_message` 发 `MEDIA:/tmp/qr.png`。

---

## 🔑 身份码使用规则（简化版）

**API 返回的 `identity_info` 已经告诉你一切，AI 只管读：**

```json
// 免费身份码
{"daily_remaining": 8}       → "📊 今日免费剩余：8次（每天10次）"
// 付费计次
{"remaining": 42}            → "📊 剩余：42次"
// 时长/永久身份码
{"remaining": null}           → 不显示剩余
// 今日免费已用完
{"daily_remaining": 0}        → "📊 今日免费次数已用完，明天再试或购买套餐"
```

**AI 只需要做到：**

1. **始终使用同一个身份码** — 首次调用 API 返回的 `identity_info.code` 即为你的身份码，永久有效
2. **开始对话** → 用已知的身份码调用 API，如无则首次自动生成
3. **用户买了套餐** → 查支付结果，拿到身份码确认权益已叠加
4. **调用失败（403 / 429）** → 按 `msg` 区分处理：
   - `msg` 含 **"身份码无效"** → 身份码不存在或被禁用
   - HTTP **429** → 每日10次已用完，引导购买套餐
   - ⚠️ 403/429 返回体不含 `identity_info`
5. **身份码提示按 `identity_info` 显示**
