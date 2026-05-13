<?php

namespace App\Enums;

/**
 * How the Burmese narrator is mixed with the source audio.
 *
 *  - Replace  : drop the original audio entirely, keep narrator only
 *  - Duck     : keep original audio at ~ -18 dB whenever narrator speaks
 *               (the design's documented default)
 */
enum AudioMixMode: string
{
    case Replace = 'replace';
    case Duck    = 'duck';

    public function label(): string
    {
        return match ($this) {
            self::Replace => 'Replace original audio entirely',
            self::Duck    => 'Keep original audio at low volume under narrator',
        };
    }
}
