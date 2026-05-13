<?php

namespace App\Jobs;

use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Models\Project;
use App\Services\PipelinePaths;
use App\Services\PythonRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FetchSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries   = 1;

    public function __construct(public readonly int $projectId) {}

    public function handle(PythonRunner $py, PipelinePaths $paths): void
    {
        $project = Project::findOrFail($this->projectId);

        $project->update([
            'source_status' => StepStatus::Running,
            'current_step'  => PipelineStep::Source,
            'last_error'    => null,
        ]);

        try {
            $result = $py->run('fetch_source.py', [
                'url'        => $project->source_url,
                'output_dir' => $paths->projectDir($project),
                'ytdlp_bin'  => config('pipeline.yt_dlp_bin'),
            ]);

            $project->update([
                'source_title'         => $result['title'] ?? null,
                'source_duration_sec'  => $result['duration_sec'] ?? null,
                'source_thumbnail_url' => $result['thumbnail_url'] ?? null,
                'source_local_path'    => $result['local_path'] ?? null,
                'source_status'        => StepStatus::Done,
            ]);
        } catch (Throwable $e) {
            $project->update([
                'source_status' => StepStatus::Failed,
                'last_error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
