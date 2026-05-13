<?php

namespace App\Enums;

/**
 * The 5 ordered steps of the Burmese movie recap pipeline.
 *
 * Stored on the projects table as a string column (`current_step`).
 * The numeric ordinal returned by ::order() drives the step bar UI.
 *
 * All up-front configuration (segment length, narration tone, audio mix,
 * frame preset, etc.) is collected in the Split step; the user only
 * interacts again on Edit-SRT to refine subtitles. Voice + Render run
 * back-to-back inside a single "Finalize" page.
 */
enum PipelineStep: string
{
    case Source    = 'source';
    case Split     = 'split';
    case Translate = 'translate';
    case EditSrt   = 'edit_srt';
    case Finalize  = 'finalize';

    /** Zero-based ordinal — matches the design step numbers (0..4). */
    public function order(): int
    {
        return match ($this) {
            self::Source    => 0,
            self::Split     => 1,
            self::Translate => 2,
            self::EditSrt   => 3,
            self::Finalize  => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Source    => 'Source',
            self::Split     => 'Settings',
            self::Translate => 'Translate',
            self::EditSrt   => 'Edit SRT',
            self::Finalize  => 'Finalize',
        };
    }

    /** Route name for the Livewire full-page component. */
    public function routeName(): string
    {
        return 'pipeline.'.$this->value;
    }

    /** @return array<int, self> all steps in order */
    public static function ordered(): array
    {
        return [
            self::Source, self::Split, self::Translate,
            self::EditSrt, self::Finalize,
        ];
    }
}
