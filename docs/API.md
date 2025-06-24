# API Reference

Complete API documentation for Laravel Cache Cascade.

## CacheCascade Facade

### Core Methods

#### get()
Retrieve data from cache with automatic fallback.

```php
CacheCascade::get(
    string $key,
    mixed $default = null,
    array $options = []
): mixed
```

**Parameters:**
- `$key` - The cache key to retrieve
- `$default` - Default value if not found
- `$options` - Additional options:
  - `ttl` - Cache TTL in seconds
  - `transform` - Callback to transform data
  - `visitor_isolation` - Enable visitor-specific cache

**Example:**
```php
$data = CacheCascade::get('settings', [], [
    'ttl' => 3600,
    'visitor_isolation' => true
]);
```

---

#### set()
Store data across all cache layers.

```php
CacheCascade::set(
    string $key,
    mixed $data,
    bool $skipDatabase = false
): void
```

**Parameters:**
- `$key` - The cache key
- `$data` - Data to cache
- `$skipDatabase` - Skip database storage

**Example:**
```php
CacheCascade::set('settings', $settings);
CacheCascade::set('config', $config, true); // Skip DB
```

---

#### remember()
Get an item from cache or store the result of a callback.

```php
CacheCascade::remember(
    string $key,
    \Closure $callback,
    ?int $ttl = null,
    bool $useVisitorIsolation = false
): mixed
```

**Parameters:**
- `$key` - The cache key
- `$callback` - Closure to generate data
- `$ttl` - Time to live in seconds
- `$useVisitorIsolation` - Enable visitor isolation

**Example:**
```php
$users = CacheCascade::remember('active-users', function() {
    return User::active()->get();
}, 3600);
```

---

#### invalidate()
Clear cache and file storage for a key.

```php
CacheCascade::invalidate(string $key): void
```

**Example:**
```php
CacheCascade::invalidate('products');
```

---

#### refresh()
Reload data from database and update all cache layers.

```php
CacheCascade::refresh(string $key): mixed
```

**Example:**
```php
$freshData = CacheCascade::refresh('settings');
```

---

#### clearCache()
Clear only the cache layer for a specific key.

```php
CacheCascade::clearCache(string $key): void
```

**Example:**
```php
CacheCascade::clearCache('temporary-data');
```

---

#### clearAllCache()
Clear all cascade cache entries.

```php
CacheCascade::clearAllCache(): void
```

**Example:**
```php
CacheCascade::clearAllCache();
```

---

#### getStats()
Get runtime statistics.

```php
CacheCascade::getStats(): array
```

**Returns:**
```php
[
    'hits' => [
        'cache' => 150,
        'file' => 45,
        'database' => 12
    ],
    'misses' => 8,
    'writes' => 67
]
```

---

#### fake()
Replace with a test double for testing.

```php
CacheCascade::fake(): CacheCascadeFake
```

**Example:**
```php
$fake = CacheCascade::fake();
// Run tests
$fake->assertCalled('get', ['key']);
```

## CascadeInvalidation Trait

### Methods

#### getCascadeCacheKey()
Override to customize the cache key for a model.

```php
public function getCascadeCacheKey(): ?string
```

**Default:** Returns the model's table name

**Example:**
```php
public function getCascadeCacheKey(): ?string
{
    return 'custom_cache_key';
}
```

---

#### scopeForCascadeCache()
Define a query scope for cache loading.

```php
public function scopeForCascadeCache($query)
```

**Example:**
```php
public function scopeForCascadeCache($query)
{
    return $query->where('active', true)
                 ->orderBy('priority')
                 ->with('translations');
}
```

---

#### refreshCascadeCache()
Manually refresh the cache for this model.

```php
public function refreshCascadeCache(): void
```

**Example:**
```php
$model->refreshCascadeCache();
```

---

#### shouldInvalidateCascadeCache()
Control when cache should be invalidated.

```php
protected function shouldInvalidateCascadeCache(): bool
```

**Default:** Returns true

**Example:**
```php
protected function shouldInvalidateCascadeCache(): bool
{
    // Only invalidate if important fields changed
    return $this->isDirty(['name', 'price', 'status']);
}
```

## Artisan Commands

### cache:cascade:refresh

Refresh a cache key from the database.

```bash
php artisan cache:cascade:refresh {key}
```

**Options:**
- `-v, --verbose` - Show refreshed data

**Example:**
```bash
php artisan cache:cascade:refresh settings
php artisan cache:cascade:refresh products -v
```

---

### cache:cascade:clear

