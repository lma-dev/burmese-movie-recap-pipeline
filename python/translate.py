"""
Step 2b — Translate transcribed lines to Burmese with GPT.

We batch every line for a segment into one prompt to keep cost low and
let the model preserve cross-line context (pronouns, names).

Input (stdin, JSON):
    {
        "lines": [{"line_number": 1, "text": "..."}, ...],
        "openai_api_key": "sk-...",
        "model": "gpt-4o-mini"
    }

Output (stdout, JSON):
    {
        "translations": [{"line_number": 1, "burmese": "..."}, ...]
    }
"""
from __future__ import annotations

import json
import os
import sys

from _common import die, progress, read_payload, write_result

SYSTEM_PROMPT = (
    "You are a professional Burmese subtitler. Translate each numbered line "
    "from the source language into natural, conversational Burmese suitable "
    "for movie-recap subtitles. Keep the SAME number of output lines as "
    "input lines and keep their line numbers. Reply with strict JSON of the "
    "shape {\"translations\":[{\"line_number\":N,\"burmese\":\"...\"}]}."
)


def main() -> None:
    cfg = read_payload()
    lines = cfg.get("lines", [])
    api_key = cfg.get("openai_api_key") or os.environ.get("OPENAI_API_KEY")
    model = cfg.get("model", "gpt-4o-mini")

    if not api_key:
        die("missing openai_api_key")
    if not lines:
        write_result({"translations": []})
        return

    try:
        from openai import OpenAI
    except ImportError:
        die("python `openai` package not installed (pip install openai)")

    client = OpenAI(api_key=api_key)

    progress("translating", 30)

    user_payload = "\n".join(f'{l["line_number"]}. {l["text"]}' for l in lines)

    completion = client.chat.completions.create(
        model=model,
        response_format={"type": "json_object"},
        messages=[
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user",   "content": user_payload},
        ],
    )
    raw = completion.choices[0].message.content or "{}"

    try:
        parsed = json.loads(raw)
        translations = parsed.get("translations", [])
    except json.JSONDecodeError:
        die(f"GPT returned non-JSON: {raw[:500]}")
        return  # unreachable

    progress("done", 100)
    write_result({"translations": translations})


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(130)
