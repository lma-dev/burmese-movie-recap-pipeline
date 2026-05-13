<?php

use App\Enums\PipelineStep;
use App\Http\Controllers\DownloadController;
use App\Livewire\Pipeline\EditSrt;
use App\Livewire\Pipeline\Render;
use App\Livewire\Pipeline\Source;
use App\Livewire\Pipeline\Split;
use App\Livewire\Pipeline\Translate;
use App\Livewire\Pipeline\Voice;
use App\Models\Segment;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// Entry: the Source step IS the home page (matches the design's Slide 1
// "Overview" → "Step 0" flow — paste a URL and you're in).
Route::redirect('/', '/pipeline/source')->name('home');

// -----------------------------------------------------------------
// 6 Livewire full-page step routes
// -----------------------------------------------------------------
Route::prefix('pipeline')->name('pipeline.')->group(function () {
    Route::get('/source',    Source::class)   ->name(PipelineStep::Source->value);
    Route::get('/split',     Split::class)    ->name(PipelineStep::Split->value);
    Route::get('/translate', Translate::class)->name(PipelineStep::Translate->value);
    Route::get('/edit-srt',  EditSrt::class)  ->name(PipelineStep::EditSrt->value);
    Route::get('/voice',     Voice::class)    ->name(PipelineStep::Voice->value);
    Route::get('/render',    Render::class)   ->name(PipelineStep::Render->value);
});

// -----------------------------------------------------------------
// Downloads — full project ZIP + single rendered segment
// -----------------------------------------------------------------
Route::get('/projects/{project}/download.zip', [DownloadController::class, 'projectZip'])
    ->name('projects.download_zip');

Route::get('/segments/{segment}/download', [DownloadController::class, 'segment'])
    ->name('segments.download');

// Inline streaming endpoint for the <video> preview on the Render step.
Route::get('/segments/{segment}/stream', function (Segment $segment): BinaryFileResponse {
    abort_unless($segment->rendered_path && is_file($segment->rendered_path), 404);
    return response()->file($segment->rendered_path);
})->name('segments.stream');
