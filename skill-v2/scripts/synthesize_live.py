#!/usr/bin/env python3
"""
Live Photo 本地合成脚本
用法:
  python3 synthesize_live.py <parse_json_file>
  python3 synthesize_live.py --url <douyin_url> --key <api_key>
  curl -s "http://spqsy.kcucu.com/api/parse.php?key=KEY&url=URL" | python3 synthesize_live.py

输出: /tmp/synth_final_<timestamp>.mp4
"""

import sys, os, json, subprocess, shutil, time, base64, urllib.parse
from urllib.request import urlopen, Request

WORK_ROOT = "/tmp"
IMG_DUR = 1.5   # 每张静图时长(秒)
CYCLES = 3      # 循环次数
CANVAS = "1080:1920"  # 统一画布尺寸（宽×高），自动补黑边防止不同比例图片变形
# 画质控制：CRF 越低画质越好。18=视觉无损，23=默认好画质，28=有损（旧值）
QUALITY_CRF = "18"
QUALITY_PRESET = "medium"


def get_img_filename(url):
    """从任意URL格式提取原始图片文件名"""
    # 尝试 svproxy 格式: 解码 proxyurl 参数
    try:
        qs = urllib.parse.parse_qs(urllib.parse.urlparse(url).query)
        proxy = qs.get('proxyurl', [''])[0]
        if proxy:
            raw = base64.b64decode(proxy).decode('utf-8', errors='replace')
            # 原始链接格式: .../filename~tplv-dy-aweme-images:q75.jpeg?...
            name = raw.split('~')[0].split('/')[-1].split('.')[0].split('?')[0]
            if name and not name.startswith('http'):
                return name
    except Exception:
        pass
    # 直链格式
    name = url.split('~')[0].split('/')[-1].split('.')[0].split('?')[0]
    return name


def download(url, outpath, timeout=30):
    """下载文件"""
    try:
        req = Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urlopen(req, timeout=timeout) as r:
            with open(outpath, 'wb') as f:
                f.write(r.read())
        return True
    except Exception as e:
        print(f"  ⚠️  下载失败: {outpath} - {e}", file=sys.stderr)
        return False


def match_images_to_live_photo(images, live_photo):
    """匹配静图与动图段的对应关系
    返回: {images_index: live_photo_index}
    """
    # 提取所有文件名
    img_names = []
    for url in images:
        img_names.append(get_img_filename(url))

    lp_names = []
    for lp in live_photo:
        lp_names.append(get_img_filename(lp.get('image', '')))

    # 精确匹配
    mapping = {}
    for pi, lp_name in enumerate(lp_names):
        for ii, img_name in enumerate(img_names):
            if lp_name and img_name and lp_name == img_name:
                mapping[ii] = pi
                break

    # ⚡ Fallback 1: 直比 URL（svproxy 代理后 URL 一模一样）
    if not mapping:
        for pi, lp in enumerate(live_photo):
            lp_img_url = lp.get('image', '')
            for ii, img_url in enumerate(images):
                if lp_img_url and lp_img_url == img_url:
                    mapping[ii] = pi
                    break

    # ⚡ Fallback 2: 文件名模糊匹配（base64 失败后靠文件名的原始子串）
    if not mapping:
        # 从 base64 代理URL的 proxyurl 参数中取文件名片段
        def extract_raw_name(url):
            try:
                qs = urllib.parse.parse_qs(urllib.parse.urlparse(url).query)
                proxy = qs.get('proxyurl', [''])[0]
                raw = base64.b64decode(proxy + '==').decode('utf-8', errors='replace')
                # .../filename~tplv... or .../filename?...
                name = raw.split('~')[0].split('/')[-1].split('.')[0].split('?')[0]
                if name and not name.startswith('http'):
                    return name
            except Exception:
                pass
            return ''

        img_names = [extract_raw_name(u) for u in images]
        lp_names = [extract_raw_name(lp.get('image', '')) for lp in live_photo]
        for pi, lp_name in enumerate(lp_names):
            for ii, img_name in enumerate(img_names):
                if lp_name and img_name and lp_name == img_name:
                    mapping[ii] = pi
                    break

    # ⚡ Fallback 3: 位置推测（live_photo 在 images[] 中大致均匀分布）
    if not mapping:
        step = len(images) / max(len(live_photo), 1)
        for pi in range(len(live_photo)):
            ii = min(int(round(pi * step + step / 2)), len(images) - 1)
            mapping[ii] = pi

    return mapping


