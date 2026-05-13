<?php

namespace App\Livewire\Pipeline;

use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Models\Project;
use App\Models\Segment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Render extends Component
{
    #[Url(as: 'p')]
    public ?int $projectId = null;

    public ?int $openPlayerSegmentId = null;

    public ?Project $project = null;

    /**
     * Frame-size preset key chosen on this page (the design's only render-
     * step control). Persisted to the project row whenever it changes.
     */
    public string $framePreset = 'reel_9x16';

    public function mount(): void
    {
        $this->project = Project::with('segments')->findOrFail($this->projectId);
        $this->framePreset = $this->project->frame_preset;
        $this->openPlayerSegmentId = $this->project->segments
            ->first(fn ($s) => $s->render_status === StepStatus::Done)?->id;
    }

    public function updatedFramePreset(string $value): void
    {
        if (! array_key_exists($value, config('pipeline.frame_presets'))) {
            return;
        }
        $this->project->update(['frame_preset' => $value]);
    }

    public function togglePlayer(int $segmentId): void
    {
        $this->openPlayerSegmentId = $this->openPlayerSegmentId === $segmentId ? null : $segmentId;
    }

    public function back(): void
    {
        $this->redirectRoute('pipeline.voice', ['p' => $this->project->id], navigate: true);
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

        $total = $this->project->segments->count();
        $done  = $this->project->segments
            ->filter(fn ($s) => $s->render_status === StepStatus::Done)
            ->count();
        $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        return view('livewire.pipeline.render', [
            'step'      => PipelineStep::Render,
            'project'   => $this->project,
            'doneCount' => $done,
            'progress'  => $progress,
            'presets'   => config('pipeline.frame_presets'),
        ]);
    }
}
