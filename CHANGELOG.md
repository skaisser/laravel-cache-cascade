# Changelog

All notable changes to Laravel Cache Cascade will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `forget()` method as an alias for `clearCache()` to match Laravel's Cache facade
- `rememberFor()` method with Laravel-compatible parameter order: `rememberFor(string $key, int $ttl, Closure $callback)`
- Global helper function `cache_cascade()` for convenient access
- Comprehensive usage examples in README
- Enhanced Facade docblock with complete method annotations

### Improved
- Better Laravel compatibility with familiar method names
- Enhanced developer experience with clearer documentation
- Improved IDE support through detailed docblocks

## [1.1.0] - 2024-06-24

### Added
- Full support for Laravel 12
- Laravel version badge in README

### Changed
- Updated minimum PHP requirement to 8.2 for Laravel 11/12 compatibility
- Updated PHPUnit to v10+ for better test compatibility
- Updated Orchestra Testbench to support v10 (Laravel 12)
- Improved GitHub Actions workflow with better error handling

### Fixed
- GitHub Actions test workflow now properly handles test environment
- Test paths now use app->basePath() for CI compatibility

## [1.0.0] - 2024-06-24

### Added
- Artisan commands for cache management:
  - `cache:cascade:refresh` - Refresh cache from database
  - `cache:cascade:clear` - Clear specific or all cache keys
  - `cache:cascade:stats` - Display cache statistics
- `CacheCascade::fake()` for easy testing with assertion methods
- Example test cases demonstrating testing best practices
- Integration with Laravel's `cache:clear` command
- `ConfigCacheHelper` for config:cache compatibility
- Comprehensive testing documentation
- CONTRIBUTING.md with contribution guidelines
- CHANGELOG.md for tracking changes
- SECURITY.md for security policy

### Changed
- Enhanced `clearAllCache()` to also clear file storage
- Improved documentation with Laravel integration section

## [1.0.0] - 2024-06-24

### Added
- Initial release of Laravel Cache Cascade
- Multi-layer caching with automatic fallback (Cache → File → Database → Auto-seeding)
- `CacheCascadeManager` for managing cache operations
- `CascadeInvalidation` trait for automatic cache invalidation on model events
- File-based persistent storage layer
- Visitor isolation for secure multi-tenant caching
- Auto-seeding from Laravel seeders when data not found
- Cache tagging support for Redis/Memcached
- Comprehensive test suite with 100% coverage
- GitHub Actions CI/CD workflow
- Detailed README with real-world use cases
- Configuration file with extensive options

### Features
- `get()` - Retrieve data with automatic fallback
- `set()` - Store data across all layers
- `remember()` - Cache with callback pattern
- `invalidate()` - Clear cache and file layers
- `refresh()` - Reload from database and update cache
- `clearCache()` - Clear specific cache key
- `clearAllCache()` - Clear all cascade caches

[Unreleased]: https://github.com/skaisser/laravel-cache-cascade/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/skaisser/laravel-cache-cascade/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/skaisser/laravel-cache-cascade/releases/tag/v1.0.0