def probe_duration(mp4_path):
    """用ffprobe获取视频时长"""
    r = subprocess.run(
        ["ffprobe", "-v", "error", "-show_entries", "format=duration",
         "-of", "csv=p=0", mp4_path],
        capture_output=True, text=True, timeout=15)
    return float(r.stdout.strip()) if r.stdout.strip() else 3.0


def synthesize(data, work_dir):
    """核心合成逻辑"""
    images = data.get('images', [])
    live_photo = data.get('live_photo', [])
    music = data.get('music') or {}

    TOTAL_IMGS = len(images)
    TOTAL_LP = len(live_photo)

    if TOTAL_IMGS == 0:
        print("❌ 没有图片素材", file=sys.stderr)
        return None

    print(f"📸 静图: {TOTAL_IMGS}张 | 🎬 动图段: {TOTAL_LP}段 | 🎵 配乐: {'有' if music.get('url') else '无'}")

    # 1. 下载素材
    print("⬇️  下载素材...")
    for i, url in enumerate(images):
        download(url, f"{work_dir}/img_{i}.jpg")
    for pi, lp in enumerate(live_photo):
        download(lp['video'], f"{work_dir}/live_{pi}.mp4")
    if music.get('url'):
        download(music['url'], f"{work_dir}/bgm.mp3")

    # 2. 匹配动图位置
    lp_map = match_images_to_live_photo(images, live_photo)
    print(f"🔗 匹配: {lp_map}")

    # 3. 生成动图段（全部 ss=0！）
    print("🎞️  生成动图段 (ss=0)...")
    lp_durs = []
    for pi in range(TOTAL_LP):
        dur = probe_duration(f"{work_dir}/live_{pi}.mp4")
        lp_durs.append(dur)

        # loop 到 20s 以上
        subprocess.run(
            ["ffmpeg", "-y", "-stream_loop", "6", "-i", f"{work_dir}/live_{pi}.mp4",
             "-t", "20", "-r", "30",
             "-vf", f"scale={CANVAS}:force_original_aspect_ratio=1,pad={CANVAS}:(ow-iw)/2:(oh-ih)/2:color=black",
             "-c:v", "libx264", "-preset", QUALITY_PRESET, "-crf", QUALITY_CRF, "-an",
             f"{work_dir}/motion_{pi}.mp4"],
            capture_output=True, check=True, timeout=120)

        # 关键：ss=0，从同一画面起点切
        subprocess.run(
            ["ffmpeg", "-y", "-i", f"{work_dir}/motion_{pi}.mp4",
             "-ss", "0", "-t", str(dur),
             "-c:v", "libx264", "-preset", QUALITY_PRESET, "-crf", QUALITY_CRF,
             "-g", "1", "-an", "-fflags", "+genpts", f"{work_dir}/m_{pi}.mp4"],
            capture_output=True, check=True, timeout=120)

    # 4. 按顺序构建播放列表
    concat_lines = []
    for cycle in range(CYCLES):
        for ii in range(TOTAL_IMGS):
            # 每段动图每个cycle都播
            if ii in lp_map:
                pi = lp_map[ii]
                concat_lines.append(f"file {work_dir}/m_{pi}.mp4")

            # 静图段
            still = f"{work_dir}/still_{cycle}_{ii}.mp4"
            subprocess.run(
                ["ffmpeg", "-y", "-loop", "1", "-i", f"{work_dir}/img_{ii}.jpg",
                 "-t", str(IMG_DUR), "-r", "30",
                 "-vf", f"scale={CANVAS}:force_original_aspect_ratio=1,pad={CANVAS}:(ow-iw)/2:(oh-ih)/2:color=black",
                 "-c:v", "libx264", "-preset", QUALITY_PRESET, "-crf", QUALITY_CRF,
                 "-g", "1", "-an", "-fflags", "+genpts", still],
                capture_output=True, check=True, timeout=60)
            concat_lines.append(f"file {still}")

    # 5. 合并视频
    print(f"🔄 合并 {len(concat_lines)} 段...")
    with open(f"{work_dir}/list.txt", "w") as f:
        f.write("\n".join(concat_lines))

    subprocess.run(
        ["ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", f"{work_dir}/list.txt",
         "-fflags", "+genpts", "-r", "30",
         "-c:v", "copy",
         f"{work_dir}/video.mp4"],
        capture_output=True, check=True, timeout=300)

    vd = probe_duration(f"{work_dir}/video.mp4")

    # 6. 加配乐
    bgm = f"{work_dir}/bgm.mp3"
    if os.path.exists(bgm):
        print(f"🎵 加配乐 ({vd:.0f}s)...")
        subprocess.run(
            ["ffmpeg", "-y", "-i", f"{work_dir}/video.mp4", "-stream_loop", "-1",
             "-i", bgm, "-filter_complex", f"[1:a]volume=1.0,atrim=0:{vd}[bgm]",
             "-map", "0:v", "-map", "[bgm]", "-c:v", "copy", "-c:a", "aac", "-b:a", "96k",
             "-movflags", "+faststart", f"{work_dir}/final.mp4"],
            capture_output=True, check=True, timeout=120)
    else:
        # 无配乐，用第1段动图原声
        subprocess.run(
            ["ffmpeg", "-y", "-i", f"{work_dir}/video.mp4", "-stream_loop", "6",
             "-i", f"{work_dir}/live_0.mp4", "-filter_complex",
             f"[1:a]volume=1.3,atrim=0:{vd}[orig]",
             "-map", "0:v", "-map", "[orig]", "-c:v", "copy", "-c:a", "aac", "-b:a", "96k",
             "-movflags", "+faststart", f"{work_dir}/final.mp4"],
            capture_output=True, check=True, timeout=120)

    sz_mb = os.path.getsize(f"{work_dir}/final.mp4") / 1024 / 1024
    print(f"✅ 完成! {sz_mb:.1f}MB, {vd:.0f}s")
    return f"{work_dir}/final.mp4"


