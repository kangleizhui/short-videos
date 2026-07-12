# 动图素材处理指南

## 处理流程

当 `parse.php` 返回 `type: live` 时，响应包含：

```json
{
  "data": {
    "type": "live",
    "images": ["静图URL1", "静图URL2", ...],
    "live_photo": [
      {"video": "动图段1_MP4", "image": "该段对应静图"},
      {"video": "动图段2_MP4", "image": "该段对应静图"}
    ],
    "music": {"title": "歌名", "url": "配乐MP3"},
    "url": ""
  }
}
```

直接下载所有素材发给用户即可。

## 下载要点

### 1. 动图段 live_photo[].video

每段是一个**短视频（MP4）**，几秒到十几秒不等，可以直接播放。

```bash
# 需要带 UA 和 Referer，否则 CDN 返回错误
UA="Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36"
REFERER="https://www.douyin.com/"

curl -sL -A "$UA" -e "$REFERER" "https://v3-dy-o.zjcdn.com/..." -o live_1.mp4 --max-time 30
```

> ⚠️ 抖音 CDN 链接有过期时间，解析后尽快下载。
> 不带 UA/Referer 下载会得到 238 字节的 HTML 错误页。

### 2. 静图 data.images[]

静图可直接下载，无需额外头。

### 3. 配乐 data.music.url

直接下载 MP3，通常不需要特殊头。

## 发送策略

| 素材类型 | 数量 | 发送方式 |
|---------|------|---------|
| 动图段 (live_photo[].video) | 1~N 段 | **逐段发送**，每段自带动态效果 |
| 静图 (data.images[]) | 0~N 张 | 作为图片发送 |
| 配乐 (data.music.url) | 0~1 个 | 作为音频发送（仅 image / live 类型） |
| 封面 (data.cover) | 0~1 张 | 可选，作为图片发送 |

> **资源较大提醒：** 如果素材总量超过 **10MB**，先告知用户「资源较大，下载和发送大约需要 3-5 分钟，请稍等」再开始处理。

## 全量下载脚本示例

```bash
# 1. 解析
RESULT=$(curl -s "https://spqsy.kcucu.com/api/parse.php?code=CODE&url=链接")

# 2. 提取链接
LIVE_VIDEOS=$(python3 -c "
import sys,json
d=json.load(sys.stdin)
lps = d.get('data',{}).get('live_photo',[])
for lp in lps: print(lp.get('video',''))
" <<< "$RESULT")

MUSIC_URL=$(python3 -c "
import sys,json
d=json.load(sys.stdin)
print(d.get('data',{}).get('music',{}).get('url',''))
" <<< "$RESULT")

# 3. 下载
UA="Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36"
REF="https://www.douyin.com/"
i=1
for url in $LIVE_VIDEOS; do
    curl -sL -A "$UA" -e "$REF" "$url" -o "live_${i}.mp4" --max-time 30
    i=$((i+1))
done
curl -sL "$MUSIC_URL" -o music.mp3 --max-time 15

# 4. 发送 → 逐段发给用户
```
