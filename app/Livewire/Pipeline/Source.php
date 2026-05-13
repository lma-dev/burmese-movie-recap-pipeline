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
class Source extends Component
{
    #[Url(as: 'p')]
    public ?int $projectId = null;

    #[Validate('required|url|max:2048')]
    public string $sourceUrl = '';

    public ?Project $project = null;

    /** Last status we showed in the UI — used to detect a Running→Done transition. */
    public ?string $previousSourceStatus = null;

    public function mount(): void
    {
        if ($this->projectId) {
            $this->project = Project::find($this->projectId);
            if ($this->project) {
                $this->sourceUrl = $this->project->source_url;
                $this->previousSourceStatus = $this->project->source_status->value;
            }
        }
    }

    public function fetch(PipelineService $pipeline): void
    {
        $this->validate();

        $this->project = Project::create([
            'source_url'    => $this->sourceUrl,
            'current_step'  => PipelineStep::Source,
            'source_status' => StepStatus::Pending,
        ]);

        $this->projectId = $this->project->id;
        $this->previousSourceStatus = $this->project->source_status->value;
        $pipeline->startSource($this->project);
    }

    /**
     * Polled by `wire:poll` while the fetch job is in flight. Refreshes the
     * project row and, on the first transition into Done, fires a browser
     * event so the page can hard-reload with the final metadata in place.
     */
    public function pollStatus(): void
    {
        if (! $this->project) {
            return;
        }

        $this->project->refresh();
        $current = $this->project->source_status->value;

        if ($this->previousSourceStatus !== StepStatus::Done->value
            && $current === StepStatus::Done->value) {
            $this->dispatch('source-completed');
        }

        $this->previousSourceStatus = $current;
    }

    public function continueToSplit(): void
    {
        if (! $this->project || $this->project->source_status !== StepStatus::Done) {
            return;
        }

        $this->project->update(['current_step' => PipelineStep::Split]);
        $this->redirectRoute('pipeline.split', ['p' => $this->project->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.pipeline.source', [
            'project' => $this->project,
            'step'    => PipelineStep::Source,
        ]);
    }
}
