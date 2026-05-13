<?php

namespace App\Services;

use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Jobs\FetchSourceJob;
use App\Jobs\GenerateVoiceJob;
use App\Jobs\RenderSegmentJob;
use App\Jobs\SplitVideoJob;
use App\Models\Project;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * High-level pipeline orchestration. Livewire components call into this
 * service; the service dispatches jobs and updates the project row.
 *
 * Each method maps 1:1 to a user action in the design:
 *   - startSource()    → "Fetch" button on Step 0
 *   - startSplit()     → "Start split" on Step 1
 *   - markEditSrtDone()→ "Next" on Step 3 (then auto-runs Voice)
 *   - startVoice()     → auto-runs after Edit SRT
 *   - startRender()    → auto-runs after Voice
 */
class PipelineService
{
    public function startSource(Project $project): void
    {
        $project->update([
            'source_status' => StepStatus::Pending,
            'current_step'  => PipelineStep::Source,
        ]);
        FetchSourceJob::dispatch($project->id);
    }

    public function startSplit(Project $project, int $segmentSeconds): void
    {
        $project->update([
            'segment_seconds' => $segmentSeconds,
            'split_status'    => StepStatus::Pending,
            'current_step'    => PipelineStep::Split,
        ]);
        SplitVideoJob::dispatch($project->id);
    }

    /**
     * User finished editing subtitles in the Edit-SRT step.
     * The next two steps (Voice → Render) auto-run as a batch chain.
     */
    public function markEditSrtDone(Project $project): void
    {
        $project->update([
            'edit_srt_status' => StepStatus::Done,
            'current_step'    => PipelineStep::Voice,
        ]);
        $this->startVoice($project);
    }

    public function startVoice(Project $project): void
    {
        $project->update([
            'voice_status' => StepStatus::Running,
            'current_step' => PipelineStep::Voice,
        ]);

        $jobs = $project->segments()->get()->map(fn ($s) => new GenerateVoiceJob($s->id))->all();
        if (empty($jobs)) {
            $project->update(['voice_status' => StepStatus::Done]);
            $this->startRender($project);
            return;
        }

        Bus::batch($jobs)
            ->name("voice-project-{$project->id}")
            ->then(function (Batch $batch) use ($project) {
                Project::whereKey($project->id)->update([
                    'voice_status' => StepStatus::Done->value,
                    'current_step' => PipelineStep::Render->value,
                ]);
                app(self::class)->startRender(Project::findOrFail($project->id));
            })
            ->catch(function (Batch $batch, Throwable $e) use ($project) {
                Project::whereKey($project->id)->update([
                    'voice_status' => StepStatus::Failed->value,
                    'last_error'   => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }

    public function startRender(Project $project): void
    {
        $project->update([
            'render_status' => StepStatus::Running,
            'current_step'  => PipelineStep::Render,
        ]);

        $jobs = $project->segments()->get()->map(fn ($s) => new RenderSegmentJob($s->id))->all();
        if (empty($jobs)) {
            $project->update(['render_status' => StepStatus::Done]);
            return;
        }

        Bus::batch($jobs)
            ->name("render-project-{$project->id}")
            ->then(function (Batch $batch) use ($project) {
                Project::whereKey($project->id)->update([
                    'render_status' => StepStatus::Done->value,
                ]);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($project) {
                Project::whereKey($project->id)->update([
                    'render_status' => StepStatus::Failed->value,
                    'last_error'    => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }
}
