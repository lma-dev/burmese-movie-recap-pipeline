"""
Step 0 — Source

Download the user's pasted URL and return resolved metadata.
Supports anything yt-dlp supports (YouTube, TikTok, Facebook, Xiaohongshu, …).

Input (stdin, JSON):
    {
        "url": "https://...",
        "output_dir": "/abs/path/storage/pipeline/123",
        "ytdlp_bin": "yt-dlp"
    }

Output (stdout, JSON):
    {
        "title": "...",
        "duration_sec": 754,
        "thumbnail_url": "...",
        "local_path": "/abs/path/.../source.mp4"
    }
"""
from __future__ import annotations

import os
import subprocess
import sys

from _common import die, ensure_dir, progress, read_payload, write_result


def main() -> None:
    cfg = read_payload()
    url        = cfg["url"]
    output_dir = ensure_dir(cfg["output_dir"])
    ytdlp_bin  = cfg.get("ytdlp_bin", "yt-dlp")

    progress("connecting", 10)

    # yt-dlp's --print-to-file APPENDS — wipe any stale meta from prior runs
    # so a retry doesn't accumulate duplicate lines that break the parser.
    for fname in (".title", ".duration", ".thumb"):
        p = os.path.join(output_dir, fname)
        if os.path.isfile(p):
            os.remove(p)

    local_template = os.path.join(output_dir, "source.%(ext)s")

    # Single call: download as mp4 + dump info JSON via --print
    cmd = [
        ytdlp_bin,
        "-f", "bv*[ext=mp4]+ba[ext=m4a]/b[ext=mp4]/b",
        "--merge-output-format", "mp4",
        "-o", local_template,
        "--no-progress",
        "--print-to-file", "title,%(title)s",       os.path.join(output_dir, ".title"),
        "--print-to-file", "duration,%(duration)s", os.path.join(output_dir, ".duration"),
        "--print-to-file", "thumbnail,%(thumbnail)s", os.path.join(output_dir, ".thumb"),
        url,
    ]

    progress("downloading", 30)
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        die(f"yt-dlp failed: {res.stderr[-2000:]}")

    progress("downloading", 80)

    # Find downloaded file
    downloaded = None
    for fname in os.listdir(output_dir):
        if fname.startswith("source.") and not fname.startswith(".source"):
            downloaded = os.path.join(output_dir, fname)
            break
    if not downloaded:
        die("yt-dlp finished but no source.* file found")

    title = _read_meta(output_dir, ".title", default=os.path.basename(downloaded))
    duration_raw = _read_meta(output_dir, ".duration", default="0")
    thumbnail = _read_meta(output_dir, ".thumb", default="")

    # Strip leading "key," prefix written by --print-to-file
    title    = title.split(",", 1)[-1].strip()
    duration = int(float(duration_raw.split(",", 1)[-1].strip() or 0))
    thumbnail = thumbnail.split(",", 1)[-1].strip()

    progress("ready", 100)

    write_result({
        "title":         title,
        "duration_sec":  duration,
        "thumbnail_url": thumbnail,
        "local_path":    downloaded,
    })


def _read_meta(output_dir: str, fname: str, default: str = "") -> str:
    p = os.path.join(output_dir, fname)
    if not os.path.isfile(p):
        return default
    try:
        with open(p, "r", encoding="utf-8") as f:
            return f.read().strip()
    except OSError:
        return default


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(130)
