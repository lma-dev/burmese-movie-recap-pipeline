<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Segment;
use Illuminate\Support\Facades\Storage;

/**
 * One place that knows where each pipeline artifact lives on disk.
 *
 * Layout (relative to PIPELINE_STORAGE_ROOT under the configured disk):
 *
 *   pipeline/
 *     {project_id}/
 *       source.mp4
 *       segments/
 *         part_001.mp4
 *         part_002.mp4
 *         ...
 *       voice/
 *         part_001.voice.wav
 *         ...
 *       rendered/
 *         part_001.rendered.mp4
 *         ...
 *       srt/
 *         part_001.srt
 *         ...
 *       project.zip
 */
class PipelinePaths
{
    public function projectDir(Project $project): string
    {
        return $this->abs((string) $project->id);
    }

    public function segmentsDir(Project $project): string
    {
        return $this->abs($project->id.'/segments');
    }

    public function voiceDir(Project $project): string
    {
        return $this->abs($project->id.'/voice');
    }

    public function renderedDir(Project $project): string
    {
        return $this->abs($project->id.'/rendered');
    }

    public function srtDir(Project $project): string
    {
        return $this->abs($project->id.'/srt');
    }

    public function srtPath(Segment $segment): string
    {
        return $this->srtDir($segment->project)
            .DIRECTORY_SEPARATOR
            .sprintf('part_%03d.srt', $segment->part_number);
    }

    public function voicePath(Segment $segment): string
    {
        return $this->voiceDir($segment->project)
            .DIRECTORY_SEPARATOR
            .sprintf('part_%03d.voice.wav', $segment->part_number);
    }

    public function renderedPath(Segment $segment): string
    {
        return $this->renderedDir($segment->project)
            .DIRECTORY_SEPARATOR
            .sprintf('part_%03d.rendered.mp4', $segment->part_number);
    }

    public function zipPath(Project $project): string
    {
        return $this->projectDir($project).DIRECTORY_SEPARATOR.'project.zip';
    }

    /** Absolute filesystem path on the pipeline disk. */
    private function abs(string $rel): string
    {
        $disk = config('pipeline.storage_disk');
        $root = config('pipeline.storage_root');

        return Storage::disk($disk)->path($root.'/'.$rel);
    }
}
