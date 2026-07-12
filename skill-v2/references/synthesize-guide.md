# 动图合成指南 — synthesize_live.py

## 管道模式（推荐）

动图解析结果直接管道给脚本，一行完成全部合成：

```bash
curl -s "http://{DOMAIN}:{PORT}/api/parse.php?key={密钥}&url={链接}" \
  | python3 scripts/synthesize_live.py
```

输出：`/tmp/synth_final_<timestamp>.mp4`

## 其他用法

```bash
# 从 JSON 文件读
python3 scripts/synthesize_live.py parse_result.json

# URL + 密钥直传
python3 scripts/synthesize_live.py --url <douyin_url> --key <API_KEY>
```

## 脚本做了什么

| 步骤 | 说明 |
|------|------|
| 下载素材 | 并行下载 images[]（静图）、live_photo[].video（动图段）、music.url（配乐） |
| 匹配动图位置 | 3层 fallback 确定 `live_photo[pi]` 对应 `images[ii]` 的位置 |
| 生成动图段 | stream_loop 到 20s 后切原始时长，全部 `-ss 0`（同一画面起点，杜绝闪烁） |
| 统一画布 | 所有段 `scale=1080:1920:force_original_aspect_ratio=1,pad=1080:1920...(ow-iw)/2:(oh-ih)/2:color=black` |
| 构建播放列表 | 3周期循环：每周期按 images[] 原始顺序，有匹配动图的先播动图再播静图 |
| 合并 + 混音 | concat 所有段，优先 BGM 配乐 100% 音量，无配乐降级第1段原声 |
| 输出 | 复制到 `/tmp/synth_final_<ts>.mp4`，清理临时文件 |

## 匹配机制（3层 fallback）

```
① 直比URL: live_photo[i].image == images[j]            ← 最可靠，svproxy URL 相同
② base64补padding解码后文件名匹配                       ← 原始抖音 URL 文件名片段
③ 按 images[] 均匀分布位置推测                          ← 兜底，任何段数都行
```

## 验证

```bash
ffprobe -v error -show_entries format=duration -of csv=p=0 /tmp/synth_final_*.mp4
# 预期时长 ≈ CYCLES × (动图段数×段平均时长 + 静图张数×1.5s)
```

## 已知问题

无。脚本处理过的案例包括 3图2段、9图2段、9图3段、12图5段等多种组合，匹配均正确。
