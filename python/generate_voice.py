"""
Step 4 — Generate the Burmese narrator track for a single segment
         using Microsoft Edge TTS (free, no API key).

Glues all subtitle lines into one continuous narration aligned to their
start times by inserting silence padding between lines.

Input (stdin, JSON):
    {
        "lines": [{"line_number":1,"start_ms":0,"end_ms":3500,"burmese":"..."}, ...],
        "voice": "my-MM-NilarNeural",
        "segment_duration_ms": 120000,
        "output_path": "/abs/.../part_001.voice.wav",
        "ffmpeg_bin": "ffmpeg"
    }

Output (stdout, JSON):
    {
        "voice_path": "/abs/.../part_001.voice.wav"
    }
"""
from __future__ import annotations

import asyncio
import os
import subprocess
import sys
import tempfile

from _common import die, ensure_dir, progress, read_payload, write_result


async def _synth_one(voice: str, text: str, dest_mp3: str) -> None:
    import edge_tts
    communicate = edge_tts.Communicate(text=text, voice=voice)
    await communicate.save(dest_mp3)


def _ffmpeg_concat(ffmpeg: str, parts: list[tuple[int, str]], duration_ms: int, output: str) -> None:
    """
    `parts` is [(start_ms, mp3_path), ...]. We build a filter that lays each
    chunk on a silent base at its start position with `adelay`, then mixes
    them down with `amix`.
    """
    if not parts:
        # No narration — produce a silent track the length of the segment.
        cmd = [ffmpeg, "-y", "-f", "lavfi",
               "-i", f"anullsrc=channel_layout=stereo:sample_rate=44100",
               "-t", f"{duration_ms/1000:.3f}",
               output]
        res = subprocess.run(cmd, capture_output=True, text=True)
        if res.returncode != 0:
            die(f"ffmpeg silence failed: {res.stderr[-1000:]}")
        return

    inputs = []
    for _, p in parts:
        inputs.extend(["-i", p])

    filters = []
    for idx, (start_ms, _) in enumerate(parts):
        filters.append(f"[{idx}:a]adelay={start_ms}|{start_ms},apad[a{idx}]")
    join = "".join(f"[a{i}]" for i in range(len(parts)))
    filters.append(
        f"{join}amix=inputs={len(parts)}:duration=longest:dropout_transition=0,"
        f"atrim=0:{duration_ms/1000:.3f}[out]"
    )

    cmd = [ffmpeg, "-y", *inputs,
           "-filter_complex", ";".join(filters),
           "-map", "[out]",
           "-ar", "44100", "-ac", "2",
           output]
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        die(f"ffmpeg mix failed: {res.stderr[-1500:]}")


async def _run(cfg: dict) -> None:
    lines = cfg.get("lines", [])
    voice = cfg.get("voice", "my-MM-NilarNeural")
    output = cfg["output_path"]
    duration_ms = int(cfg.get("segment_duration_ms", 0))
    ffmpeg = cfg.get("ffmpeg_bin", "ffmpeg")
    ensure_dir(os.path.dirname(output))

    try:
        import edge_tts  # noqa: F401
    except ImportError:
        die("python `edge-tts` package not installed (pip install edge-tts)")

    with tempfile.TemporaryDirectory() as tmp:
        parts: list[tuple[int, str]] = []
        total = max(1, len(lines))
        for idx, line in enumerate(lines):
            text = (line.get("burmese") or "").strip()
            if not text:
                continue
            mp3_path = os.path.join(tmp, f"line_{idx:04d}.mp3")
            await _synth_one(voice, text, mp3_path)
            parts.append((int(line.get("start_ms", 0)), mp3_path))
            progress("synthesizing", int((idx + 1) / total * 80))

        progress("mixing", 90)
        _ffmpeg_concat(ffmpeg, parts, duration_ms, output)

    progress("done", 100)
    write_result({"voice_path": output})


def main() -> None:
    cfg = read_payload()
    asyncio.run(_run(cfg))


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(130)
