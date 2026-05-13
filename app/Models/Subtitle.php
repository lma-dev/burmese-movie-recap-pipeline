<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subtitle extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'line_number' => 'integer',
        'start_ms'    => 'integer',
        'end_ms'      => 'integer',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /** SRT-style timecode "HH:MM:SS,mmm" for the start of this line. */
    public function startTimecode(): string
    {
        return $this->msToTimecode($this->start_ms);
    }

    public function endTimecode(): string
    {
        return $this->msToTimecode($this->end_ms);
    }

    private function msToTimecode(int $ms): string
    {
        $hours = intdiv($ms, 3600_000);
        $ms -= $hours * 3600_000;
        $minutes = intdiv($ms, 60_000);
        $ms -= $minutes * 60_000;
        $seconds = intdiv($ms, 1000);
        $millis  = $ms - $seconds * 1000;

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $seconds, $millis);
    }
}
