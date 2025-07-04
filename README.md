# Laravel Cache Cascade

[![Tests](https://github.com/skaisser/laravel-cache-cascade/actions/workflows/tests.yml/badge.svg)](https://github.com/skaisser/laravel-cache-cascade/actions/workflows/tests.yml)
[![Code Coverage](https://img.shields.io/badge/coverage-90.13%25-brightgreen.svg)](https://github.com/skaisser/laravel-cache-cascade)
[![Laravel](https://img.shields.io/badge/Laravel-10.x%20|%2011.x%20|%2012.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)

**Never lose your cached data again.** Laravel Cache Cascade provides bulletproof caching with automatic fallback through multiple storage layers. When Redis goes down, your app keeps running. When files get corrupted, data loads from the database. When the database is empty, seeders run automatically.

🚀 **Perfect for**: SaaS settings, CMS content, API responses, feature flags, and any rarely-changing data that must always be available.

## Why This Package Exists

Ever had Redis crash and take your app down because all your cached settings disappeared? Or struggled with cache invalidation when your database updates? Laravel's built-in cache is great, but it has limitations:

- **Single point of failure** - When your cache driver fails, your app fails
- **No automatic persistence** - Cache expires and you have to rebuild from scratch
- **Manual invalidation** - Database changes don't automatically update the cache
- **No built-in fallback** - You need to write try-catch blocks everywhere

**Laravel Cache Cascade solves these problems** by creating a resilient caching system that automatically falls back through multiple storage layers and keeps them in sync.

## Laravel Cache vs Cache Cascade

| Feature | Laravel Cache | Cache Cascade |
|---------|--------------|---------------|
| **Fallback Mechanism** | ❌ None | ✅ Cache → File → Database → Seeder |
| **Automatic Invalidation** | ❌ Manual | ✅ Model observers auto-refresh |
| **Persistent Storage** | ❌ Memory only | ✅ File + Memory |
| **Database Sync** | ❌ Manual | ✅ Automatic on update |
| **Visitor Isolation** | ❌ Not built-in | ✅ Optional per-key |
| **Auto-seeding** | ❌ Manual | ✅ Runs seeders automatically |
| **Zero-config Models** | ❌ Requires setup | ✅ Just add trait |

## Features

- **🏗️ Multi-layer Caching**: Automatic fallback chain (Cache → File → Database → Auto-seeding)
- **🔒 Visitor Isolation**: Optional visitor-specific cache keys for enhanced security
- **🌱 Auto-seeding**: Automatically seed data from seeders when not found
- **📁 File Storage**: Persistent file-based caching layer
- **🔄 Flexible Configuration**: Customize fallback order and behavior
- **🏷️ Cache Tagging**: Support for tagged cache operations
- **⚡ High Performance**: Request-level caching to minimize database queries
- **♻️ Automatic Invalidation**: Database changes automatically refresh cache and file layers
- **🎯 Model Integration**: Trait for automatic cache management in Eloquent models

## Installation

Install the package via Composer:

```bash
composer require skaisser/laravel-cache-cascade
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=cache-cascade-config
```

This will create a `config/cache-cascade.php` file where you can customize the package settings.

### Key Configuration Options

```php
return [
    // Path for file-based cache storage
    'config_path' => 'config/dynamic',
    
    // Cache settings
    'cache_prefix' => 'cascade:',
    'default_ttl' => 86400, // 24 hours
    
    // Enable visitor-specific caching
    'visitor_isolation' => false,
    
    // Database integration
    'use_database' => true,
    'auto_seed' => true,
    
    // Define fallback order
    'fallback_chain' => ['cache', 'file', 'database'],
];
```

## Usage

### Basic Usage

```php
use Skaisser\CacheCascade\Facades\CacheCascade;

// Get data with automatic fallback
$data = CacheCascade::get('settings', []);

// Get with custom options
$faqs = CacheCascade::get('faqs', [], [
    'ttl' => 3600, // 1 hour
    'transform' => fn($data) => collect($data)->sortBy('order')
]);

// Set data (updates all layers)
CacheCascade::set('settings', $data);

// Clear specific cache (both methods work)
CacheCascade::clearCache('settings');
CacheCascade::forget('settings'); // Laravel-style alias

// Using the global helper
$settings = cache_cascade('settings', []); // Get with default
$value = cache_cascade('key', function() {  // Remember pattern
    return expensive_operation();
});
```

### Remember Pattern

```php
// Cache data with a callback (original method)
$users = CacheCascade::remember('active-users', function() {
    return User::where('active', true)->get();
}, 3600); // Cache for 1 hour

// Laravel-compatible signature with rememberFor()
$posts = CacheCascade::rememberFor('recent-posts', 3600, function() {
    return Post::recent()->limit(10)->get();
});

// Using the helper function
$data = cache_cascade('expensive-data', function() {
    return expensive_computation();
});
```

### Visitor Isolation

Enable visitor-specific caching to prevent data leakage between users:

```php
// Enable for specific cache
$userData = CacheCascade::get('user-settings', [], [
    'visitor_isolation' => true
]);

// Or use remember with isolation
$userDashboard = CacheCascade::remember('dashboard', function() {
    return $this->generateDashboard();
}, 3600, true); // Last parameter enables visitor isolation
```

### Working with Models

The package can automatically load data from Eloquent models and seed if empty:

```php
// If 'faqs' table is empty, it will run FaqSeeder automatically
$faqs = CacheCascade::get('faqs');

// The package will look for:
// 1. Cache key 'cascade:faqs'
// 2. File at 'config/dynamic/faqs.php'
// 3. App\Models\Faq::orderBy('order')->get()
// 4. Database\Seeders\FaqSeeder (if auto_seed is enabled)
```

## How It Works

### Fallback Chain

When you request data, the package tries each storage layer in order:

1. **Cache Layer**: Fast in-memory storage (Redis/Memcached)
2. **File Layer**: Persistent file storage for rarely-changing data
3. **Database Layer**: Load from Eloquent models
4. **Auto-seeding**: Run seeders if no data exists

### File Storage Format

Files are stored in PHP format by default:

```php
// config/dynamic/settings.php
<?php return [
    'data' => [
        'site_name' => 'My App',
        'maintenance_mode' => false,
    ]
];
```

### Automatic Model Detection

The package intelligently detects models based on the cache key:

- `'faqs'` → `App\Models\Faq`
- `'settings'` → `App\Models\Setting`
- `'categories'` → `App\Models\Category`

## Cache Invalidation

One of the most important features is automatic cache invalidation when database data changes. The package provides multiple ways to handle this:

### Manual Invalidation

```php
use Skaisser\CacheCascade\Facades\CacheCascade;

// Invalidate all cache layers (cache + file)
CacheCascade::invalidate('settings');

// Refresh from database and update all layers
$freshData = CacheCascade::refresh('settings');
```

### Automatic Model Invalidation

Use the `CascadeInvalidation` trait in your Eloquent models for automatic cache invalidation:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Skaisser\CacheCascade\Traits\CascadeInvalidation;

class Faq extends Model
{
    use CascadeInvalidation;
    
    protected $fillable = ['question', 'answer', 'order'];
    
    // Optional: Customize the cache key (defaults to table name)
    public function getCascadeCacheKey(): ?string
    {
        return 'faqs'; // This will be the cache key
    }
    
    // Optional: Customize what data gets cached
    public function scopeForCascadeCache($query)
    {
        return $query->where('active', true)->orderBy('order');
    }
}
```

Now when you update the model, the cache is automatically refreshed:

```php
// This will automatically invalidate cache and file, then refresh from database
$faq = Faq::find(1);
$faq->update(['answer' => 'Updated answer']);

// Cache has been automatically refreshed!
$cachedFaqs = CacheCascade::get('faqs'); // Fresh data from database
```

### Invalidation Events

The trait automatically invalidates cache on:
- Model creation (`created`)
- Model updates (`updated`)
- Model deletion (`deleted`)
- Model restoration (`restored` - for soft deletes)

### Manual Model Refresh

```php
// Manually refresh cache for a model
$faq = Faq::first();
$faq->refreshCascadeCache();
```

## Advanced Usage

For advanced features like custom storage drivers, performance optimization, multi-tenant support, and more, see the [Advanced Usage Guide](docs/ADVANCED.md).

### Quick Examples

**Custom Transformations**
```php
$products = CacheCascade::get('products', [], [
    'transform' => function($data) {
        return collect($data)
            ->map(fn($item) => new ProductDTO($item))
            ->filter(fn($product) => $product->isActive());
    }
]);
```

**Skip Database Layer**
```php
CacheCascade::set('config', $data, true); // Skip database
```

**Cache Tags (Redis/Memcached only)**
```php
// Enable tags in config
'use_tags' => true,
'cache_tag' => 'my-app-cache',

// Clear all cascade caches
CacheCascade::clearAllCache();
```

## Comprehensive Examples

### Real-World Usage Patterns

**E-commerce Settings**
```php
use Skaisser\CacheCascade\Facades\CacheCascade;

// Get store configuration with fallback
$storeConfig = CacheCascade::get('store-config', [
    'currency' => 'USD',
    'tax_rate' => 0.08
]);

// Remember computed values with Laravel-compatible syntax
$shippingRates = CacheCascade::rememberFor('shipping-rates', 3600, function() {
    return ShippingProvider::calculateRates();
});

// Clear cache when admin updates settings
CacheCascade::forget('store-config');
CacheCascade::forget('shipping-rates');
```

**Multi-tenant SaaS Application**
```php
// Enable visitor isolation for tenant-specific data
$tenantSettings = CacheCascade::remember('tenant-settings', function() {
    return Tenant::current()->settings;
}, 86400, true); // true enables visitor isolation

// Or use the global helper
$features = cache_cascade('tenant-features', function() {
    return Feature::forTenant(tenant())->get();
});
```

**CMS Content Management**
```php
// Model with automatic cache invalidation
class Page extends Model
{
    use CascadeInvalidation;
    
    public function getCascadeCacheKey(): ?string
    {
        return 'pages';
    }
}

// Usage in controllers
$pages = CacheCascade::rememberFor('pages', 7200, function() {
    return Page::published()->with('author')->get();
});

// When a page is updated, cache automatically refreshes!
$page->update(['title' => 'New Title']); // Triggers cache invalidation
```

**API Response Caching**
```php
// Cache API responses with transformation
$apiData = CacheCascade::get('weather-data', [], [
    'ttl' => 1800, // 30 minutes
    'transform' => function($data) {
        return collect($data)->map(function($item) {
            return [
                'temp' => $item['temperature'],
                'desc' => $item['description'],
                'icon' => "weather-{$item['code']}.svg"
            ];
        });
    }
]);

// Refresh from external API
Route::post('/admin/refresh-weather', function() {
    $freshData = WeatherAPI::fetch();
    CacheCascade::set('weather-data', $freshData);
    CacheCascade::invalidate('weather-widget'); // Clear dependent caches
});
```

**Feature Flags & Configuration**
```php
// Define feature flags that must always load
$features = CacheCascade::remember('feature-flags', function() {
    return FeatureFlag::all()->pluck('enabled', 'name');
}, 86400);

if ($features['new-checkout-flow'] ?? false) {
    return view('checkout.new');
}

// Admin panel update
Route::post('/admin/features/{flag}/toggle', function($flag) {
    FeatureFlag::where('name', $flag)->toggle('enabled');
    CacheCascade::refresh('feature-flags'); // Immediate update
});
```

## Laravel Integration

### Artisan Commands

The package provides several Artisan commands:

```bash
# Refresh cache from database
php artisan cache:cascade:refresh {key}

# Clear specific or all cascade cache
php artisan cache:cascade:clear {key}
php artisan cache:cascade:clear --all

# Show cache statistics
php artisan cache:cascade:stats
php artisan cache:cascade:stats {key}
```

### Integration with Laravel Commands

#### cache:clear

By default, running `php artisan cache:clear` will also clear all cascade cache. You can disable this:

```php
// config/cache-cascade.php
'clear_on_cache_clear' => false,
```

#### config:cache

Dynamic cascade files are excluded from `config:cache` by default. If you need to check which files would be cached:

```php
use Skaisser\CacheCascade\Helpers\ConfigCacheHelper;

// Get all static config files (excluding cascade files)
$staticFiles = ConfigCacheHelper::getStaticConfigFiles();

// Get all cascade cache keys from file storage
$cascadeKeys = ConfigCacheHelper::getFileStorageKeys();
```

### Logging & Debugging

Enable detailed logging to debug cache behavior:

```php
// .env
CACHE_CASCADE_LOG=true
CACHE_CASCADE_LOG_CHANNEL=daily
CACHE_CASCADE_LOG_LEVEL=debug
```

Or configure in the config file:

```php
// config/cache-cascade.php
'logging' => [
    'enabled' => true,
    'channel' => 'daily',
    'level' => 'debug',
    'log_hits' => true,   // Log cache/file/database hits
    'log_misses' => true, // Log cache misses
    'log_writes' => true, // Log write operations
],
```

View runtime statistics:

```bash
php artisan cache:cascade:stats
```

Example log output:
```
[2024-06-24 10:15:32] local.DEBUG: CacheCascade: Cache hit for key: settings {"layer":"cache","key":"settings"}
[2024-06-24 10:15:45] local.DEBUG: CacheCascade: File hit for key: faqs {"layer":"file","key":"faqs"}
[2024-06-24 10:16:03] local.INFO: CacheCascade: Cache miss for key: new_feature {"key":"new_feature","default_used":true}
```

## Real-World Use Cases

### 🏢 SaaS Applications
**Problem**: Your app stores tenant settings, feature flags, and subscription plans in cache. When Redis restarts, all tenants experience errors.

**Solution**: Cache Cascade ensures settings persist in files and auto-reload from database:
```php
// Tenant settings always available, even if Redis is down
$settings = CacheCascade::get("tenant:{$tenantId}:settings");
```

### 📰 Content Management Systems
**Problem**: Your CMS caches articles, menus, and widgets. Cache invalidation is a nightmare when editors update content.

**Solution**: Use the trait for automatic invalidation:
```php
class Article extends Model
{
    use CascadeInvalidation;
    
    // Article updates automatically refresh the cache
}
```

### 🌐 API Gateway / Microservices
**Problem**: You cache API responses but need fallback when the cache server is unreachable.

**Solution**: Cache with file backup for critical endpoints:
```php
$products = CacheCascade::remember('products:list', function() {
    return Http::get('https://api.example.com/products')->json();
}, 3600);
```

### 🎛️ Feature Flags & Configuration
**Problem**: Feature flags must always be available but can change dynamically.

**Solution**: Database-backed cache with instant updates:
```php
class FeatureFlag extends Model
{
    use CascadeInvalidation;
    
    // Flags update instantly across all servers
}
```

### 🏪 E-commerce Settings
**Problem**: Payment gateways, shipping rates, and tax rules must never fail to load.

**Solution**: Multi-layer protection with auto-seeding:
```php
// Even on fresh deployments, settings are auto-seeded
$shippingRates = CacheCascade::get('shipping:rates');
```

## Testing

### Testing Your Code with CacheCascade::fake()

The package provides a powerful fake implementation for easy testing:

```php
use Skaisser\CacheCascade\Facades\CacheCascade;

public function test_my_service()
{
    // Replace with fake for testing
    $fake = CacheCascade::fake();
    
    // Your code that uses CacheCascade
    $service = new MyService();
    $service->cacheSettings();
    
    // Assert cache interactions
    $fake->assertCalled('set', ['settings', $data, false]);
    $fake->assertHas('settings');
    
    // Verify call counts
    $this->assertEquals(1, $fake->calledCount('set'));
}
```

Available test assertions:
- `assertCalled($method, $arguments)` - Verify method was called
- `assertNotCalled($method)` - Verify method wasn't called
- `assertHas($key)` - Check if cache has key
- `assertMissing($key)` - Check if cache doesn't have key
- `calledCount($method)` - Get number of calls
- `reset()` - Clear fake data between tests

See [Testing Documentation](docs/TESTING.md) for comprehensive examples.

### Running Package Tests

Run the test suite:

```bash
composer test
```

Run tests with code coverage:

```bash
composer test -- --coverage-html coverage
```

### Test Coverage

The package maintains **90.13% code coverage** with comprehensive tests for:
- ✅ Core CacheCascadeManager functionality
- ✅ Console commands (refresh, clear, stats)
- ✅ Model trait integration
- ✅ Facade implementation
- ✅ Testing utilities (CacheCascadeFake)
- ✅ Helper classes
- ✅ Error handling and edge cases
- ✅ Visitor isolation
- ✅ Auto-seeding functionality

## Performance

### Benchmarks

Cache Cascade adds minimal overhead while providing maximum reliability:

| Operation | Native Cache | Cache Cascade | Overhead |
|-----------|-------------|---------------|----------|
| Cache Hit | 0.02ms | 0.03ms | +50% |
| Cache Miss (File Hit) | 5ms | 0.5ms | -90% |
| Cache Miss (DB Hit) | 5ms | 5.2ms | +4% |
| Write Operation | 0.1ms | 0.8ms | +700%* |

*Write operations update all layers for reliability

### Optimization Tips

1. **Use appropriate TTLs** - Longer TTLs reduce database hits
2. **Enable visitor isolation selectively** - Only for user-specific data
3. **Use file storage for rarely-changing data** - Config, settings, etc.
4. **Batch operations when possible** - Reduce write overhead

## Security

- **Visitor Isolation**: Prevents cache poisoning and data leaks between users
- **File Permissions**: Ensure proper permissions (755) on cache directories
- **Sensitive Data**: Consider encryption for sensitive cached data
- **Input Validation**: Cache keys are sanitized to prevent directory traversal

See [Security Best Practices](docs/SECURITY.md) for detailed guidelines.

## Documentation

- 📖 [API Reference](docs/API.md) - Complete method documentation
- 🧪 [Testing Guide](docs/TESTING.md) - Testing strategies and examples
- 🔒 [Security Best Practices](docs/SECURITY.md) - Security considerations
- 🚀 [Advanced Usage](docs/ADVANCED.md) - Performance optimization and advanced patterns
- 🔧 [Troubleshooting](docs/TROUBLESHOOTING.md) - Common issues and solutions

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Contributions are welcome! Please see [Contributing Guide](CONTRIBUTING.md) for details.

## Credits

- [Shirleyson Kaisser](https://github.com/skaisser)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.