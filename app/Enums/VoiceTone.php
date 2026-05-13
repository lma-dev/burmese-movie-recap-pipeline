<?php

namespace App\Enums;

/**
 * Narration tone / style for the Burmese voice generation step.
 *
 * Stored on the projects table as a string column (`voice_tone`).
 * Drives the prompt + voice parameters sent to the TTS provider.
 */
enum VoiceTone: string
{
    case Neutral    = 'neutral';
    case Dramatic   = 'dramatic';
    case Calm       = 'calm';
    case Energetic  = 'energetic';

    public function label(): string
    {
        return match ($this) {
            self::Neutral   => 'Neutral',
            self::Dramatic  => 'Dramatic',
            self::Calm      => 'Calm',
            self::Energetic => 'Energetic',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Neutral   => 'Clean, balanced delivery. Works for most movie genres.',
            self::Dramatic  => 'Bold, expressive narration. Good for action and thriller recaps.',
            self::Calm      => 'Soft, measured tone. Best for romance or slow-paced films.',
            self::Energetic => 'Upbeat, lively pacing. Great for comedy and feel-good recaps.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Neutral   => 'microphone',
            self::Dramatic  => 'fire',
            self::Calm      => 'sparkles',
            self::Energetic => 'bolt',
        };
    }

    public static function default(): self
    {
        return self::Neutral;
    }
}
