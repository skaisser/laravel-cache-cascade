<?php

namespace Skaisser\CacheCascade\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null, array $options = [])
 * @method static void set(string $key, mixed $data, bool $skipDatabase = false)
 * @method static void clearCache(string $key)
 * @method static void clearAllCache()
 * @method static mixed remember(string $key, \Closure $callback, ?int $ttl = null, bool $useVisitorIsolation = false)
 * 
 * @see \Skaisser\CacheCascade\Services\CacheCascadeManager
 */
class CacheCascade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'cache-cascade';
    }
}