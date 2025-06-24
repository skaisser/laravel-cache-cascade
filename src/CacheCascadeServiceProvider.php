<?php

namespace Skaisser\CacheCascade;

use Illuminate\Support\ServiceProvider;
use Skaisser\CacheCascade\Services\CacheCascadeManager;
use Skaisser\CacheCascade\Console\Commands\RefreshCommand;
use Skaisser\CacheCascade\Console\Commands\ClearCommand;
use Skaisser\CacheCascade\Console\Commands\StatsCommand;

class CacheCascadeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cache-cascade.php', 'cache-cascade');

        $this->app->singleton('cache-cascade', function ($app) {
            return new CacheCascadeManager($app);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__.'/../config/cache-cascade.php' => config_path('cache-cascade.php'),
            ], 'cache-cascade-config');
            
            // Register Artisan commands
            $this->commands([
                RefreshCommand::class,
                ClearCommand::class,
                StatsCommand::class,
            ]);
        }
        
        // Integrate with Laravel's cache:clear command
        $this->app['events']->listen('cache:clearing', function() {
            if (config('cache-cascade.clear_on_cache_clear', true)) {
                $this->app['cache-cascade']->clearAllCache();
            }
        });
    }
}