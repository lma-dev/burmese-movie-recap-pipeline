<?php

namespace App\Jobs;

use App\Enums\StepStatus;
use App\Models\Segment;
use App\Services\PipelinePaths;
use App\Services\PythonRunner;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RenderSegmentJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries   = 1;

    public function __construct(public readonly int $segmentId) {}

    public function handle(PythonRunner $py, PipelinePaths $paths): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $segment = Segment::with('project')->findOrFail($this->segmentId);
        $project = $segment->project;

        $segment->update([
            'render_status'   => StepStatus::Running,
            'render_progress' => 0,
        ]);

        try {
            $renderedPath = $paths->renderedPath($segment);

            $result = $py->run('render_video.py', [
                'segment_path'    => $segment->video_path,
                'srt_path'        => $segment->srt_path,
                'voice_path'      => $segment->voice_path,
                'output_path'     => $renderedPath,
                'preset'          => $project->framePreset(),
                'audio_mix_mode'  => $project->audio_mix_mode->value,
                'duck_db'         => (int) config('pipeline.audio_duck_db'),
                'ffmpeg_bin'      => config('pipeline.ffmpeg_bin'),
            ]);

            $segment->update([
                'rendered_path'   => $result['rendered_path'] ?? $renderedPath,
                'render_status'   => StepStatus::Done,
                'render_progress' => 100,
            ]);
        } catch (Throwable $e) {
            $segment->update([
                'render_status' => StepStatus::Failed,
                'last_error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
