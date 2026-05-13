<?php

namespace App\Livewire;

use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Models\Project;
use Livewire\Component;

/**
 * The persistent app top bar + 6-step indicator.
 *
 * Receives the current PipelineStep + the Project from the parent
 * Livewire component. Re-renders whenever either changes.
 */
class StepBar extends Component
{
    public PipelineStep $current;
    public ?Project $project = null;

    public function mount(PipelineStep $current, ?Project $project = null): void
    {
        $this->current = $current;
        $this->project = $project;
    }

    /**
     * Decide the CSS class for each indicator row.
     *  - done   = greenish, has check
     *  - active = blue, underlined
     *  - ''     = neutral
     */
    public function classFor(PipelineStep $step): string
    {
        if (! $this->project) {
            return $step === $this->current ? 'active' : '';
        }
        return $this->project->stepBarClass($step);
    }

    public function stateLabel(PipelineStep $step): string
    {
        if (! $this->project) {
            return $step === $this->current ? 'In progress' : 'Pending';
        }
        $status = $this->project->statusFor($step);

        if ($step === $this->current && $status === StepStatus::Running) {
            return $this->runningLabel($step);
        }

        return $status->label();
    }

    private function runningLabel(PipelineStep $step): string
    {
        return match ($step) {
            PipelineStep::Source    => 'Fetching',
            PipelineStep::Split     => 'Splitting',
            PipelineStep::Translate => 'Auto-running',
            PipelineStep::EditSrt   => 'Reviewing',
            PipelineStep::Voice     => 'Generating',
            PipelineStep::Render    => 'Rendering',
        };
    }

    public function render()
    {
        return view('livewire.step-bar', [
            'steps' => PipelineStep::ordered(),
        ]);
    }
}
