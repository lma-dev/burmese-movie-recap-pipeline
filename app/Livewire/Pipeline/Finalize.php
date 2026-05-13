<?php

namespace App\Livewire\Pipeline;

use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Models\Project;
use App\Services\PipelineService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Finalize is the merged Voice + Render step. The user no longer changes
 * any settings here — everything was committed back on the Split page.
 * On entry, if voice hasn't run yet, we auto-start the voice → render
 * chain. The page just visualises both progress lanes and lets the user
 * play / download finished segments once render is complete.
 */
#[Layout('components.layouts.app')]
class Finalize extends Component
{
    #[Url(as: 'p')]
    public ?int $projectId = null;

    public ?Project $project = null;

    public ?int $openPlayerSegmentId = null;

    public function mount(): void
    {
        $this->project = Project::with('segments')->findOrFail($this->projectId);

        $this->openPlayerSegmentId = $this->project->segments
            ->first(fn ($s) => $s->render_status === StepStatus::Done)?->id;
    }

    public function retry(PipelineService $pipeline): void
    {
        $this->project->refresh();
        if (! in_array($this->project->finalizeStatus(), [StepStatus::Failed], true)) {
            return;
        }

        $this->project->update([
            'voice_status'  => StepStatus::Pending,
            'render_status' => StepStatus::Pending,
            'last_error'    => null,
        ]);
        $pipeline->startFinalize($this->project->refresh());
    }

    public function togglePlayer(int $segmentId): void
    {
        $this->openPlayerSegmentId = $this->openPlayerSegmentId === $segmentId ? null : $segmentId;
    }

    public function back(): void
    {
        $this->redirectRoute('pipeline.edit_srt', ['p' => $this->project->id], navigate: true);
    }

    public function downloadZip(): void
    {
        $this->redirect(route('projects.download_zip', $this->project), navigate: false);
    }

    public function downloadSegment(int $segmentId): void
    {
        $this->redirect(route('segments.download', $segmentId), navigate: false);
    }

    public function render()
    {
        $this->project->load('segments');

        $segments = $this->project->segments;
        $total    = $segments->count();

        $voiceDone  = $segments->filter(fn ($s) => $s->voice_status === StepStatus::Done)->count();
        $renderDone = $segments->filter(fn ($s) => $s->render_status === StepStatus::Done)->count();

        return view('livewire.pipeline.finalize', [
            'step'        => PipelineStep::Finalize,
            'project'     => $this->project,
            'voiceDone'   => $voiceDone,
            'renderDone'  => $renderDone,
            'total'       => $total,
            'voicePct'    => $total ? (int) round($voiceDone * 100 / $total) : 0,
            'renderPct'   => $total ? (int) round($renderDone * 100 / $total) : 0,
            'preset'      => config('pipeline.frame_presets')[$this->project->frame_preset]
                             ?? config('pipeline.frame_presets')[config('pipeline.default_frame_preset')],
        ]);
    }
}
