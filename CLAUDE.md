# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Laravel Cache Cascade Package

A Laravel package that provides bulletproof caching with automatic fallback through multiple storage layers (Cache → File → Database → Auto-seeding). This ensures critical application data remains available even when primary cache systems fail.

## Common Development Commands

### Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Feature

# Run with coverage
vendor/bin/phpunit --coverage-html build/coverage

# Run specific test
vendor/bin/phpunit --filter testGetWithFallbackChain
```

### Development
```bash
# Install dependencies
composer install

# Update dependencies for specific Laravel version
composer require "laravel/framework:11.*" "orchestra/testbench:9.*" --no-update
composer update

# Create test directories (required before running tests)
mkdir -p tests/fixtures/dynamic
mkdir -p build/logs
```

## Package Architecture

### Core Service: CacheCascadeManager
Located at `src/Services/CacheCascadeManager.php`, this is the heart of the package implementing:
- Multi-layer fallback chain (cache → file → database → seeder)
- Visitor isolation for multi-tenant scenarios
- Request-level caching to minimize redundant operations
- Statistics tracking for performance monitoring
- Automatic model detection from cache keys (e.g., 'faqs' → `App\Models\Faq`)

### Key Components

1. **CascadeInvalidation Trait** (`src/Traits/CascadeInvalidation.php`)
   - Add to Eloquent models for automatic cache invalidation
   - Implements `getCascadeCacheKey()` method to define the cache key
   - Hooks into model events (created, updated, deleted, restored)

2. **Console Commands** (`src/Console/Commands/`)
   - `cache:cascade:refresh {key}` - Refresh cache from database
   - `cache:cascade:clear {key}` - Clear all layers for a key
   - `cache:cascade:stats` - Display runtime statistics

3. **Testing Support** (`src/Testing/CacheCascadeFake.php`)
   - Fake implementation for unit tests
   - Assertion methods: `assertCached()`, `assertNotCached()`, `assertCacheMissed()`

### Storage Layers

1. **Cache Layer**: Primary fast storage (Redis/Memcached)
   - Configurable TTL via `default_ttl` config
   - Optional tagging support
   - Automatic visitor isolation when enabled

2. **File Layer**: Persistent storage in `config/dynamic/` directory
   - Survives cache server restarts
   - PHP array format for easy debugging
   - Automatically created/updated on cache writes

3. **Database Layer**: Loads from Eloquent models
   - Automatic model detection: 'settings' → `App\Models\Setting`
   - Support for custom scopes via `scopeForCascadeCache($query)`
   - Falls back to singular form if plural model not found

4. **Auto-seeding**: Runs seeders when no data exists
   - Looks for seeder matching the cache key
   - Example: 'faqs' → `FaqSeeder` or `FaqsSeeder`

### Model Integration Pattern

```php
class Faq extends Model
{
    use CascadeInvalidation;
    
    public function getCascadeCacheKey(): ?string
    {
        return 'faqs'; // This model's data will be cached under 'faqs' key
    }
    
    // Optional: Custom query for cascade loading
    public function scopeForCascadeCache($query)
    {
        return $query->orderBy('order')->where('active', true);
    }
}
```

## Testing Approach

- **Unit Tests**: Test individual components in isolation
- **Feature Tests**: Test full cascade behavior with database
- **Test Database**: SQLite in-memory (`:memory:`)
- **Test Cache**: Array driver for predictable behavior
- **Coverage Goal**: Maintain >90% code coverage

## Important Implementation Notes

1. **Cache Key Resolution**: The package intelligently maps cache keys to models:
   - Tries plural form first: 'faqs' → `Faq` model
   - Falls back to singular: 'setting' → `Setting` model
   - Supports namespaced models via config

2. **Visitor Isolation**: When enabled, appends visitor ID to cache keys
   - Uses session ID or cookie-based identifier
   - Prevents data leakage between users/tenants
   - Enable via `visitor_isolation` config option

3. **Request-Level Caching**: Data is cached in memory during request
   - Prevents multiple reads of same key
   - Automatically cleared between requests
   - Improves performance for repeated access

4. **File Storage Format**: Files are stored as PHP arrays
   - Located in `config/dynamic/{key}.php`
   - Returns PHP array when included
   - Easy to debug and manually edit if needed

5. **Automatic Invalidation**: When using CascadeInvalidation trait
   - Model changes trigger cache refresh
   - Refreshes both cache and file layers
   - Maintains data consistency across layers

## Common Patterns

### Adding Cache Cascade to Existing Models
1. Add the `CascadeInvalidation` trait
2. Implement `getCascadeCacheKey()` method
3. Optionally add `scopeForCascadeCache()` for custom queries

### Creating New Cached Data Types
1. Create model with CascadeInvalidation trait
2. Define cache key in `getCascadeCacheKey()`
3. Create seeder for initial data (optional)
4. Access via `CacheCascade::get('key')`

### Testing Cache Behavior
1. Use `CacheCascade::fake()` in test setup
2. Perform operations
3. Assert with `CacheCascade::assertCached('key')`

## Package Testing Matrix

The package is tested against:
- PHP: 8.2, 8.3
- Laravel: 10.x, 11.x, 12.x
- Test coverage uploaded to Codecov for Laravel 11.x on PHP 8.3