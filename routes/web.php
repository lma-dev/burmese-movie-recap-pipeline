<?php

use App\Enums\PipelineStep;
use App\Http\Controllers\DownloadController;
use App\Livewire\Pipeline\EditSrt;
use App\Livewire\Pipeline\Finalize;
use App\Livewire\Pipeline\Source;
use App\Livewire\Pipeline\Split;
use App\Livewire\Pipeline\Translate;
use Illuminate\Support\Facades\Route;

// Entry: the Source step IS the home page (paste a URL and you're in).
Route::redirect('/', '/pipeline/source')->name('home');

// -----------------------------------------------------------------
// 5 Livewire full-page step routes
// -----------------------------------------------------------------
Route::prefix('pipeline')->name('pipeline.')->group(function () {
    Route::get('/source',    Source::class)   ->name(PipelineStep::Source->value);
    Route::get('/split',     Split::class)    ->name(PipelineStep::Split->value);
    Route::get('/translate', Translate::class)->name(PipelineStep::Translate->value);
    Route::get('/edit-srt',  EditSrt::class)  ->name(PipelineStep::EditSrt->value);
    Route::get('/finalize',  Finalize::class) ->name(PipelineStep::Finalize->value);
});

// -----------------------------------------------------------------
// Downloads — full project ZIP + single rendered segment
// -----------------------------------------------------------------
Route::get('/projects/{project}/download.zip', [DownloadController::class, 'projectZip'])
    ->name('projects.download_zip');

Route::get('/segments/{segment}/download', [DownloadController::class, 'segment'])
    ->name('segments.download');

// Inline streaming endpoint for the <video> preview on the Render step.
Route::get('/segments/{segment}/stream', [DownloadController::class, 'stream'])
    ->name('segments.stream');
