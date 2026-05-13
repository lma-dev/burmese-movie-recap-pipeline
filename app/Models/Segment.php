<?php

namespace App\Models;

use App\Enums\StepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Segment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'transcribe_status' => StepStatus::class,
        'translate_status'  => StepStatus::class,
        'voice_status'      => StepStatus::class,
        'render_status'     => StepStatus::class,
        'render_progress'   => 'integer',
        'voice_progress'    => 'integer',
        'part_number'       => 'integer',
        'start_sec'         => 'integer',
        'end_sec'           => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subtitles(): HasMany
    {
        return $this->hasMany(Subtitle::class)->orderBy('line_number');
    }

    /** "Part 01", "Part 02", etc — matches the design column. */
    public function label(): string
    {
        return 'Part '.str_pad((string) $this->part_number, 2, '0', STR_PAD_LEFT);
    }

    /** "00:00 → 02:00" range for the segment grid card. */
    public function range(): string
    {
        return $this->formatSeconds($this->start_sec).' → '.$this->formatSeconds($this->end_sec);
    }

    private function formatSeconds(int $s): string
    {
        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }
}
