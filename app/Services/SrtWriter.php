<?php

namespace App\Services;

use App\Models\Segment;
use Illuminate\Support\Facades\File;

/**
 * Serialize a segment's editable subtitles to a real .srt file on disk.
 * Render and voice jobs consume this file directly.
 */
class SrtWriter
{
    public function __construct(private readonly PipelinePaths $paths) {}

    public function writeForSegment(Segment $segment): string
    {
        $segment->loadMissing('subtitles');
        $path = $this->paths->srtPath($segment);
        File::ensureDirectoryExists(dirname($path));

        $body = $segment->subtitles
            ->map(fn ($line, $i) => sprintf(
                "%d\n%s --> %s\n%s\n",
                $i + 1,
                $line->startTimecode(),
                $line->endTimecode(),
                trim($line->burmese_text),
            ))
            ->implode("\n");

        File::put($path, $body);

        return $path;
    }
}
