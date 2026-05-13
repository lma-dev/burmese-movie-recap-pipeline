<?php

namespace App\Livewire\Pipeline;

use App\Enums\AudioMixMode;
use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use App\Enums\VoiceTone;
use App\Models\Project;
use App\Services\PipelineService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Split is the configuration hub: it owns every pipeline setting (segment
 * length, narration tone, audio-mix mode, output frame preset, …) plus
 * the actual segment-cutting job. Settings save live; the user confirms
 * them via a modal on the Continue button before moving forward.
 */
#[Layout('components.layouts.app')]
class Split extends Component
{
    #[Url(as: 'p')]
    public ?int $projectId = null;

    /** Segment length in seconds. Slider runs 60..300 in 30-second steps. */
    #[Validate('required|integer|min:60|max:300')]
    public int $segmentSeconds = 120;

    public VoiceTone $voiceTone = VoiceTone::Neutral;
    public AudioMixMode $mixMode = AudioMixMode::Duck;
    public string $framePreset = 'reel_9x16';

    public ?Project $project = null;

    public function mount(): void
    {
        $this->project = Project::with('segments')->findOrFail($this->projectId);

        $this->segmentSeconds = $this->project->segment_seconds;
        $this->voiceTone      = $this->project->voice_tone;
        $this->mixMode        = $this->project->audio_mix_mode;
        $this->framePreset    = $this->project->frame_preset;
    }

    public function updatedSegmentSeconds(): void
    {
        $this->validateOnly('segmentSeconds');

        $this->project->update(['segment_seconds' => $this->segmentSeconds]);
    }

    public function updatedVoiceTone(): void
    {
        $this->project->update(['voice_tone' => $this->voiceTone]);
    }

    public function updatedMixMode(): void
    {
        $this->project->update(['audio_mix_mode' => $this->mixMode]);
    }

    public function updatedFramePreset(string $value): void
    {
        if (! array_key_exists($value, config('pipeline.frame_presets'))) {
            return;
        }
        $this->project->update(['frame_preset' => $value]);
    }

    /**
     * Called from the confirm dialog. Persists the chosen settings,
     * dispatches the split job (which auto-chains into Translate), and
     * redirects to the Translate page where the user watches progress.
     */
    public function confirmAndContinue(PipelineService $pipeline): void
    {
        $this->validate();

        $this->project->update([
            'segment_seconds' => $this->segmentSeconds,
            'voice_tone'      => $this->voiceTone,
            'audio_mix_mode'  => $this->mixMode,
            'frame_preset'    => $this->framePreset,
        ]);

        $pipeline->startSplit($this->project->refresh(), $this->segmentSeconds);

        $this->redirectRoute('pipeline.translate', ['p' => $this->project->id], navigate: true);
    }

    public function back(): void
    {
        $this->redirectRoute('pipeline.source', ['p' => $this->project->id], navigate: true);
    }

    public function render()
    {
        $this->project->load('segments');

        return view('livewire.pipeline.split', [
            'step'    => PipelineStep::Split,
            'project' => $this->project,
            'tones'   => VoiceTone::cases(),
            'presets' => config('pipeline.frame_presets'),
        ]);
    }
}
