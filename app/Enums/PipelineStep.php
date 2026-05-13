<?php

namespace App\Enums;

/**
 * The 6 ordered steps of the Burmese movie recap pipeline.
 *
 * Stored on the projects table as a string column (`current_step`).
 * The numeric ordinal returned by ::order() drives the step bar UI.
 */
enum PipelineStep: string
{
    case Source    = 'source';
    case Split     = 'split';
    case Translate = 'translate';
    case EditSrt   = 'edit_srt';
    case Voice     = 'voice';
    case Render    = 'render';

    /** Zero-based ordinal — matches the design step numbers (0..5). */
    public function order(): int
    {
        return match ($this) {
            self::Source    => 0,
            self::Split     => 1,
            self::Translate => 2,
            self::EditSrt   => 3,
            self::Voice     => 4,
            self::Render    => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Source    => 'Source',
            self::Split     => 'Split',
            self::Translate => 'Translate',
            self::EditSrt   => 'Edit SRT',
            self::Voice     => 'Voice',
            self::Render    => 'Render',
        };
    }

    /** Route name for the Livewire full-page component. */
    public function routeName(): string
    {
        return 'pipeline.'.$this->value;
    }

    /** @return array<int, self> all 6 steps in order */
    public static function ordered(): array
    {
        return [
            self::Source, self::Split, self::Translate,
            self::EditSrt, self::Voice, self::Render,
        ];
    }
}
