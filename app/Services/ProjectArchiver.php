<?php

namespace App\Services;

use App\Models\Project;
use RuntimeException;
use ZipArchive;

/**
 * Bundles a project's rendered segments into project.zip.
 *
 * Lives outside the controller so the HTTP layer stays thin
 * (laravel-basic-skill: no file I/O or business logic in controllers).
 */
class ProjectArchiver
{
    public function __construct(private readonly PipelinePaths $paths)
    {
    }

    /**
     * Build the ZIP and return its absolute path, or null when there's
     * nothing rendered yet (caller decides whether that's a 404).
     */
    public function buildZip(Project $project): ?string
    {
        $rendered = $project->segments()
            ->whereNotNull('rendered_path')
            ->orderBy('part_number')
            ->get()
            ->filter(fn ($segment) => is_file($segment->rendered_path));

        if ($rendered->isEmpty()) {
            return null;
        }

        $zipPath = $this->paths->zipPath($project);
        if (is_file($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not open ZIP for writing: {$zipPath}");
        }

        foreach ($rendered as $segment) {
            $zip->addFile(
                $segment->rendered_path,
                sprintf('part_%03d.mp4', $segment->part_number),
            );
        }
        $zip->close();

        return $zipPath;
    }
}
