<?php

namespace Skaisser\CacheCascade\Traits;

use Skaisser\CacheCascade\Facades\CacheCascade;

trait CascadeInvalidation
{
    /**
     * Boot the cascade invalidation trait
     */
    public static function bootCascadeInvalidation(): void
    {
        // Invalidate cache on create, update, or delete
        static::saved(function ($model) {
            $model->invalidateCascadeCache();
        });

        static::deleted(function ($model) {
            $model->invalidateCascadeCache();
        });

        // If using soft deletes, also invalidate on restore
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->invalidateCascadeCache();
            });
        }
    }

    /**
     * Invalidate the cascade cache for this model
     *
     * @return void
     */
    public function invalidateCascadeCache(): void
    {
        $cacheKey = $this->getCascadeCacheKey();
        
        if ($cacheKey) {
            CacheCascade::refresh($cacheKey);
        }
    }

    /**
     * Get the cache key for this model
     * Override this method to customize the cache key
     *
     * @return string|null
     */
    public function getCascadeCacheKey(): ?string
    {
        // Default implementation uses table name
        // e.g., 'faqs' table becomes 'faqs' cache key
        return $this->getTable();
    }

    /**
     * Manually refresh the cascade cache
     *
     * @return mixed
     */
    public function refreshCascadeCache(): mixed
    {
        $cacheKey = $this->getCascadeCacheKey();
        
        if (!$cacheKey) {
            return null;
        }

        return CacheCascade::refresh($cacheKey);
    }

    /**
     * Get all records for caching
     * Override this method to customize what data gets cached
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function scopeForCascadeCache($query)
    {
        // Default: order by 'order' column if it exists
        if (in_array('order', $this->getFillable())) {
            return $query->orderBy('order');
        }
        
        return $query;
    }
}