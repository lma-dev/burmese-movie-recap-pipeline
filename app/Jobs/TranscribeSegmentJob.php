<?php

namespace App\Jobs;

use App\Enums\StepStatus;
use App\Models\Segment;
use App\Models\Subtitle;
use App\Services\PythonRunner;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Transcribe + translate one segment.
 * Two python scripts in sequence (whisper, then GPT) to keep payloads
 * small and let either step fail independently with a clear error.
 */
class TranscribeSegmentJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 1;

    public function __construct(public readonly int $segmentId) {}

    public function handle(PythonRunner $py): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $segment = Segment::with('project')->findOrFail($this->segmentId);

        $segment->update([
            'transcribe_status' => StepStatus::Running,
            'translate_status'  => StepStatus::Pending,
        ]);

        try {
            // --- 1) Whisper transcription ---
            $transcription = $py->run('transcribe.py', [
                'segment_path'   => $segment->video_path,
                'openai_api_key' => config('pipeline.openai.api_key'),
                'model'          => config('pipeline.openai.whisper_model'),
            ]);
            $lines = $transcription['lines'] ?? [];

            $segment->update(['transcribe_status' => StepStatus::Done]);

            if (empty($lines)) {
                $segment->update(['translate_status' => StepStatus::Done]);
                return;
            }

            // --- 2) GPT translation to Burmese ---
            $segment->update(['translate_status' => StepStatus::Running]);
            $translated = $py->run('translate.py', [
                'lines'          => $lines,
                'openai_api_key' => config('pipeline.openai.api_key'),
                'model'          => config('pipeline.openai.chat_model'),
            ]);

            $burmeseByLine = collect($translated['translations'] ?? [])
                ->keyBy('line_number');

            // --- 3) Persist subtitles ---
            $segment->subtitles()->delete();
            foreach ($lines as $line) {
                $n = $line['line_number'];
                Subtitle::create([
                    'segment_id'   => $segment->id,
                    'line_number'  => $n,
                    'start_ms'     => $line['start_ms'],
                    'end_ms'       => $line['end_ms'],
                    'source_text'  => $line['text'] ?? null,
                    'burmese_text' => $burmeseByLine->get($n)['burmese'] ?? '',
                ]);
            }

            $segment->update(['translate_status' => StepStatus::Done]);
        } catch (Throwable $e) {
            $segment->update([
                'transcribe_status' => StepStatus::Failed,
                'translate_status'  => StepStatus::Failed,
                'last_error'        => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
