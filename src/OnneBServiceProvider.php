<?php

namespace RealsoftGt\OnneB;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RealsoftGt\OnneB\Mapping\CatalogMapper;

class OnneBServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/onneb.php', 'onneb');

        $this->app->bind(CatalogMapper::class, fn (Application $app): CatalogMapper => new CatalogMapper(
            $app['config']->get('onneb.mapping.extra_units', []),
            $app['config']->get('onneb.mapping.generic_brands', ['VARIOS']),
        ));
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/onneb.php' => config_path('onneb.php')], 'onneb-config');
    }
}