def main():
    # 从 stdin 读取 JSON（管道模式）
    if len(sys.argv) == 1 and not sys.stdin.isatty():
        stdin_data = sys.stdin.read().strip()
        if stdin_data:
            raw = json.loads(stdin_data)
            data = raw.get('data', raw)
            return _run(data)
        print(__doc__, file=sys.stderr)
        sys.exit(1)

    if len(sys.argv) < 2:
        print(__doc__, file=sys.stderr)
        sys.exit(1)

    # 获取 parse 数据
    if sys.argv[1] == '--url':
        # 模式: --url <url> --key <key>
        url = sys.argv[2]
        key = sys.argv[4] if len(sys.argv) > 4 else os.environ.get('API_KEY', '')
        api = f"http://spqsy.kcucu.com/api/parse.php?key={key}&url={urllib.parse.quote(url)}"
        print(f"🔍 解析: {url}")
        req = Request(api, headers={'User-Agent': 'Mozilla/5.0'})
        with urlopen(req, timeout=60) as r:
            data = json.loads(r.read())
        if data.get('code') != 200:
            print(f"❌ 解析失败: {data.get('msg')}", file=sys.stderr)
            sys.exit(1)
        data = data['data']
    elif sys.argv[1] == '--file':
        # 模式: --file <json_file>
        with open(sys.argv[2]) as f:
            raw = json.load(f)
        data = raw.get('data', raw)
    else:
        # 从 stdin 或文件读取 JSON
        if os.path.exists(sys.argv[1]):
            with open(sys.argv[1]) as f:
                raw = json.load(f)
        else:
            raw = json.loads(sys.argv[1])
        data = raw.get('data', raw)

    # 创建临时工作目录
    ts = int(time.time())
    work_dir = f"{WORK_ROOT}/synth_{ts}"
    os.makedirs(work_dir, exist_ok=True)

    try:
        result = synthesize(data, work_dir)
        if result:
            out = f"{WORK_ROOT}/synth_final_{ts}.mp4"
            shutil.copy(result, out)
            print(f"📁 输出: {out}")
            return out
    finally:
        shutil.rmtree(work_dir, ignore_errors=True)

    return None


def _run(data):
    """内部运行入口"""
    ts = int(time.time())
    work_dir = f"{WORK_ROOT}/synth_{ts}"
    os.makedirs(work_dir, exist_ok=True)
    try:
        result = synthesize(data, work_dir)
        if result:
            out = f"{WORK_ROOT}/synth_final_{ts}.mp4"
            shutil.copy(result, out)
            print(f"📁 输出: {out}")
            return out
    finally:
        shutil.rmtree(work_dir, ignore_errors=True)
    return None


if __name__ == '__main__':
    main()
