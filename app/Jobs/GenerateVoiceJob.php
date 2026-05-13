<?php

namespace App\Jobs;

use App\Enums\StepStatus;
use App\Models\Segment;
use App\Services\PipelinePaths;
use App\Services\PythonRunner;
use App\Services\SrtWriter;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateVoiceJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 1;

    public function __construct(public readonly int $segmentId) {}

    public function handle(PythonRunner $py, PipelinePaths $paths, SrtWriter $srt): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $segment = Segment::with(['project', 'subtitles'])->findOrFail($this->segmentId);

        $segment->update([
            'voice_status'   => StepStatus::Running,
            'voice_progress' => 0,
        ]);

        try {
            // Always (re)write the SRT before voice + render: the user may
            // have edited it on the Edit-SRT step.
            $srtPath = $srt->writeForSegment($segment);
            $segment->update(['srt_path' => $srtPath]);

            $lines = $segment->subtitles->map(fn ($l) => [
                'line_number' => $l->line_number,
                'start_ms'    => $l->start_ms,
                'end_ms'      => $l->end_ms,
                'burmese'     => $l->burmese_text,
            ])->all();

            $voicePath = $paths->voicePath($segment);

            $result = $py->run('generate_voice.py', [
                'lines'               => $lines,
                'voice'               => config('pipeline.tts.voice'),
                'segment_duration_ms' => ($segment->end_sec - $segment->start_sec) * 1000,
                'output_path'         => $voicePath,
                'ffmpeg_bin'          => config('pipeline.ffmpeg_bin'),
            ]);

            $segment->update([
                'voice_path'     => $result['voice_path'] ?? $voicePath,
                'voice_status'   => StepStatus::Done,
                'voice_progress' => 100,
            ]);
        } catch (Throwable $e) {
            $segment->update([
                'voice_status' => StepStatus::Failed,
                'last_error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
