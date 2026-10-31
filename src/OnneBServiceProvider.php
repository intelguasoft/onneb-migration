<?php

namespace RealsoftGt\OnneB;

use Illuminate\Support\ServiceProvider;

class OnneBServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/onneb.php', 'onneb');

    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/onneb.php' => config_path('onneb.php')], 'onneb-config');
    }
}
