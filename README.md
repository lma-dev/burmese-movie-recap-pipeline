# Burmese Movie Recap Pipeline

A 6-step Laravel + Livewire app that turns a video URL into a Burmese-narrated, subtitled reel.

Paste a URL → split the video → transcribe & translate to Burmese → review/edit the SRT → generate a Burmese narrator track → render each segment with burned-in subtitles, sized for **Facebook Reels / TikTok / YouTube Shorts** (all 9:16 1080×1920).

This repo implements the **Burmese Pipeline v2** design from `claude.ai/design` — pixel-for-pixel tokens, six ordered steps, narrator-only, no lip-sync.

---

## The six steps

| #  | Step       | Auto? | What it does                                                              |
|----|------------|-------|---------------------------------------------------------------------------|
| 0  | Source     | no    | Paste URL → `yt-dlp` downloads + grabs metadata                           |
| 1  | Split      | no    | `ffmpeg -segment_time` slices the source into 1–5 min parts               |
| 2  | Translate  | **yes** | Whisper transcribes each part → GPT-4o-mini translates to Burmese       |
| 3  | Edit SRT   | no    | Refine the Burmese subtitle lines (live preview)                          |
| 4  | Voice      | **yes** | Microsoft `edge-tts` (free) generates a narrator track per segment      |
| 5  | Render     | **yes** | `ffmpeg` burns subs, mixes narrator, scales/crops to the chosen frame   |

Each automated step is a queue batch — the UI polls every 2–3 s and reflects per-segment progress.

---

## Architecture at a glance

```
┌─────────────────┐                            ┌──────────────────────┐
│  Livewire pages │  ─── dispatch jobs ────►   │  Laravel queue (db)  │
│  (6 step views) │                            │   FetchSourceJob     │
│                 │  ◄─── poll every 2-3s ───  │   SplitVideoJob      │
└─────────────────┘                            │   TranscribeSegmentJ │
        ▲                                      │   GenerateVoiceJob   │
        │                                      │   RenderSegmentJob   │
        │                                      └─────────┬────────────┘
        │                                                │
        │                                                ▼
        │                                      ┌──────────────────────┐
        │                                      │   PythonRunner       │
        │                                      │   (Process facade)   │
        │                                      └─────────┬────────────┘
        │                                                │ stdin/stdout JSON
        │                                                ▼
        │                                      ┌──────────────────────┐
        │                                      │   python/*.py        │
        │                                      │   yt-dlp · whisper   │
        │                                      │   gpt · edge-tts · ff│
        └─── DB rows (project, segment, ─────  └──────────────────────┘
              subtitle) updated by jobs
```

**Why this split?** Laravel handles the UI, ordering and DB state. Python handles the AI/ffmpeg work where every library you actually want (`openai`, `edge-tts`, `yt-dlp`) is best-in-class. They talk via stdin/stdout JSON — see `python/_common.py` and `app/Services/PythonRunner.php`.

---

## Requirements

- PHP 8.2+, Composer
- Node.js 18+ (Vite + Tailwind)
- Python 3.10+
- **System binaries**: `ffmpeg`, `ffprobe`, `yt-dlp` on `$PATH`
- SQLite (default) or MySQL

## Quick start

```bash
# 1. PHP deps + env
composer install
cp .env.example .env
php artisan key:generate

# 2. Edit .env — at minimum set OPENAI_API_KEY
#    EDGE_TTS_VOICE has a sensible default (my-MM-NilarNeural)

# 3. DB
touch database/database.sqlite
php artisan migrate

# 4. Python deps
python3 -m venv .venv && source .venv/bin/activate
pip install -r python/requirements.txt

# 5. Front-end
npm install && npm run dev      # in one terminal
php artisan serve               # in another
php artisan queue:work          # in a third — the pipeline lives on the queue
```

Open <http://localhost:8000>, paste a YouTube/TikTok/Facebook URL, and walk the 6 steps.

---

## Frame-size presets (Step 5 — Render)

The render step exposes a picker for the output frame. The defaults aim at vertical reels:

| Preset                | Size        | Aspect | Platform               |
|-----------------------|-------------|--------|------------------------|
| `reel_9x16`           | 1080×1920   | 9:16   | Universal reel         |
| `tiktok_9x16`         | 1080×1920   | 9:16   | TikTok                 |
| `facebook_reels_9x16` | 1080×1920   | 9:16   | Facebook Reels         |
| `youtube_shorts_9x16` | 1080×1920   | 9:16   | YouTube Shorts         |
| `youtube_16x9`        | 1920×1080   | 16:9   | YouTube horizontal     |

Add/edit presets in `config/pipeline.php` — `app/Models/Project::framePreset()` resolves whatever the user picked and `python/render_video.py` reads it off the JSON payload.

The pipeline scales+crops to cover the target so vertical sources stay centered on a horizontal canvas (and vice-versa).

---

## Configuration (.env)

| Key                   | Default                    | What it does                                              |
|-----------------------|----------------------------|-----------------------------------------------------------|
| `OPENAI_API_KEY`      | _(required)_               | Used by Whisper transcription + GPT translation           |
| `OPENAI_WHISPER_MODEL`| `whisper-1`                | Whisper model id                                          |
| `OPENAI_CHAT_MODEL`   | `gpt-4o-mini`              | Translation model                                         |
| `EDGE_TTS_VOICE`      | `my-MM-NilarNeural`        | Burmese voice (also: `my-MM-ThihaNeural` male)            |
| `PYTHON_BIN`          | `python3`                  | Python interpreter for `PythonRunner`                     |
| `YT_DLP_BIN`          | `yt-dlp`                   | Binary used by `fetch_source.py`                          |
| `FFMPEG_BIN`          | `ffmpeg`                   | Used by split + voice + render scripts                    |
| `FFPROBE_BIN`         | `ffprobe`                  | Used by `split_video.py` to measure source duration       |
| `DEFAULT_FRAME_PRESET`| `reel_9x16`                | Frame preset new projects start with                      |

---

## Project layout

```
app/
  Enums/             PipelineStep · StepStatus · AudioMixMode
  Models/            Project · Segment · Subtitle  (+ User)
  Livewire/
    StepBar.php      ← the persistent 6-step bar (rendered on every page)
    Pipeline/
      Source.php Split.php Translate.php
      EditSrt.php Voice.php Render.php
  Jobs/              one job per step (per-segment jobs use Bus::batch)
  Services/          PythonRunner · PipelineService · PipelinePaths · SrtWriter
  Http/Controllers/  DownloadController (segment mp4 + project zip)

python/              fetch_source · split_video · transcribe
                     translate · generate_voice · render_video

config/pipeline.php  ← frame presets, binaries, AI provider config
```

---

## Conventions

- **Laravel basics** (laravel-basic-skill): FormRequest-style validation lives in Livewire components via `#[Validate]`, controllers stay thin, enums replace status strings, `preventLazyLoading()` is on in non-prod.
- **Livewire-first** (livewire-basic-skill): every step is a full-page Livewire component, state is `#[Url]`'d so refreshes don't lose the project, lists auto-poll via `wire:poll`, no controller-rendered HTML for interactive pages.
- **Single source of truth for tokens**: design CSS tokens (`--blue`, `--green`, hairline borders, IBM Plex / Noto Sans Myanmar) live in `resources/css/app.css` and Tailwind theme — no inline color literals.

---

## License

MIT
