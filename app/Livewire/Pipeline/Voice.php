<?php

namespace App\Livewire\Pipeline;

use App\Enums\AudioMixMode;
use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Voice extends Component
{
    #[Url(as: 'p')]
    public ?int $projectId = null;

    public AudioMixMode $mixMode = AudioMixMode::Duck;

    public ?Project $project = null;

    public function mount(): void
    {
        $this->project = Project::with('segments')->findOrFail($this->projectId);
        $this->mixMode = $this->project->audio_mix_mode;
    }

    public function updatedMixMode(): void
    {
        $this->project->update(['audio_mix_mode' => $this->mixMode]);
    }

    public function continueToRender(): void
    {
        if ($this->project->voice_status !== StepStatus::Done) {
            return;
        }
        $this->redirectRoute('pipeline.render', ['p' => $this->project->id], navigate: true);
    }

    public function back(): void
    {
        $this->redirectRoute('pipeline.edit_srt', ['p' => $this->project->id], navigate: true);
    }

    public function render()
    {
        $this->project->load('segments');

        $total = $this->project->segments->count();
        $done  = $this->project->segments
            ->filter(fn ($s) => $s->voice_status === StepStatus::Done)
            ->count();
        $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        return view('livewire.pipeline.voice', [
            'step'      => PipelineStep::Voice,
            'project'   => $this->project,
            'doneCount' => $done,
            'progress'  => $progress,
        ]);
    }
}
