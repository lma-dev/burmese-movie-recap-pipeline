# Burmese Movie Recap Pipeline — Project Flow

End-to-end walkthrough of how a pasted video URL becomes a Burmese-narrated, subtitled 9:16 reel.

This document is the *runtime story* of the app: who calls what, in what order, what state changes on the database, and where Laravel hands off to Python.

---

## 1. High-level shape

The app is a **Laravel + Livewire** front-end driving a **queue-backed pipeline** that shells out to **Python** for the AI / `ffmpeg` work. There are exactly six ordered steps; each step is a full-page Livewire component, and the persistent step-bar shows where the user is.

```
 Browser  ──HTTP──►  Livewire page  ──dispatch──►  Job (queue)
   ▲                                                  │
   │ wire:poll (2–3s)                                  ▼
   │                                          PythonRunner (Process)
   │                                                  │
   │                                                  ▼
   └──────── reads DB rows ◄────── Jobs update DB ── python/*.py
                                                       │
                                                       ▼
                                            yt-dlp · whisper · gpt
                                            edge-tts · ffmpeg
```

Why the split?
- **Laravel** owns UI state, ordering, validation, DB writes.
- **Python** owns the AI + media work where the best libraries live (`openai`, `edge-tts`, `yt-dlp`).
- They talk via **stdin/stdout JSON** — see [app/Services/PythonRunner.php](../app/Services/PythonRunner.php) and [python/_common.py](../python/_common.py).

---

## 2. The six steps

| #  | Step       | Route                  | Livewire component                                       | Auto-runs? |
|----|------------|------------------------|----------------------------------------------------------|------------|
| 0  | Source     | `/pipeline/source`     | [Source.php](../app/Livewire/Pipeline/Source.php)        | no         |
| 1  | Split      | `/pipeline/split`      | [Split.php](../app/Livewire/Pipeline/Split.php)          | no         |
| 2  | Translate  | `/pipeline/translate`  | [Translate.php](../app/Livewire/Pipeline/Translate.php)  | **yes**    |
| 3  | Edit SRT   | `/pipeline/edit-srt`   | [EditSrt.php](../app/Livewire/Pipeline/EditSrt.php)      | no         |
| 4  | Voice      | `/pipeline/voice`      | [Voice.php](../app/Livewire/Pipeline/Voice.php)          | **yes**    |
| 5  | Render     | `/pipeline/render`     | [Render.php](../app/Livewire/Pipeline/Render.php)        | **yes**    |

Routes live in [routes/web.php](../routes/web.php). `/` redirects to Step 0.

Step ordering and labels come from the `PipelineStep` enum: [app/Enums/PipelineStep.php](../app/Enums/PipelineStep.php). Per-step state comes from `StepStatus` (`pending` → `running` → `done` / `failed`): [app/Enums/StepStatus.php](../app/Enums/StepStatus.php).

---

## 3. Data model

Three tables drive the pipeline. All status columns are cast to enums on the Eloquent models.

### projects

One row per recap. Tracks the original URL + the status column for **each** of the 6 steps.

Key columns (see [app/Models/Project.php](../app/Models/Project.php)):
- `source_url`, `source_title`, `source_duration_sec`, `source_thumbnail_url`, `source_local_path`
- `current_step` (`PipelineStep` enum)
- `source_status`, `split_status`, `translate_status`, `edit_srt_status`, `voice_status`, `render_status` (each a `StepStatus`)
- `segment_seconds`, `segment_count`
- `frame_preset`, `audio_mix_mode`
- `last_error`

### segments

One row per slice produced by Step 1. Holds per-step sub-status so the Translate / Voice / Render tables can show per-row progress.

Columns (see [app/Models/Segment.php](../app/Models/Segment.php)):
- `part_number`, `start_sec`, `end_sec`, `video_path`
- `transcribe_status`, `translate_status`, `voice_status`, `render_status`
- `voice_progress`, `render_progress`
- `voice_path`, `rendered_path`

