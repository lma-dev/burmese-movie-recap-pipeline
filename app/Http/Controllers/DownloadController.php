<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Segment;
use App\Services\ProjectArchiver;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Final-mile delivery: serve the rendered mp4 for one segment, stream
 * it inline for the <video> preview, or send the bundled project ZIP.
 *
 * Per laravel-basic-skill, controllers stay thin — file I/O and zipping
 * live in ProjectArchiver; filename formatting lives on the Project model.
 */
class DownloadController extends Controller
{
    public function __construct(private readonly ProjectArchiver $archiver)
    {
    }

    public function segment(Segment $segment): BinaryFileResponse
    {
        $segment->loadMissing('project');

        abort_unless(
            $segment->rendered_path && is_file($segment->rendered_path),
            404,
            'Rendered segment not available yet.',
        );

        return response()->download(
            $segment->rendered_path,
            sprintf('%s_part_%03d.mp4', $segment->project->downloadSlug(), $segment->part_number),
        );
    }

    public function stream(Segment $segment): BinaryFileResponse
    {
        abort_unless(
            $segment->rendered_path && is_file($segment->rendered_path),
            404,
        );

        return response()->file($segment->rendered_path);
    }

    public function projectZip(Project $project): BinaryFileResponse
    {
        $zipPath = $this->archiver->buildZip($project);

        abort_if($zipPath === null, 404, 'No rendered segments yet.');

        return response()->download($zipPath, $project->downloadSlug().'.zip');
    }
}
