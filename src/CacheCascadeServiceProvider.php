<?php

namespace Skaisser\CacheCascade;

use Illuminate\Support\ServiceProvider;
use Skaisser\CacheCascade\Services\CacheCascadeManager;

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
        }
    }
}