### subtitles

One row per Burmese line written by Step 2 and editable in Step 3.

Columns (see [app/Models/Subtitle.php](../app/Models/Subtitle.php)):
- `segment_id`, `line_number`, `start_ms`, `end_ms`, `text`

---

## 4. Orchestration layer

User actions in Livewire don't talk to jobs directly — they go through [app/Services/PipelineService.php](../app/Services/PipelineService.php), which maps one method to one button:

| Method                 | Trigger                       | Effect                                                  |
|------------------------|-------------------------------|---------------------------------------------------------|
| `startSource()`        | "Fetch" on Step 0             | Dispatches `FetchSourceJob`                             |
| `startSplit()`         | "Start split" on Step 1       | Dispatches `SplitVideoJob`                              |
| `markEditSrtDone()`    | "Next" on Step 3              | Marks Edit-SRT done, then calls `startVoice()`          |
| `startVoice()`         | auto after Edit SRT           | `Bus::batch` of `GenerateVoiceJob` (one per segment)    |
| `startRender()`        | auto after Voice batch        | `Bus::batch` of `RenderSegmentJob` (one per segment)    |

The `Bus::batch(...)->then(...)` chain is how Voice → Render auto-flows: when the voice batch finishes, the `then` callback updates the project row and calls `startRender()` for the next batch.

---

## 5. Step-by-step walkthrough

### Step 0 — Source

**User action:** paste a YouTube / TikTok / Facebook URL, click "Fetch".

**Flow:**
1. `Source` Livewire component validates the URL and creates the `projects` row.
2. `PipelineService::startSource()` dispatches `FetchSourceJob`.
3. `FetchSourceJob` ([app/Jobs/FetchSourceJob.php](../app/Jobs/FetchSourceJob.php)) sets `source_status = running`, then shells out to `python/fetch_source.py` via `PythonRunner`.
4. `fetch_source.py` runs `yt-dlp` to download the video and pull metadata (title, duration, thumbnail), and returns a JSON blob.
5. Job writes `source_title`, `source_duration_sec`, `source_thumbnail_url`, `source_local_path` and flips status to `done`.

UI polls the project row via `wire:poll` and unlocks "Next → Split" once status is `done`.

### Step 1 — Split

**User action:** choose segment length (1–5 min), click "Start split".

**Flow:**
1. `PipelineService::startSplit()` writes `segment_seconds` and dispatches `SplitVideoJob`.
2. `SplitVideoJob` ([app/Jobs/SplitVideoJob.php](../app/Jobs/SplitVideoJob.php)) calls `python/split_video.py` (`ffprobe` measures duration, `ffmpeg -segment_time` slices).
3. Returned segment metadata is written as `segments` rows (old segments are wiped first so a re-split is safe).
4. Job flips `split_status` to `done`, sets `current_step = Translate`, and **auto-dispatches** `TranslateProjectJob` — the user does not press a button to start transcription.

### Step 2 — Translate (auto)

This is a fan-out batch.

1. `TranslateProjectJob` ([app/Jobs/TranslateProjectJob.php](../app/Jobs/TranslateProjectJob.php)) creates a `Bus::batch` of one `TranscribeSegmentJob` per segment.
2. Each `TranscribeSegmentJob`:
   - Runs `python/transcribe.py` (Whisper) on the segment's audio → English text + timings.
   - Runs `python/translate.py` (GPT-4o-mini) → Burmese text, line-by-line, preserving timings.
   - Writes `subtitles` rows for that segment, flips the segment's `transcribe_status` / `translate_status` to `done`.
3. The batch's `then` callback flips the project's `translate_status` to `done` and advances `current_step` to `EditSrt`.

The Translate page shows a per-segment table with status pills; `wire:poll` refreshes every 2–3 s.

### Step 3 — Edit SRT

**User action:** review and edit the generated Burmese lines, click "Next".

