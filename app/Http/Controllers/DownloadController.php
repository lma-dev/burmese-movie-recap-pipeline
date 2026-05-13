<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Segment;
use App\Services\PipelinePaths;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * Final-mile delivery: serve the rendered mp4 for one segment or
 * the bundled ZIP of all rendered segments for a project.
 *
 * Per laravel-basic-skill, controllers stay thin — these methods
 * delegate path resolution to PipelinePaths and just sendFile().
 */
class DownloadController extends Controller
{
    public function segment(Segment $segment): BinaryFileResponse|Response
    {
        $path = $segment->rendered_path;
        if (! $path || ! is_file($path)) {
            abort(404, 'Rendered segment not available yet.');
        }
        $name = sprintf('%s_part_%03d.mp4',
            str($segment->project->source_title ?? 'recap')->slug('_'),
            $segment->part_number,
        );
        return response()->download($path, $name);
    }

    public function projectZip(Project $project, PipelinePaths $paths): BinaryFileResponse|Response
    {
        $project->loadMissing('segments');

        $renderedFiles = $project->segments
            ->filter(fn ($s) => $s->rendered_path && is_file($s->rendered_path));

        if ($renderedFiles->isEmpty()) {
            abort(404, 'No rendered segments yet.');
        }

        $zipPath = $paths->zipPath($project);
        @unlink($zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create ZIP.');
        }
        foreach ($renderedFiles as $seg) {
            $zip->addFile($seg->rendered_path, sprintf('part_%03d.mp4', $seg->part_number));
        }
        $zip->close();

        return response()->download($zipPath, str($project->source_title ?? 'recap')->slug('_').'.zip');
    }
}
