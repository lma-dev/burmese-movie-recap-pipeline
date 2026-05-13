<?php

namespace App\Jobs;

use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Models\Project;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Fan out per-segment transcribe+translate jobs in a batch, then mark
 * the Translate step done when they all finish.
 */
class TranslateProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries   = 1;

    public function __construct(public readonly int $projectId) {}

    public function handle(): void
    {
        $project = Project::with('segments')->findOrFail($this->projectId);

        $project->update([
            'translate_status' => StepStatus::Running,
            'current_step'     => PipelineStep::Translate,
            'last_error'       => null,
        ]);

        $jobs = $project->segments->map(fn ($s) => new TranscribeSegmentJob($s->id))->all();

        if (empty($jobs)) {
            $project->update(['translate_status' => StepStatus::Done]);
            return;
        }

        Bus::batch($jobs)
            ->name("translate-project-{$project->id}")
            ->then(function (Batch $batch) use ($project) {
                Project::whereKey($project->id)->update([
                    'translate_status' => StepStatus::Done->value,
                    'current_step'     => PipelineStep::EditSrt->value,
                ]);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($project) {
                Project::whereKey($project->id)->update([
                    'translate_status' => StepStatus::Failed->value,
                    'last_error'       => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }
}
