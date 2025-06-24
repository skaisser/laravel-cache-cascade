<?php

use Skaisser\CacheCascade\Facades\CacheCascade;

if (!function_exists('cache_cascade')) {
    /**
     * Get or set a value in the cache cascade
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed|\Skaisser\CacheCascade\Services\CacheCascadeManager
     */
    function cache_cascade($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('cache-cascade');
        }

        if (is_callable($default)) {
            return CacheCascade::remember($key, $default);
        }

        return CacheCascade::get($key, $default);
    }
}