<?php

namespace RealsoftGt\OnneB;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RealsoftGt\OnneB\Console\ExportCommand;
use RealsoftGt\OnneB\Console\MigrateCommand;
use RealsoftGt\OnneB\Console\ReviewCommand;
use RealsoftGt\OnneB\Console\VerifyCommand;
use RealsoftGt\OnneB\Mapping\CatalogMapper;
use RealsoftGt\OnneB\Review\ReviewReport;
use RealsoftGt\OnneB\Sources\SourceManager;

class OnneBServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/onneb.php', 'onneb');

        $this->app->singleton(SourceManager::class);

        $this->app->bind(CatalogMapper::class, fn (Application $app): CatalogMapper => new CatalogMapper(
            $app['config']->get('onneb.mapping.extra_units', []),
            $app['config']->get('onneb.mapping.generic_brands', ['VARIOS']),
        ));

        $this->app->bind(ReviewReport::class, fn (Application $app): ReviewReport => new ReviewReport(
            $app['config']->get('onneb.review.disk', 'local'),
            $app['config']->get('onneb.review.directory', 'onneb'),
            $app['config']->get('onneb.review.target_name', 'Destino'),
        ));
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/onneb.php' => config_path('onneb.php')], 'onneb-config');

        $this->commands([ExportCommand::class, VerifyCommand::class, ReviewCommand::class, MigrateCommand::class]);
    }
}
