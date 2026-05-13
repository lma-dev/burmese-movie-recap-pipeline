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
 * Flow (5 steps):
 *   Source     → startSource()        ("Fetch")
 *   Split      → startSplit()         (auto-runs on entry; collects all settings)
 *   Translate  → auto-runs after split
 *   EditSrt    → markEditSrtDone()    ("Next" — transitions to Finalize)
 *   Finalize   → startFinalize()      (auto-runs voice → render chain)
 *
 * Voice and Render still have their own status columns + jobs internally,
 * but the user-facing step bar treats them as one combined "Finalize" step.
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
     * The Finalize step (voice → render) auto-runs from there.
     */
    public function markEditSrtDone(Project $project): void
    {
        $project->update([
            'edit_srt_status' => StepStatus::Done,
            'current_step'    => PipelineStep::Finalize,
            'voice_status'    => StepStatus::Pending,
            'render_status'   => StepStatus::Pending,
        ]);
    }

    /**
     * Kick off voice generation; on success, automatically chains into
     * render. This is the single entry point used by the Finalize page.
     */
    public function startFinalize(Project $project): void
    {
        $this->startVoice($project);
    }

    public function startVoice(Project $project): void
    {
        $project->update([
            'voice_status' => StepStatus::Running,
            'current_step' => PipelineStep::Finalize,
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
            'current_step'  => PipelineStep::Finalize,
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
