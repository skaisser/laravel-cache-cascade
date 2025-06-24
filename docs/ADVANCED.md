# Advanced Usage Guide

This guide covers advanced features and techniques for Laravel Cache Cascade.

## Table of Contents

- [Custom Storage Drivers](#custom-storage-drivers)
- [Performance Optimization](#performance-optimization)
- [Multi-tenant Applications](#multi-tenant-applications)
- [Event-Driven Architecture](#event-driven-architecture)
- [Custom Transformers](#custom-transformers)
- [Database Optimization](#database-optimization)
- [Cache Warming Strategies](#cache-warming-strategies)

## Custom Storage Drivers

### Creating a Custom Storage Layer

You can extend Cache Cascade with custom storage drivers:

```php
namespace App\Cache;

use Skaisser\CacheCascade\Contracts\StorageLayer;

class S3StorageLayer implements StorageLayer
{
    public function get(string $key): mixed
    {
        return Storage::disk('s3')->exists("cache/{$key}.json") 
            ? json_decode(Storage::disk('s3')->get("cache/{$key}.json"), true)
            : null;
    }
    
    public function set(string $key, mixed $data): void
    {
        Storage::disk('s3')->put(
            "cache/{$key}.json", 
            json_encode($data)
        );
    }
    
    public function forget(string $key): void
    {
        Storage::disk('s3')->delete("cache/{$key}.json");
    }
}
```

### Registering Custom Driver

```php
// AppServiceProvider.php
public function register()
{
    $this->app->extend('cache-cascade', function ($manager, $app) {
        $manager->addLayer('s3', new S3StorageLayer());
        return $manager;
    });
}

// config/cache-cascade.php
'fallback_chain' => ['cache', 'file', 's3', 'database'],
```

## Performance Optimization

### Batch Operations

Minimize database queries with batch loading:

```php
class ProductService
{
    public function getMultiple(array $ids): Collection
    {
        $keys = array_map(fn($id) => "product.{$id}", $ids);
        $results = [];
        
        // Check cache for all keys at once
        foreach ($keys as $index => $key) {
            $cached = CacheCascade::get($key);
            if ($cached) {
                $results[$ids[$index]] = $cached;
                unset($ids[$index]);
            }
        }
        
        // Load missing items from database
        if (!empty($ids)) {
            $products = Product::whereIn('id', $ids)->get();
            
            foreach ($products as $product) {
                $key = "product.{$product->id}";
                CacheCascade::set($key, $product->toArray());
                $results[$product->id] = $product->toArray();
            }
        }
        
        return collect($results);
    }
}
```

### Lazy Loading with Generators

For large datasets, use generators to save memory:

```php
class DataProcessor
{
    public function processLargeDataset(): \Generator
    {
        $page = 1;
        
        do {
            $key = "dataset.page.{$page}";
            $data = CacheCascade::remember($key, function() use ($page) {
                return Model::paginate(100, ['*'], 'page', $page);
            }, 3600);
            
            foreach ($data->items() as $item) {
                yield $item;
            }
            
            $page++;
        } while ($data->hasMorePages());
    }
}
```

### Partial Cache Updates

Update only changed portions of cached data:

```php
class SettingsManager
{
    public function updateSetting(string $key, mixed $value): void
    {
        $settings = CacheCascade::get('app.settings', []);
        $settings[$key] = $value;
        
        // Update only if changed
        if (data_get($settings, $key) !== $value) {
            CacheCascade::set('app.settings', $settings);
        }
    }
}
```

## Multi-tenant Applications

### Tenant-Specific Caching

Implement tenant isolation:

```php
trait TenantCaching
{
    protected function getTenantCacheKey(string $key): string
    {
        $tenantId = app('tenant')->id;
        return "tenant.{$tenantId}.{$key}";
    }
    
    public function getCached(string $key, mixed $default = null): mixed
    {
        return CacheCascade::get(
            $this->getTenantCacheKey($key),
            $default,
            ['visitor_isolation' => true]
        );
    }
    
    public function setCached(string $key, mixed $data): void
    {
        CacheCascade::set(
            $this->getTenantCacheKey($key),
            $data
        );
    }
}
```

### Cross-Tenant Shared Cache

Share common data across tenants:

```php
class SharedDataService
{
    public function getSharedData(string $key): mixed
    {
        // First check tenant-specific override
        $tenantKey = "tenant." . tenant()->id . ".{$key}";
        $override = CacheCascade::get($tenantKey);
        
        if ($override !== null) {
            return $override;
        }
        
        // Fall back to shared data
        return CacheCascade::get("shared.{$key}");
    }
}
```

## Event-Driven Architecture

### Cache Events

Listen to cache events for monitoring or side effects:

```php
// EventServiceProvider.php
protected $listen = [
    'cache.cascade.hit' => [
        \App\Listeners\LogCacheHit::class,
    ],
    'cache.cascade.miss' => [
        \App\Listeners\WarmCacheAsync::class,
    ],
    'cache.cascade.invalidated' => [
        \App\Listeners\NotifyCacheInvalidation::class,
    ],
];
```

### Async Cache Warming

```php
namespace App\Listeners;

class WarmCacheAsync
{
    public function handle($event)
    {
        if ($event->key && $event->layer === 'cache') {
            dispatch(new WarmCacheJob($event->key))->onQueue('cache');
        }
    }
}

// Job
class WarmCacheJob implements ShouldQueue
{
    public function handle()
    {
        // Load from database and cache
        $data = $this->loadFromDatabase($this->key);
        CacheCascade::set($this->key, $data);
    }
}
```

### Cache Invalidation Broadcasting

Notify other services of cache changes:

```php
class BroadcastInvalidation
{
    public function handle($event)
    {
        broadcast(new CacheInvalidatedEvent(
            $event->key,
            now()
        ))->toOthers();
    }
}
```

## Custom Transformers

### Complex Data Transformations

Create reusable transformers:

```php
class TransformerPipeline
{
    protected array $transformers = [];
    
    public function add(callable $transformer): self
    {
        $this->transformers[] = $transformer;
        return $this;
    }
    
    public function transform(mixed $data): mixed
    {
        return collect($this->transformers)->reduce(
            fn($data, $transformer) => $transformer($data),
            $data
        );
    }
}

// Usage
$pipeline = (new TransformerPipeline())
    ->add(fn($data) => collect($data)->filter(fn($item) => $item->active))
    ->add(fn($data) => $data->sortBy('priority'))
    ->add(fn($data) => $data->take(10));

$topItems = CacheCascade::get('items', [], [
    'transform' => [$pipeline, 'transform']
]);
```

### Versioned Transformations

Handle data structure changes:

```php
class VersionedTransformer
{
    public function transform(array $data): array
    {
        $version = $data['_version'] ?? 1;
        
        return match($version) {
            1 => $this->transformV1($data),
            2 => $this->transformV2($data),
            default => $data,
        };
    }
    
    protected function transformV1(array $data): array
    {
        // Migrate v1 to v2 structure
        return array_merge($data, [
            '_version' => 2,
            'created_at' => $data['date'] ?? now(),
            'updated_at' => $data['modified'] ?? now(),
        ]);
    }
}
```

## Database Optimization

### Query Optimization for Cache Loading

```php
class OptimizedModel extends Model
{
    use CascadeInvalidation;
    
    public function scopeForCascadeCache($query)
    {
        return $query
            ->select(['id', 'name', 'data']) // Only needed columns
            ->with(['relationship' => function($q) {
                $q->select('id', 'foreign_id', 'value');
            }])
            ->withCount('items') // Avoid N+1 for counts
            ->orderByRaw('ISNULL(priority), priority ASC'); // Nulls last
    }
}
```

### Chunked Loading for Large Datasets

```php
class LargeDatasetService
{
    public function cacheDataset(string $key): void
    {
        $chunks = [];
        
        Model::forCascadeCache()
            ->chunk(1000, function($items) use (&$chunks) {
                $chunks[] = $items->toArray();
            });
        
        // Store chunks separately
        foreach ($chunks as $index => $chunk) {
            CacheCascade::set("{$key}.chunk.{$index}", $chunk);
        }
        
        // Store chunk count
        CacheCascade::set("{$key}.chunks", count($chunks));
    }
    
    public function getDataset(string $key): Collection
    {
        $chunkCount = CacheCascade::get("{$key}.chunks", 0);
        $results = collect();
        
        for ($i = 0; $i < $chunkCount; $i++) {
            $chunk = CacheCascade::get("{$key}.chunk.{$i}", []);
            $results = $results->concat($chunk);
        }
        
        return $results;
    }
}
```

## Cache Warming Strategies

### Scheduled Cache Warming

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Warm critical caches during low-traffic hours
    $schedule->call(function () {
        $keys = ['settings', 'products', 'categories'];
        
        foreach ($keys as $key) {
            CacheCascade::refresh($key);
        }
    })->dailyAt('03:00');
    
    // Warm user-specific caches
    $schedule->job(new WarmUserCachesJob)->hourly();
}
```

### Predictive Cache Warming

```php
class PredictiveCacheWarmer
{
    public function warmRelatedData(string $accessedKey): void
    {
        $predictions = $this->getPredictions($accessedKey);
        
        foreach ($predictions as $predictedKey => $probability) {
            if ($probability > 0.7) {
                dispatch(new WarmCacheJob($predictedKey))
                    ->delay(now()->addSeconds(rand(1, 10)));
            }
        }
    }
    
    protected function getPredictions(string $key): array
    {
        // Use ML model or heuristics to predict next access
        return [
            'related.data' => 0.85,
            'user.preferences' => 0.75,
            'product.recommendations' => 0.65,
        ];
    }
}
```

### Progressive Cache Building

```php
class ProgressiveCacheBuilder
{
    public function buildCache(string $key, int $depth = 1): void
    {
        // Level 1: Cache essential data
        $essentialData = $this->getEssentialData($key);
        CacheCascade::set($key, $essentialData);
        
        if ($depth > 1) {
            // Level 2: Add computed fields asynchronously
            dispatch(function() use ($key, $essentialData) {
                $enhanced = $this->enhanceData($essentialData);
                CacheCascade::set("{$key}.enhanced", $enhanced);
            })->afterResponse();
        }
        
        if ($depth > 2) {
            // Level 3: Pre-compute aggregations
            dispatch(new ComputeAggregationsJob($key))
                ->delay(now()->addMinutes(5));
        }
    }
}
```

## Integration Patterns

### Repository Pattern Integration

```php
abstract class CachedRepository
{
    protected string $cacheKey;
    protected int $ttl = 3600;
    
    public function find(int $id): ?Model
    {
        return CacheCascade::remember(
            "{$this->cacheKey}.{$id}",
            fn() => $this->model::find($id),
            $this->ttl
        );
    }
    
    public function invalidate(int $id): void
    {
        CacheCascade::invalidate("{$this->cacheKey}.{$id}");
    }
    
    public function warmCache(array $ids): void
    {
        $missing = array_filter($ids, function($id) {
            return !CacheCascade::has("{$this->cacheKey}.{$id}");
        });
        
        if (!empty($missing)) {
            $models = $this->model::whereIn('id', $missing)->get();
            
            foreach ($models as $model) {
                CacheCascade::set(
                    "{$this->cacheKey}.{$model->id}",
                    $model->toArray(),
                    false
                );
            }
        }
    }
}
```

### Service Layer Integration

```php
class CachedService
{
    use InteractsWithCache;
    
    protected function getCacheKey(string $method, ...$args): string
    {
        return sprintf(
            '%s.%s.%s',
            class_basename($this),
            $method,
            md5(serialize($args))
        );
    }
    
    protected function cached(string $method, callable $callback, ...$args): mixed
    {
        return CacheCascade::remember(
            $this->getCacheKey($method, ...$args),
            $callback,
            $this->getCacheTtl($method)
        );
    }
    
    public function getComplexData(array $filters): Collection
    {
        return $this->cached(__METHOD__, function() use ($filters) {
            return $this->repository->getFiltered($filters);
        }, $filters);
    }
}
```

## Monitoring and Debugging

### Performance Profiling

```php
class CacheProfiler
{
    protected array $metrics = [];
    
    public function profile(string $operation, callable $callback): mixed
    {
        $start = microtime(true);
        $startMemory = memory_get_usage();
        
        $result = $callback();
        
        $this->metrics[$operation] = [
            'time' => microtime(true) - $start,
            'memory' => memory_get_usage() - $startMemory,
            'timestamp' => now(),
        ];
        
        return $result;
    }
    
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}

// Usage with CacheCascade
$profiler = new CacheProfiler();

$data = $profiler->profile('cache.get', function() {
    return CacheCascade::get('complex.data');
});
```

## Further Reading

- [Main Documentation](../README.md)
- [Testing Guide](TESTING.md)
- [Security Best Practices](SECURITY.md)
- [Troubleshooting](TROUBLESHOOTING.md)