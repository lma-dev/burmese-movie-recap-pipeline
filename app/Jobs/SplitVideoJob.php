<?php

namespace App\Jobs;

use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Models\Project;
use App\Models\Segment;
use App\Services\PipelinePaths;
use App\Services\PythonRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SplitVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 1;

    public function __construct(public readonly int $projectId) {}

    public function handle(PythonRunner $py, PipelinePaths $paths): void
    {
        $project = Project::with('segments')->findOrFail($this->projectId);

        $project->update([
            'split_status' => StepStatus::Running,
            'current_step' => PipelineStep::Split,
            'last_error'   => null,
        ]);

        try {
            $result = $py->run('split_video.py', [
                'source_path'     => $project->source_local_path,
                'output_dir'      => $paths->segmentsDir($project),
                'segment_seconds' => $project->segment_seconds,
                'ffmpeg_bin'      => config('pipeline.ffmpeg_bin'),
                'ffprobe_bin'     => config('pipeline.ffprobe_bin'),
            ]);

            // Wipe old segments (segments rows cascade-delete subtitles).
            $project->segments()->delete();

            foreach ($result['segments'] as $seg) {
                Segment::create([
                    'project_id'  => $project->id,
                    'part_number' => $seg['part_number'],
                    'start_sec'   => $seg['start_sec'],
                    'end_sec'     => $seg['end_sec'],
                    'video_path'  => $seg['path'],
                ]);
            }

            $project->update([
                'segment_count' => count($result['segments']),
                'split_status'  => StepStatus::Done,
                'current_step'  => PipelineStep::Translate,
            ]);

            // Translate step auto-runs per the design.
            TranslateProjectJob::dispatch($project->id);
        } catch (Throwable $e) {
            $project->update([
                'split_status' => StepStatus::Failed,
                'last_error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
