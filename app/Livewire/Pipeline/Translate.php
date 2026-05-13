<?php

namespace App\Livewire\Pipeline;

use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Translate extends Component
{
    #[Url(as: 'p')]
    public ?int $projectId = null;

    public ?Project $project = null;

    public function mount(): void
    {
        $this->project = Project::with('segments')->findOrFail($this->projectId);
    }

    public function continueToEditSrt(): void
    {
        if ($this->project->translate_status !== StepStatus::Done) {
            return;
        }
        $this->redirectRoute('pipeline.edit_srt', ['p' => $this->project->id], navigate: true);
    }

    public function back(): void
    {
        $this->redirectRoute('pipeline.split', ['p' => $this->project->id], navigate: true);
    }

    public function render()
    {
        $this->project->load('segments');

        $total    = $this->project->segments->count();
        $done     = $this->project->segments
            ->filter(fn ($s) => $s->translate_status === StepStatus::Done)
            ->count();
        $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        return view('livewire.pipeline.translate', [
            'step'     => PipelineStep::Translate,
            'project'  => $this->project,
            'doneCount'=> $done,
            'progress' => $progress,
        ]);
    }
}
