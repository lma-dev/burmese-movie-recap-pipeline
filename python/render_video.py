"""
Step 5 — Render a single segment with burned-in Burmese subtitles,
         the narrator audio, and the frame-size preset (reel 9:16 etc.).

Pipeline inside ffmpeg:
    1) Scale + crop the segment to the target frame (e.g. 1080x1920)
       so the subject stays centered no matter the source aspect ratio.
    2) Burn the SRT in with the `subtitles` filter and a Noto Sans
       Myanmar style.
    3) Mix the narrator audio with the source audio according to
       `audio_mix_mode` — "replace" drops the source, "duck" keeps it
       at ~ -18 dB whenever the narrator is speaking (via sidechaincompress).

Input (stdin, JSON):
    {
        "segment_path": "/abs/.../part_001.mp4",
        "srt_path":     "/abs/.../part_001.srt",
        "voice_path":   "/abs/.../part_001.voice.wav",
        "output_path":  "/abs/.../part_001.rendered.mp4",

        "preset": {"width":1080,"height":1920,"fps":30,"bitrate":"6M"},
        "audio_mix_mode": "duck",
        "duck_db": -18,

        "ffmpeg_bin": "ffmpeg",
        "myanmar_font_dir": "/abs/.../fonts"
    }

Output (stdout, JSON):
    {
        "rendered_path": "/abs/.../part_001.rendered.mp4"
    }
"""
from __future__ import annotations

import os
import shlex
import subprocess
import sys

from _common import die, ensure_dir, progress, read_payload, write_result


def main() -> None:
    cfg = read_payload()

    seg    = cfg["segment_path"]
    srt    = cfg.get("srt_path")
    voice  = cfg.get("voice_path")
    output = cfg["output_path"]
    preset = cfg["preset"]
    mix    = cfg.get("audio_mix_mode", "duck")
    duck   = int(cfg.get("duck_db", -18))
    ffmpeg = cfg.get("ffmpeg_bin", "ffmpeg")
    font_dir = cfg.get("myanmar_font_dir")

    if not os.path.isfile(seg):
        die(f"segment not found: {seg}")

    ensure_dir(os.path.dirname(output))

    w   = int(preset["width"])
    h   = int(preset["height"])
    fps = int(preset.get("fps", 30))
    br  = preset.get("bitrate", "6M")

    progress("rendering", 10)

    # ---------- Video filtergraph ----------
    # Scale source to cover the canvas then crop to exact (W,H).
    vf_parts = [
        f"scale={w}:{h}:force_original_aspect_ratio=increase",
        f"crop={w}:{h}",
        f"fps={fps}",
    ]
    if srt and os.path.isfile(srt):
        # Build the subtitles filter via filter_complex on a separate line
        # so we can hand-craft escaping rather than fight ffmpeg's filterchain
        # parser, which gets confused by commas inside force_style.
        def _esc_path(p: str) -> str:
            return p.replace("\\", "\\\\").replace(":", r"\:").replace("'", r"\'")

        srt_arg = _esc_path(srt)
        style = (
            "FontName=Noto Sans Myanmar,FontSize=22,PrimaryColour=&H00FFFFFF,"
            "OutlineColour=&H00000000,BorderStyle=3,Outline=2,Shadow=0,"
            "Alignment=2,MarginV=80"
        )
        if font_dir:
            vf_parts.append(f"subtitles={srt_arg}:fontsdir={_esc_path(font_dir)}:force_style='{style}'")
        else:
            vf_parts.append(f"subtitles={srt_arg}:force_style='{style}'")

    vf = ",".join(vf_parts)

    # ---------- Audio filtergraph ----------
    has_voice = bool(voice and os.path.isfile(voice))
    audio_inputs = [seg]
    audio_filter = None
    audio_map = None

    if has_voice:
        audio_inputs.append(voice)
        if mix == "replace":
            # Use only the narrator track.
            audio_filter = "[1:a]anull[aout]"
            audio_map = "[aout]"
        else:
            # Duck the source under the narrator with sidechaincompress, then
            # mix back in the narrator at full level.
            # `normalize=0` on amix is critical: the default 1/N normalization
            # halves every input, which alone was burying the narrator ~6 dB.
            # The lines from generate_voice.py also need normalize=0 so the
            # narrator track itself arrives at full volume.
            audio_filter = (
                "[0:a]volume=1.0[src];"
                "[src][1:a]sidechaincompress=threshold=0.05:ratio=8:attack=5:"
                f"release=200:makeup=1[src_ducked];"
                "[src_ducked][1:a]amix=inputs=2:duration=longest:"
                "dropout_transition=0:normalize=0[aout]"
            )
            audio_map = "[aout]"
    else:
        audio_map = "0:a?"

    # ---------- ffmpeg command ----------
    cmd = [ffmpeg, "-y"]
    cmd += ["-i", seg]
    if has_voice:
        cmd += ["-i", voice]
    cmd += ["-vf", vf]
    if audio_filter:
        cmd += ["-filter_complex", audio_filter]
        cmd += ["-map", "0:v:0", "-map", audio_map]
    else:
        cmd += ["-map", "0:v:0", "-map", audio_map]

    cmd += ["-c:v", "libx264", "-preset", "medium", "-b:v", br, "-pix_fmt", "yuv420p"]
    cmd += ["-c:a", "aac", "-b:a", "192k"]
    cmd += [output]

    progress("encoding", 40)
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        die(f"ffmpeg render failed: {res.stderr[-2000:]}")

    progress("done", 100)
    write_result({"rendered_path": output})


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(130)
