"""
Step 2a — Transcribe a single segment.

Provider is selectable via `provider` in the payload (or AI_PROVIDER env):
    - "openai"  → Whisper (audio.transcriptions, verbose_json with segments)
    - "gemini"  → Gemini Files API + JSON-mode prompt for SRT-style segments

Both return the same shape so downstream code is unchanged.

Input (stdin, JSON):
    {
        "segment_path": "/abs/.../part_001.mp4",
        "provider":     "openai" | "gemini",
        "api_key":      "...",
        "model":        "whisper-1" | "gemini-2.5-flash"
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

import json
import os
import re
import sys
import time

from _common import die, progress, read_payload, write_result


def main() -> None:
    cfg = read_payload()
    seg_path = cfg["segment_path"]
    provider = (cfg.get("provider") or os.environ.get("AI_PROVIDER") or "openai").lower()
    api_key  = cfg.get("api_key") or os.environ.get(
        "GEMINI_API_KEY" if provider == "gemini" else "OPENAI_API_KEY"
    )
    model    = cfg.get("model")

    if not api_key:
        die(f"missing api_key for provider={provider}")
    if not os.path.isfile(seg_path):
        die(f"segment not found: {seg_path}")

    if provider == "gemini":
        lines = transcribe_gemini(seg_path, api_key, model or "gemini-2.5-flash")
    elif provider == "openai":
        lines = transcribe_openai(seg_path, api_key, model or "whisper-1")
    else:
        die(f"unknown provider: {provider}")
        return  # unreachable

    progress("done", 100)
    write_result({"lines": lines})


# ---------------------------------------------------------------------------
# OpenAI Whisper
# ---------------------------------------------------------------------------
def transcribe_openai(seg_path: str, api_key: str, model: str) -> list[dict]:
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
    return lines


# ---------------------------------------------------------------------------
# Google Gemini
# ---------------------------------------------------------------------------
GEMINI_TRANSCRIBE_PROMPT = (
    "Transcribe the spoken audio in this clip. Split it into short "
    "subtitle-style segments (3–8 seconds each). Reply with strict JSON "
    "of the shape "
    "{\"segments\":[{\"start\":\"MM:SS.mmm\",\"end\":\"MM:SS.mmm\",\"text\":\"...\"}]} "
    "with no commentary. Preserve the original spoken language — do not translate."
)


def transcribe_gemini(seg_path: str, api_key: str, model: str) -> list[dict]:
    try:
        from google import genai
        from google.genai import types
    except ImportError:
        die("python `google-genai` package not installed (pip install google-genai)")

    client = genai.Client(api_key=api_key)

    progress("uploading", 20)
    uploaded = client.files.upload(file=seg_path)

    # Files must reach ACTIVE state before they can be used in a prompt.
    progress("processing", 40)
    deadline = time.time() + 300
    while uploaded.state.name == "PROCESSING":
        if time.time() > deadline:
            die("gemini file upload timed out (>5min in PROCESSING)")
        time.sleep(2)
        uploaded = client.files.get(name=uploaded.name)

    if uploaded.state.name != "ACTIVE":
        die(f"gemini file upload failed: state={uploaded.state.name}")

    progress("transcribing", 60)
    response = client.models.generate_content(
        model=model,
        contents=[uploaded, GEMINI_TRANSCRIBE_PROMPT],
        config=types.GenerateContentConfig(response_mime_type="application/json"),
    )
    raw = (response.text or "").strip()

    try:
        parsed = json.loads(raw)
        segments = parsed.get("segments", [])
    except json.JSONDecodeError:
        die(f"Gemini returned non-JSON: {raw[:500]}")
        return  # unreachable

    progress("parsing", 85)
    lines = []
    for idx, seg in enumerate(segments, start=1):
        text = str(seg.get("text", "")).strip()
        if not text:
            continue
        lines.append({
            "line_number": idx,
            "start_ms":    _timestamp_to_ms(seg.get("start", "00:00")),
            "end_ms":      _timestamp_to_ms(seg.get("end", "00:00")),
            "text":        text,
        })
    return lines


_TS_RE = re.compile(r"^(?:(\d+):)?(\d+):(\d+)(?:\.(\d+))?$")


def _timestamp_to_ms(ts: str | int | float) -> int:
    """Accept 'MM:SS.mmm', 'HH:MM:SS.mmm', or raw numeric seconds."""
    if isinstance(ts, (int, float)):
        return int(float(ts) * 1000)
    s = str(ts).strip()
    m = _TS_RE.match(s)
    if not m:
        # Last resort: try float seconds
        try:
            return int(float(s) * 1000)
        except ValueError:
            return 0
    h = int(m.group(1) or 0)
    mm = int(m.group(2) or 0)
    ss = int(m.group(3) or 0)
    frac = m.group(4) or ""
    # pad/truncate fractional part to milliseconds (3 digits)
    frac = (frac + "000")[:3] if frac else "0"
    ms = int(frac)
    return ((h * 3600) + (mm * 60) + ss) * 1000 + ms


def _get(obj, key, default):
    if isinstance(obj, dict):
        return obj.get(key, default)
    return getattr(obj, key, default)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(130)