**Flow:**
1. `EditSrt` Livewire component loads all subtitles for the project.
2. Edits are persisted to the `subtitles` table as the user types (Livewire bindings).
3. "Next" calls `PipelineService::markEditSrtDone()`, which marks the step done and immediately kicks off Voice.

### Step 4 — Voice (auto)

1. `PipelineService::startVoice()` creates a `Bus::batch` of one `GenerateVoiceJob` per segment.
2. Each `GenerateVoiceJob` calls `python/generate_voice.py`, which feeds the Burmese SRT lines into Microsoft `edge-tts` (default voice `my-MM-NilarNeural`) and produces a narrator audio track aligned to the segment.
3. Output path is stored on `segment.voice_path`; segment `voice_status` flips to `done`.
4. The batch's `then` callback flips project `voice_status` to `done` and calls `startRender()`.

### Step 5 — Render (auto)

1. `PipelineService::startRender()` creates a `Bus::batch` of one `RenderSegmentJob` per segment.
2. Each `RenderSegmentJob` calls `python/render_video.py`, which uses `ffmpeg` to:
   - Burn the Burmese subtitles into the video.
   - Mix the narrator audio (per `audio_mix_mode`).
   - Scale/crop to the chosen frame preset (default `reel_9x16`, 1080×1920).
3. Output is written to `segment.rendered_path`; segment `render_status` flips to `done`.
4. The Render page shows an inline `<video>` preview per segment (streamed via the `segments.stream` route) and per-segment / whole-project download buttons.

---

## 6. Frame presets

Configured in `config/pipeline.php` and resolved by `Project::framePreset()`. Built-in presets target vertical reels:

| Preset                  | Size        | Aspect | Platform           |
|-------------------------|-------------|--------|--------------------|
| `reel_9x16`             | 1080×1920   | 9:16   | Universal reel     |
| `tiktok_9x16`           | 1080×1920   | 9:16   | TikTok             |
| `facebook_reels_9x16`   | 1080×1920   | 9:16   | Facebook Reels     |
| `youtube_shorts_9x16`   | 1080×1920   | 9:16   | YouTube Shorts     |
| `youtube_16x9`          | 1920×1080   | 16:9   | YouTube horizontal |

`render_video.py` reads the preset off the JSON payload from `RenderSegmentJob` and applies a scale + crop "cover" so vertical sources stay centered on a horizontal canvas (and vice-versa).

---

## 7. Downloads

After Step 5, two routes are available (see [routes/web.php](../routes/web.php)):

- `GET /segments/{segment}/download` → single rendered MP4
- `GET /projects/{project}/download.zip` → full project ZIP (all segments)
- `GET /segments/{segment}/stream` → inline streaming for the `<video>` preview on the Render page

All three are handled by `DownloadController`.

---

## 8. Failure model

- Each job catches `Throwable`, writes `last_error` and flips the step status to `failed`, then re-throws so the queue worker logs it.
- Batched per-segment jobs use the `Bus::batch(...)->catch(...)` hook to propagate failure up to the project's step status.
- `tries = 1` on every job — these are expensive, slow operations; we'd rather show "Failed" and let the user retry from the UI than silently double-bill the OpenAI account.
- `current_step` is *not* rewound on failure; the UI surfaces the error inline on whichever step failed.

---

## 9. Where to look next

- **Step-bar UI:** [app/Livewire/StepBar.php](../app/Livewire/StepBar.php) — renders the persistent top bar using `Project::stepBarClass()`.
- **Python ↔ Laravel bridge:** [app/Services/PythonRunner.php](../app/Services/PythonRunner.php) + [python/_common.py](../python/_common.py).
- **Path conventions:** [app/Services/PipelinePaths.php](../app/Services/PipelinePaths.php) — where source files, segments, voice tracks, and rendered MP4s live on disk.
- **SRT writing:** [app/Services/SrtWriter.php](../app/Services/SrtWriter.php) — produces the `.srt` that `render_video.py` burns into the final frame.
- **Config:** `config/pipeline.php` — frame presets, binary paths, AI provider config.
