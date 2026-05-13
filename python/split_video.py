"""
Step 1 — Split

Cut the source mp4 into N segments of `segment_seconds`. Uses ffmpeg's
segment muxer with `-c copy` so the split is stream-copy fast and lossless.

Input (stdin, JSON):
    {
        "source_path": "/abs/.../source.mp4",
        "output_dir":  "/abs/.../segments",
        "segment_seconds": 120,
        "ffmpeg_bin": "ffmpeg",
        "ffprobe_bin": "ffprobe"
    }

Output (stdout, JSON):
    {
        "segments": [
            {"part_number": 1, "start_sec": 0,   "end_sec": 120, "path": ".../part_001.mp4"},
            ...
        ]
    }
"""
from __future__ import annotations

import glob
import os
import re
import subprocess
import sys

from _common import die, ensure_dir, progress, read_payload, write_result


def probe_duration(ffprobe: str, path: str) -> int:
    res = subprocess.run(
        [ffprobe, "-v", "error", "-show_entries", "format=duration",
         "-of", "default=noprint_wrappers=1:nokey=1", path],
        capture_output=True, text=True,
    )
    if res.returncode != 0:
        die(f"ffprobe failed: {res.stderr}")
    try:
        return int(float(res.stdout.strip()))
    except ValueError:
        die(f"ffprobe returned non-numeric duration: {res.stdout!r}")
    return 0  # unreachable


def main() -> None:
    cfg = read_payload()
    source  = cfg["source_path"]
    out_dir = ensure_dir(cfg["output_dir"])
    seg_s   = int(cfg.get("segment_seconds", 120))
    ffmpeg  = cfg.get("ffmpeg_bin", "ffmpeg")
    ffprobe = cfg.get("ffprobe_bin", "ffprobe")

    if not os.path.isfile(source):
        die(f"source not found: {source}")

    progress("probing", 10)
    duration = probe_duration(ffprobe, source)

    progress("splitting", 30)
    pattern = os.path.join(out_dir, "part_%03d.mp4")

    # Wipe any stale parts from a previous run.
    for stale in glob.glob(os.path.join(out_dir, "part_*.mp4")):
        os.remove(stale)

    cmd = [
        ffmpeg, "-y", "-i", source,
        "-c", "copy", "-map", "0",
        "-f", "segment",
        "-segment_time", str(seg_s),
        "-reset_timestamps", "1",
        pattern,
    ]
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        die(f"ffmpeg split failed: {res.stderr[-2000:]}")

    progress("indexing", 80)
    parts = sorted(glob.glob(os.path.join(out_dir, "part_*.mp4")))
    segments = []
    for i, path in enumerate(parts):
        start = i * seg_s
        end   = min((i + 1) * seg_s, duration)
        segments.append({
            "part_number": i + 1,
            "start_sec":   start,
            "end_sec":     end,
            "path":        path,
        })

    progress("done", 100)
    write_result({"segments": segments, "total_duration_sec": duration})


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(130)
