<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PythonRunner is the only collaborator that needs constructor args
        // coming from config(), so we wire it up here once.
        $this->app->singleton(\App\Services\PythonRunner::class, function ($app) {
            return new \App\Services\PythonRunner(
                pythonBin:   (string) config('pipeline.python_bin'),
                scriptsPath: (string) config('pipeline.python_scripts_path'),
            );
        });

        $this->app->singleton(\App\Services\PipelinePaths::class);
        $this->app->singleton(\App\Services\SrtWriter::class);
    }

    public function boot(): void
    {
        // Surface lazy-loading bugs locally — see laravel-basic-skill.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
