<?php

namespace Nedieyassin\LaravelTsModels;

use Illuminate\Support\ServiceProvider;
use Nedieyassin\LaravelTsModels\Commands\GenerateTypesCommand;

class TsModelsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ts-models.php' => config_path('ts-models.php'),
            ], 'ts-models-config');

            $this->commands([
                GenerateTypesCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ts-models.php',
            'ts-models'
        );
    }
}