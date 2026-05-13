<?php

/*
 |--------------------------------------------------------------------------
 | Pipeline configuration
 |--------------------------------------------------------------------------
 |
 | Central config for the Burmese movie recap pipeline. Anything that the
 | UI or the Python scripts need to agree on lives here so the two sides
 | stay in sync. Read by both Laravel (config()) and the Python side
 | (passed as CLI arguments by App\Services\PythonRunner).
 |
 */

return [

    // -----------------------------------------------------------------
    // Binaries & paths
    // -----------------------------------------------------------------
    'python_bin'         => env('PYTHON_BIN', 'python3'),
    'python_scripts_path' => base_path(env('PYTHON_SCRIPTS_PATH', 'python')),
    'yt_dlp_bin'         => env('YT_DLP_BIN', 'yt-dlp'),
    'ffmpeg_bin'         => env('FFMPEG_BIN', 'ffmpeg'),
    'ffprobe_bin'        => env('FFPROBE_BIN', 'ffprobe'),

    // -----------------------------------------------------------------
    // Storage
    // -----------------------------------------------------------------
    'storage_disk'       => env('PIPELINE_STORAGE_DISK', 'local'),
    'storage_root'       => env('PIPELINE_STORAGE_ROOT', 'pipeline'),

    // -----------------------------------------------------------------
    // OpenAI (Whisper for transcription, GPT for translation)
    // -----------------------------------------------------------------
    'openai' => [
        'api_key'        => env('OPENAI_API_KEY'),
        'whisper_model'  => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
        'chat_model'     => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
    ],

    // -----------------------------------------------------------------
    // Microsoft Edge TTS (no API key — uses unauthenticated endpoint)
    // -----------------------------------------------------------------
    'tts' => [
        'voice'          => env('EDGE_TTS_VOICE', 'my-MM-NilarNeural'),
        // Available Burmese voices:
        //   my-MM-NilarNeural (female)
        //   my-MM-ThihaNeural  (male)
    ],

    // -----------------------------------------------------------------
    // Segment duration on the split step (seconds). UI exposes this
    // as a 1–5 minute slider; default matches the design (2:00).
    // -----------------------------------------------------------------
    'segment_seconds_default' => 120,
    'segment_seconds_min'     => 60,
    'segment_seconds_max'     => 300,

    // -----------------------------------------------------------------
    // Frame-size presets exposed in the render step
    // -----------------------------------------------------------------
    //  All three target platforms (Facebook Reels, TikTok, YouTube Shorts)
    //  use the same 9:16 vertical 1080×1920 canvas. Each preset carries:
    //   - id        ─ stable key stored on the project row
    //   - label     ─ human-readable button text in the UI
    //   - platform  ─ short tag for the pill ("Facebook" etc.)
    //   - width/height
    //   - aspect    ─ pretty string for the UI ("9:16")
    //   - fps       ─ recommended frame rate
    //   - bitrate   ─ recommended target bitrate for the encoder
    //
    'default_frame_preset' => env('DEFAULT_FRAME_PRESET', 'reel_9x16'),

    'frame_presets' => [
        'reel_9x16' => [
            'id'        => 'reel_9x16',
            'label'     => 'Reel 9:16 (universal)',
            'platform'  => 'All reels',
            'width'     => 1080,
            'height'    => 1920,
            'aspect'    => '9:16',
            'fps'       => 30,
            'bitrate'   => '6M',
            'description' => 'Universal vertical reel — works on Facebook Reels, TikTok, and YouTube Shorts.',
        ],
        'tiktok_9x16' => [
            'id'        => 'tiktok_9x16',
            'label'     => 'TikTok',
            'platform'  => 'TikTok',
            'width'     => 1080,
            'height'    => 1920,
            'aspect'    => '9:16',
            'fps'       => 30,
            'bitrate'   => '6M',
            'description' => 'TikTok vertical feed (9:16). 60 second sweet spot.',
        ],
        'facebook_reels_9x16' => [
            'id'        => 'facebook_reels_9x16',
            'label'     => 'Facebook Reels',
            'platform'  => 'Facebook',
            'width'     => 1080,
            'height'    => 1920,
            'aspect'    => '9:16',
            'fps'       => 30,
            'bitrate'   => '6M',
            'description' => 'Facebook Reels vertical (9:16). Safe-zone-aware.',
        ],
        'youtube_shorts_9x16' => [
            'id'        => 'youtube_shorts_9x16',
            'label'     => 'YouTube Shorts',
            'platform'  => 'YouTube',
            'width'     => 1080,
            'height'    => 1920,
            'aspect'    => '9:16',
            'fps'       => 30,
            'bitrate'   => '8M',
            'description' => 'YouTube Shorts vertical (9:16). Slightly higher bitrate.',
        ],
        // Bonus: landscape, in case the user changes their mind.
        'youtube_16x9' => [
            'id'        => 'youtube_16x9',
            'label'     => 'YouTube Landscape',
            'platform'  => 'YouTube',
            'width'     => 1920,
            'height'    => 1080,
            'aspect'    => '16:9',
            'fps'       => 30,
            'bitrate'   => '8M',
            'description' => 'YouTube horizontal long-form (16:9).',
        ],
    ],

    // -----------------------------------------------------------------
    // Audio mixing default — matches the design ("low-volume duck")
    // -----------------------------------------------------------------
    'audio_mix_default' => 'duck',  // 'duck' or 'replace'
    'audio_duck_db'     => -18,     // dB the source is ducked to when narrator speaks

];
