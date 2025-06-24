# Troubleshooting Guide

This guide helps you diagnose and fix common issues with Laravel Cache Cascade.

## Common Issues

### Cache Not Updating

**Symptom:** Database changes aren't reflected in cached data

**Possible Causes:**

1. **Model missing CascadeInvalidation trait**
   ```php
   // ❌ Without trait
   class Setting extends Model
   {
       // Cache won't auto-invalidate
   }
   
   // ✅ With trait
   class Setting extends Model
   {
       use \Skaisser\CacheCascade\Traits\CascadeInvalidation;
   }
   ```

2. **Custom cache key not matching**
   ```php
   // If your cache key is 'app_settings' but model returns 'settings'
   public function getCascadeCacheKey(): string
   {
       return 'app_settings'; // Must match your CacheCascade::get() key
   }
   ```

3. **Cache driver doesn't support tags**
   ```php
   // Check your cache driver
   if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
       // Tags supported
   } else {
       // Switch to Redis or disable tags
       config(['cache-cascade.use_tags' => false]);
   }
   ```

### File Storage Not Working

**Symptom:** Files aren't being created in config/dynamic

**Solutions:**

1. **Check directory permissions**
   ```bash
   # Fix permissions
   chmod -R 755 config/dynamic
   chown -R www-data:www-data config/dynamic
   ```

2. **Verify path configuration**
   ```php
   // Check the path exists
   $path = base_path(config('cache-cascade.config_path'));
   if (!file_exists($path)) {
       mkdir($path, 0755, true);
   }
   ```

3. **Ensure skip_database is false**
   ```php
   // ❌ This skips file storage
   CacheCascade::set('key', $data, true);
   
   // ✅ This updates all layers
   CacheCascade::set('key', $data, false);
   ```

### Auto-seeding Not Working

**Symptom:** Empty database doesn't trigger seeders

**Debugging Steps:**

1. **Check auto_seed is enabled**
   ```php
   // config/cache-cascade.php
   'auto_seed' => true,
   ```

2. **Verify seeder exists**
   ```php
   // For key 'faqs', needs Database\Seeders\FaqSeeder
   $seederClass = 'Database\\Seeders\\' . Str::studly(Str::singular($key)) . 'Seeder';
   
   if (!class_exists($seederClass)) {
       // Create the seeder
   }
   ```

3. **Check seeder namespace**
   ```php
   // If using custom namespace
   'seeder_namespace' => 'App\\Database\\Seeders\\',
   ```

### Visitor Isolation Issues

**Symptom:** Users seeing each other's cached data or cache always missing

**Solutions:**

1. **Ensure session is started**
   ```php
   // In middleware or service provider
   if (!session()->isStarted()) {
       session()->start();
   }
   ```

2. **Consistent isolation usage**
   ```php
   // ❌ Inconsistent
   CacheCascade::set('user.data', $data); // No isolation
   CacheCascade::get('user.data', [], ['visitor_isolation' => true]); // With isolation
   
   // ✅ Consistent
   CacheCascade::remember('user.data', fn() => $data, 3600, true); // Both use isolation
   ```

3. **Check lead-persistence binding**
   ```php
   // If using lead-persistence package
   if (!app()->bound('lead-persistence')) {
       // Lead persistence not configured
   }
   ```

### Performance Issues

**Symptom:** Slow response times or high memory usage

**Optimizations:**

1. **Increase cache TTL**
   ```php
   // Longer TTL = fewer database hits
   CacheCascade::remember('static.data', fn() => $data, 86400); // 24 hours
   ```

2. **Use selective fields**
   ```php
   public function scopeForCascadeCache($query)
   {
       return $query->select(['id', 'name', 'value']) // Only needed fields
                    ->where('active', true);
   }
   ```

3. **Disable unnecessary features**
   ```php
   // If not using visitor isolation
   'visitor_isolation' => false,
   
   // If not using file layer
   'fallback_chain' => ['cache', 'database'],
   ```

### Memory Cache Not Working

**Symptom:** Every request hits the database

**Checks:**

