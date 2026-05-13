"""
Step 2a — Transcribe a single segment with OpenAI Whisper.

Returns SRT-style segments (start_ms / end_ms / text) for the next step
(translate.py) to convert into Burmese.

Input (stdin, JSON):
    {
        "segment_path": "/abs/.../part_001.mp4",
        "openai_api_key": "sk-...",
        "model": "whisper-1"
    }

Output (stdout, JSON):
    {
        "lines": [
            {"line_number": 1, "start_ms": 0, "end_ms": 3500, "text": "..."},
            ...
        ]
    }
"""
from __future__ import annotations

import os
import sys

from _common import die, progress, read_payload, write_result


def main() -> None:
    cfg = read_payload()
    seg_path = cfg["segment_path"]
    api_key  = cfg.get("openai_api_key") or os.environ.get("OPENAI_API_KEY")
    model    = cfg.get("model", "whisper-1")

    if not api_key:
        die("missing openai_api_key")
    if not os.path.isfile(seg_path):
        die(f"segment not found: {seg_path}")

    try:
        from openai import OpenAI
    except ImportError:
        die("python `openai` package not installed (pip install openai)")

    client = OpenAI(api_key=api_key)

    progress("uploading", 25)
    with open(seg_path, "rb") as fh:
        # `verbose_json` returns timestamped segments which we need for SRT.
        result = client.audio.transcriptions.create(
            model=model,
            file=fh,
            response_format="verbose_json",
            timestamp_granularities=["segment"],
        )

    progress("parsing", 80)
    lines = []
    segments = getattr(result, "segments", None) or []
    for idx, seg in enumerate(segments, start=1):
        # `seg` may be a dict or a pydantic object depending on SDK version
        start = float(_get(seg, "start", 0))
        end   = float(_get(seg, "end", 0))
        text  = str(_get(seg, "text", "")).strip()
        if not text:
            continue
        lines.append({
            "line_number": idx,
            "start_ms":    int(start * 1000),
            "end_ms":      int(end * 1000),
            "text":        text,
        })

    progress("done", 100)
    write_result({"lines": lines})


def _get(obj, key, default):
    if isinstance(obj, dict):
        return obj.get(key, default)
    return getattr(obj, key, default)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(130)
