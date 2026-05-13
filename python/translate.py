"""
Step 2b — Translate transcribed lines to Burmese.

Provider is selectable via `provider` in the payload (or AI_PROVIDER env):
    - "openai"  → chat.completions JSON mode
    - "gemini"  → models.generate_content with JSON response_mime_type

We batch every line for a segment into one prompt to keep cost low and
let the model preserve cross-line context (pronouns, names).

Input (stdin, JSON):
    {
        "lines":    [{"line_number": 1, "text": "..."}, ...],
        "provider": "openai" | "gemini",
        "api_key":  "...",
        "model":    "gpt-4o-mini" | "gemini-2.5-flash"
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

from _common import die, load_prompt, progress, read_payload, write_result

SYSTEM_PROMPT = load_prompt("translate_system")


def main() -> None:
    cfg = read_payload()
    lines = cfg.get("lines", [])
    provider = (cfg.get("provider") or os.environ.get("AI_PROVIDER") or "openai").lower()
    api_key = cfg.get("api_key") or os.environ.get(
        "GEMINI_API_KEY" if provider == "gemini" else "OPENAI_API_KEY"
    )
    model = cfg.get("model")

    if not api_key:
        die(f"missing api_key for provider={provider}")
    if not lines:
        write_result({"translations": []})
        return

    user_payload = "\n".join(f'{l["line_number"]}. {l["text"]}' for l in lines)
    progress("translating", 30)

    if provider == "gemini":
        translations = translate_gemini(user_payload, api_key, model or "gemini-2.5-flash")
    elif provider == "openai":
        translations = translate_openai(user_payload, api_key, model or "gpt-4o-mini")
    else:
        die(f"unknown provider: {provider}")
        return  # unreachable

    progress("done", 100)
    write_result({"translations": translations})


# ---------------------------------------------------------------------------
# OpenAI
# ---------------------------------------------------------------------------
def translate_openai(user_payload: str, api_key: str, model: str) -> list[dict]:
    try:
        from openai import OpenAI
    except ImportError:
        die("python `openai` package not installed (pip install openai)")

    client = OpenAI(api_key=api_key)
    completion = client.chat.completions.create(
        model=model,
        response_format={"type": "json_object"},
        messages=[
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user",   "content": user_payload},
        ],
    )
    raw = completion.choices[0].message.content or "{}"
    return _parse_translations(raw)


# ---------------------------------------------------------------------------
# Google Gemini
# ---------------------------------------------------------------------------
def translate_gemini(user_payload: str, api_key: str, model: str) -> list[dict]:
    try:
        from google import genai
        from google.genai import types
    except ImportError:
        die("python `google-genai` package not installed (pip install google-genai)")

    client = genai.Client(api_key=api_key)
    response = client.models.generate_content(
        model=model,
        contents=[user_payload],
        config=types.GenerateContentConfig(
            system_instruction=SYSTEM_PROMPT,
            response_mime_type="application/json",
        ),
    )
    return _parse_translations(response.text or "{}")


def _parse_translations(raw: str) -> list[dict]:
    try:
        parsed = json.loads(raw)
    except json.JSONDecodeError:
        die(f"AI returned non-JSON: {raw[:500]}")
        return  # unreachable
    return parsed.get("translations", [])


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(130)