Clear specific or all cascade cache.

```bash
php artisan cache:cascade:clear {key?}
```

**Options:**
- `--all` - Clear all cascade cache

**Examples:**
```bash
php artisan cache:cascade:clear settings
php artisan cache:cascade:clear --all
```

---

### cache:cascade:stats

Display cache statistics.

```bash
php artisan cache:cascade:stats {key?}
```

**Examples:**
```bash
php artisan cache:cascade:stats
php artisan cache:cascade:stats products
```

**Output:**
```
Cache Cascade General Statistics
--------------------------------------------------
Runtime Statistics:
  Cache Hits: 1,234
  File Hits: 567
  Database Hits: 89
  Total Misses: 12
  Write Operations: 345
  Cache Hit Rate: 85.5%
```

## Helper Classes

### ConfigCacheHelper

#### getStaticConfigFiles()
Get config files excluding dynamic cascade files.

```php
ConfigCacheHelper::getStaticConfigFiles(): array
```

**Example:**
```php
$files = ConfigCacheHelper::getStaticConfigFiles();
// Returns all config files except those in dynamic path
```

---

#### shouldIncludeInConfigCache()
Check if cascade files should be included in config:cache.

```php
ConfigCacheHelper::shouldIncludeInConfigCache(): bool
```

---

#### getFileStorageKeys()
Get all cascade cache keys from file storage.

```php
ConfigCacheHelper::getFileStorageKeys(): array
```

**Example:**
```php
$keys = ConfigCacheHelper::getFileStorageKeys();
// Returns ['settings', 'products', 'categories']
```

## Configuration Options

### config/cache-cascade.php

```php
return [
    // Storage configuration
    'config_path' => 'config/dynamic',
    'config_format' => 'php', // or 'json'
    
    // Cache configuration
    'cache_prefix' => 'cascade:',
    'default_ttl' => 86400,
    'use_tags' => false,
    'cache_tag' => 'cascade',
    
    // Features
    'visitor_isolation' => false,
    'use_database' => true,
    'auto_seed' => true,
    
    // Model configuration
    'model_namespace' => 'App\\Models\\',
    'seeder_namespace' => 'Database\\Seeders\\',
    
    // Behavior
    'fallback_chain' => ['cache', 'file', 'database'],
    'clear_on_cache_clear' => true,
    'include_in_config_cache' => false,
    
    // Logging
    'logging' => [
        'enabled' => false,
        'channel' => env('LOG_CHANNEL', 'stack'),
        'level' => 'info',
        'log_hits' => false,
        'log_misses' => true,
        'log_writes' => true,
    ],
];
```

## Events

Cache Cascade fires the following events:

### cache.cascade.hit
Fired when data is found in cache.

**Payload:**
```php
[
    'key' => 'settings',
    'layer' => 'cache', // or 'file', 'database'
    'data' => [...],
]
```

### cache.cascade.miss
Fired when data is not found.

**Payload:**
```php
[
    'key' => 'missing-key',
    'default' => null,
]
```

### cache.cascade.write
Fired when data is written.

**Payload:**
```php
[
    'key' => 'settings',
    'data' => [...],
    'layers' => ['cache', 'file'],
]
```

### cache.cascade.invalidated
Fired when cache is invalidated.

**Payload:**
```php
[
    'key' => 'products',
    'source' => 'model', // or 'manual'
]
```

## Testing Assertions

### CacheCascadeFake Methods

#### assertCalled()
```php
$fake->assertCalled(string $method, array $arguments = null): void
```

#### assertNotCalled()
```php
$fake->assertNotCalled(string $method): void
```

#### assertHas()
```php
$fake->assertHas(string $key, bool $withVisitorIsolation = false): void
```

#### assertMissing()
```php
$fake->assertMissing(string $key, bool $withVisitorIsolation = false): void
```

#### calledCount()
```php
$fake->calledCount(string $method): int
```

#### getCalls()
```php
$fake->getCalls(): array
```

#### reset()
```php
$fake->reset(): void
```

## Error Handling

### Exceptions

Cache Cascade gracefully handles errors and logs them. It does not throw exceptions for:
- Cache connection failures
- File permission issues
- Database connection problems

Instead, it falls back to the next layer or returns the default value.

### Logging

Enable logging to track errors:

```php
'logging' => [
    'enabled' => true,
    'level' => 'error',
]
```

Error log format:
```
[2024-06-24 10:15:32] local.ERROR: CacheCascade: Error loading products from database {"error":"Connection refused","trace":"..."}
```