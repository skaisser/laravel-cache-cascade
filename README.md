# Laravel Cache Cascade

A sophisticated multi-layer caching solution for Laravel with automatic fallback mechanisms, visitor isolation, and database seeding support. This package provides a robust caching system that falls back through multiple storage layers to ensure data availability.

## Features

- **🏗️ Multi-layer Caching**: Automatic fallback chain (Cache → File → Database → Auto-seeding)
- **🔒 Visitor Isolation**: Optional visitor-specific cache keys for enhanced security
- **🌱 Auto-seeding**: Automatically seed data from seeders when not found
- **📁 File Storage**: Persistent file-based caching layer
- **🔄 Flexible Configuration**: Customize fallback order and behavior
- **🏷️ Cache Tagging**: Support for tagged cache operations
- **⚡ High Performance**: Request-level caching to minimize database queries

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

## Use Cases

1. **Configuration Management**: Store app settings with file persistence
2. **FAQ Systems**: Cache FAQ data with automatic database seeding
3. **Feature Flags**: Multi-layer storage for feature toggles
4. **Localization**: Cache translation strings with fallback
5. **Dynamic Content**: Cache CMS content with visitor isolation
6. **API Responses**: Cache external API data with file backup

## Testing

```bash
composer test
```

## Security

- Visitor isolation prevents cache poisoning and data leaks
- File permissions should be properly configured
- Use encryption for sensitive cached data

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.