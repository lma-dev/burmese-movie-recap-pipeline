<?php

namespace App\Enums;

/**
 * Status of an individual step OR an individual segment job.
 *
 * Drives both the step-bar coloring (done / active / pending) and
 * the per-segment pill in the Translate / Voice / Render tables.
 */
enum StepStatus: string
{
    case Pending    = 'pending';
    case Running    = 'running';
    case Done       = 'done';
    case Failed     = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'In progress',
            self::Done    => 'Done',
            self::Failed  => 'Failed',
        };
    }

    /** CSS pill class used in the Blade views. */
    public function pillClass(): string
    {
        return match ($this) {
            self::Pending => 'pill',
            self::Running => 'pill blue',
            self::Done    => 'pill green',
            self::Failed  => 'pill red',
        };
    }
}
