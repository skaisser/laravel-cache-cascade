<?php

namespace Skaisser\CacheCascade\Facades;

use Illuminate\Support\Facades\Facade;
use Skaisser\CacheCascade\Testing\CacheCascadeFake;

/**
 * @method static mixed get(string $key, mixed $default = null, array $options = [])
 * @method static void set(string $key, mixed $data, bool $skipDatabase = false)
 * @method static void clearCache(string $key)
 * @method static void forget(string $key)
 * @method static void clearAllCache()
 * @method static mixed remember(string $key, \Closure $callback, ?int $ttl = null, bool $useVisitorIsolation = false)
 * @method static mixed rememberFor(string $key, int $ttl, \Closure $callback)
 * @method static mixed refresh(string $key)
 * @method static void invalidate(string $key)
 * @method static array getStats()
 * 
 * @see \Skaisser\CacheCascade\Services\CacheCascadeManager
 */
class CacheCascade extends Facade
{
    /**
     * Replace the bound instance with a fake.
     *
     * @return \Skaisser\CacheCascade\Testing\CacheCascadeFake
     */
    public static function fake()
    {
        $fake = new CacheCascadeFake();
        static::swap($fake);
        
        return $fake;
    }
    
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