1. **Verify cache driver**
   ```bash
   php artisan tinker
   >>> config('cache.default')
   >>> Cache::put('test', 'value', 60)
   >>> Cache::get('test')
   ```

2. **Check Redis connection**
   ```bash
   redis-cli ping
   # Should return PONG
   ```

3. **Clear corrupted cache**
   ```bash
   php artisan cache:clear
   php artisan cache:cascade:clear --all
   ```

## Debugging Tools

### Enable Debug Logging

```php
// .env
CACHE_CASCADE_LOG=true
CACHE_CASCADE_LOG_LEVEL=debug

// View logs
tail -f storage/logs/laravel.log | grep CacheCascade
```

### Check Cache Statistics

```bash
# General stats
php artisan cache:cascade:stats

# Specific key stats
php artisan cache:cascade:stats users
```

### Trace Cache Operations

```php
// Temporary debug helper
CacheCascade::macro('debug', function($key) {
    $manager = app('cache-cascade');
    
    dump([
        'cache_hit' => Cache::has("cascade:{$key}"),
        'file_exists' => file_exists(base_path("config/dynamic/{$key}.php")),
        'in_database' => \DB::table(Str::plural($key))->exists(),
    ]);
    
    return $manager->get($key);
});

// Usage
CacheCascade::debug('settings');
```

### Monitor Performance

```php
// Add to AppServiceProvider
use Illuminate\Support\Facades\Event;

Event::listen('cache.cascade.*', function ($event, $data) {
    \Log::debug('CacheCascade Event', [
        'event' => $event,
        'data' => $data,
        'memory' => memory_get_usage(true) / 1024 / 1024 . ' MB',
        'time' => microtime(true) - LARAVEL_START,
    ]);
});
```

## Error Messages

### "No data found in database"

**Meaning:** The database query returned empty results

**Solutions:**
- Check if table exists and has data
- Verify model namespace configuration
- Ensure database connection is correct
- Check if auto-seeding should run

### "Cache tags not supported"

**Meaning:** Your cache driver doesn't support tagging

**Solutions:**
- Switch to Redis or Memcached
- Disable tags: `'use_tags' => false`
- Use array driver for testing only

### "Unable to clear all visitor caches"

**Meaning:** Without tags, we can't clear visitor-isolated caches

**Solutions:**
- Enable cache tags with Redis
- Manually clear specific visitor caches
- Use shorter TTLs for visitor-isolated data

## Testing Issues

### Fake Not Working in Tests

```php
// ❌ Wrong
public function test_something()
{
    CacheCascade::fake(); // Returns fake but doesn't replace facade
}

// ✅ Correct
public function test_something()
{
    $fake = CacheCascade::fake(); // Use returned fake instance
    
    // Your test
    
    $fake->assertCalled('get');
}
```

### Tests Affecting Each Other

```php
protected function setUp(): void
{
    parent::setUp();
    
    // Clear everything between tests
    Cache::flush();
    File::deleteDirectory(base_path('config/dynamic'));
    
    $this->fake = CacheCascade::fake();
}
```

## Getting Help

If you're still experiencing issues:

1. **Check the logs** - Enable debug logging and check Laravel logs
2. **Minimal reproduction** - Create a simple test case
3. **GitHub Issues** - Search existing issues or create new one
4. **Stack Overflow** - Tag with `laravel-cache-cascade`

### Information to Include

When reporting issues, include:

- Laravel version
- PHP version  
- Cache driver (Redis, Memcached, etc.)
- Cache Cascade version
- Relevant configuration
- Error messages and stack traces
- Steps to reproduce

## Quick Fixes

```bash
# Clear everything and start fresh
php artisan cache:clear
php artisan cache:cascade:clear --all
rm -rf config/dynamic/*
php artisan config:clear

# Verify installation
composer show skaisser/laravel-cache-cascade

# Re-publish config
php artisan vendor:publish --tag=cache-cascade-config --force

# Test basic functionality
php artisan tinker
>>> CacheCascade::set('test', 'works')
>>> CacheCascade::get('test')
```