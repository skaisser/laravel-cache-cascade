# Laravel Cache Cascade

[![Latest Version on Packagist](https://img.shields.io/packagist/v/skaisser/laravel-cache-cascade.svg?style=flat-square)](https://packagist.org/packages/skaisser/laravel-cache-cascade)
[![Tests](https://github.com/skaisser/laravel-cache-cascade/actions/workflows/tests.yml/badge.svg)](https://github.com/skaisser/laravel-cache-cascade/actions/workflows/tests.yml)
[![Code Coverage](https://codecov.io/gh/skaisser/laravel-cache-cascade/branch/main/graph/badge.svg)](https://codecov.io/gh/skaisser/laravel-cache-cascade)
[![Total Downloads](https://img.shields.io/packagist/dt/skaisser/laravel-cache-cascade.svg?style=flat-square)](https://packagist.org/packages/skaisser/laravel-cache-cascade)
[![PHP Version](https://img.shields.io/packagist/php-v/skaisser/laravel-cache-cascade.svg?style=flat-square)](https://packagist.org/packages/skaisser/laravel-cache-cascade)
[![GitHub Stars](https://img.shields.io/github/stars/skaisser/laravel-cache-cascade.svg?style=social)](https://github.com/skaisser/laravel-cache-cascade)

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

// Clear specific cache
CacheCascade::clearCache('settings');
```

### Remember Pattern

```php
// Cache data with a callback
$users = CacheCascade::remember('active-users', function() {
    return User::where('active', true)->get();
}, 3600); // Cache for 1 hour
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

### Custom Transformations

Apply transformations to cached data:

```php
$products = CacheCascade::get('products', [], [
    'transform' => function($data) {
        return collect($data)
            ->map(fn($item) => new ProductDTO($item))
            ->filter(fn($product) => $product->isActive());
    }
]);
```

### Skip Database Layer

For file-only caching:

```php
CacheCascade::set('config', $data, true); // Skip database
```

### Custom Model Namespace

Configure custom model namespace in config:

```php
'model_namespace' => 'App\\Domain\\Models\\',
```

### Cache Tags (Redis/Memcached only)

```php
// Enable tags in config
'use_tags' => true,
'cache_tag' => 'my-app-cache',

// Clear all cascade caches
CacheCascade::clearAllCache();
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

The package has comprehensive test coverage including:
- Unit tests for all manager methods
- Trait functionality tests
- Integration tests for complete flows
- Error handling and edge cases

## Security

- Visitor isolation prevents cache poisoning and data leaks
- File permissions should be properly configured
- Use encryption for sensitive cached data

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.