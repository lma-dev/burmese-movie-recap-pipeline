<?php

namespace App\Livewire\Pipeline;

use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Models\Project;
use App\Services\PipelineService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Split extends Component
{
    #[Url(as: 'p')]
    public ?int $projectId = null;

    /** Slider in the UI runs from min..max in 30-second steps. */
    #[Validate('required|integer|min:60|max:300')]
    public int $segmentSeconds = 120;

    public ?Project $project = null;

    public function mount(): void
    {
        $this->project = Project::with('segments')->findOrFail($this->projectId);
        $this->segmentSeconds = $this->project->segment_seconds;
    }

    public function startSplit(PipelineService $pipeline): void
    {
        $this->validate();

        $this->project->update(['segment_seconds' => $this->segmentSeconds]);
        $pipeline->startSplit($this->project, $this->segmentSeconds);
        $this->project->refresh();
    }

    public function continueToTranslate(): void
    {
        if ($this->project->split_status !== StepStatus::Done) {
            return;
        }
        $this->redirectRoute('pipeline.translate', ['p' => $this->project->id], navigate: true);
    }

    public function back(): void
    {
        $this->redirectRoute('pipeline.source', ['p' => $this->project->id], navigate: true);
    }

    /** Live-refreshed value for the "X / Y complete" header in the segment grid. */
    public function getCompletedCountProperty(): int
    {
        return $this->project->segments->where('video_path', '!=', null)->count();
    }

    public function render()
    {
        // Pull latest segments to drive the segment grid card.
        $this->project->load('segments');

        return view('livewire.pipeline.split', [
            'step'    => PipelineStep::Split,
            'project' => $this->project,
        ]);
    }
}
