<?php

namespace App\Models;

use App\Enums\AudioMixMode;
use App\Enums\PipelineStep;
use App\Enums\StepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'current_step'      => PipelineStep::class,
        'source_status'     => StepStatus::class,
        'split_status'      => StepStatus::class,
        'translate_status'  => StepStatus::class,
        'edit_srt_status'   => StepStatus::class,
        'voice_status'      => StepStatus::class,
        'render_status'     => StepStatus::class,
        'audio_mix_mode'    => AudioMixMode::class,
        'segment_seconds'   => 'integer',
        'segment_count'     => 'integer',
        'source_duration_sec' => 'integer',
    ];

    // -----------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------
    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class)->orderBy('part_number');
    }

    // -----------------------------------------------------------------
    // Step-status helpers
    //
    // The 6 steps each have their own column on the projects row.
    // These two helpers let UI code work in terms of the PipelineStep
    // enum instead of hard-coded column names, which keeps the step
    // bar component generic and the controllers tiny.
    // -----------------------------------------------------------------
    public function statusFor(PipelineStep $step): StepStatus
    {
        return $this->{$step->value.'_status'};
    }

    public function setStatusFor(PipelineStep $step, StepStatus $status): void
    {
        $this->{$step->value.'_status'} = $status;
    }

    /**
     * UI helper: which CSS class to apply to a step indicator
     * — done / active / (empty for pending).
     */
    public function stepBarClass(PipelineStep $step): string
    {
        $status = $this->statusFor($step);

        return match (true) {
            $status === StepStatus::Done                   => 'done',
            $this->current_step === $step                  => 'active',
            default                                        => '',
        };
    }

    /**
     * Resolve the configured frame preset (from config/pipeline.php)
     * — falls back to the default if the stored key is unknown.
     */
    public function framePreset(): array
    {
        $presets = config('pipeline.frame_presets');

        return $presets[$this->frame_preset] ?? $presets[config('pipeline.default_frame_preset')];
    }
}
