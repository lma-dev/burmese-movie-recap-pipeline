# Prompts

Plain-text prompt templates for the pipeline's AI calls. Kept here so
prompts can be tuned without editing the python scripts.

Loaded from python code via `_common.load_prompt(name)`, which reads
`python/prompts/<name>.txt`.

| File | Used by |
|---|---|
| `transcribe_gemini.txt` | `transcribe.py` (Gemini audio → JSON segments) |
| `translate_system.txt`  | `translate.py` (system prompt, both OpenAI and Gemini) |
