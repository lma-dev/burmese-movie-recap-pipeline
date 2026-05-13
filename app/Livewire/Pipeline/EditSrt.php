<?php

namespace App\Livewire\Pipeline;

use App\Enums\PipelineStep;
use App\Models\Project;
use App\Models\Segment;
use App\Models\Subtitle;
use App\Services\PipelineService;
use App\Services\SrtWriter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
class EditSrt extends Component
{
    #[Url(as: 'p')]
    public ?int $projectId = null;

    #[Url(as: 's')]
    public ?int $segmentId = null;

    public ?int $selectedLineId = null;

    /** Local working copy of the editable lines for the current segment. */
    public array $lines = [];

    public ?Project $project = null;
    public ?Segment $currentSegment = null;

    public function mount(): void
    {
        $this->project = Project::with('segments')->findOrFail($this->projectId);

        $this->currentSegment = $this->segmentId
            ? $this->project->segments->firstWhere('id', $this->segmentId)
            : $this->project->segments->first();

        if ($this->currentSegment) {
            $this->segmentId = $this->currentSegment->id;
            $this->loadLines();
        }
    }

    public function updatedSegmentId(): void
    {
        $this->currentSegment = $this->project->segments->firstWhere('id', $this->segmentId);
        $this->loadLines();
        $this->selectedLineId = $this->lines[0]['id'] ?? null;
    }

    private function loadLines(): void
    {
        $this->lines = $this->currentSegment->subtitles()
            ->get(['id', 'line_number', 'start_ms', 'end_ms', 'source_text', 'burmese_text'])
            ->map(fn ($l) => [
                'id'           => $l->id,
                'line_number'  => $l->line_number,
                'start_ms'     => $l->start_ms,
                'end_ms'       => $l->end_ms,
                'source_text'  => $l->source_text,
                'burmese_text' => $l->burmese_text,
                'start_tc'     => $l->startTimecode(),
                'end_tc'       => $l->endTimecode(),
            ])
            ->all();
        $this->selectedLineId ??= $this->lines[0]['id'] ?? null;
    }

    public function selectLine(int $id): void
    {
        $this->selectedLineId = $id;
    }

    public function saveLine(int $id): void
    {
        $line = collect($this->lines)->firstWhere('id', $id);
        if (! $line) {
            return;
        }
        Subtitle::whereKey($id)->update(['burmese_text' => $line['burmese_text']]);
    }

    public function exportSrt(SrtWriter $writer): StreamedResponse
    {
        $path = $writer->writeForSegment($this->currentSegment);
        return response()->streamDownload(
            fn () => print file_get_contents($path),
            basename($path),
            ['Content-Type' => 'application/x-subrip'],
        );
    }

    public function next(PipelineService $pipeline, SrtWriter $writer): void
    {
        // Persist every line in case it wasn't auto-saved per-keystroke,
        // and write SRT files for the voice/render steps.
        foreach ($this->lines as $line) {
            Subtitle::whereKey($line['id'])->update(['burmese_text' => $line['burmese_text']]);
        }
        foreach ($this->project->segments as $seg) {
            $writer->writeForSegment($seg->loadMissing('subtitles'));
        }

        $pipeline->markEditSrtDone($this->project);
        $this->redirectRoute('pipeline.voice', ['p' => $this->project->id], navigate: true);
    }

    public function back(): void
    {
        $this->redirectRoute('pipeline.translate', ['p' => $this->project->id], navigate: true);
    }

    public function render()
    {
        $selectedLine = collect($this->lines)->firstWhere('id', $this->selectedLineId);

        return view('livewire.pipeline.edit_srt', [
            'step'         => PipelineStep::EditSrt,
            'project'      => $this->project,
            'segment'      => $this->currentSegment,
            'selectedLine' => $selectedLine,
        ]);
    }
}
