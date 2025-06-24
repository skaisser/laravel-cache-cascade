# 🚀 Laravel Cache Cascade v1.0.0

<div align="center">

![Laravel Cache Cascade](https://img.shields.io/badge/Laravel-Cache%20Cascade-ff2d20?style=for-the-badge&logo=laravel&logoColor=white)
![Version](https://img.shields.io/badge/version-1.0.0-success?style=for-the-badge)
![Coverage](https://img.shields.io/badge/coverage-90.13%25-brightgreen?style=for-the-badge)
![License](https://img.shields.io/badge/license-MIT-blue?style=for-the-badge)

**Never lose your cached data again.** 🛡️

</div>

---

## 🎉 Introducing Laravel Cache Cascade

**Your app's memory just became bulletproof.**

After months of battle-testing in production, we're thrilled to announce the first stable release of Laravel Cache Cascade - a package that fundamentally changes how you think about caching in Laravel.

### 💡 Why We Built This

Every Laravel developer has experienced it: Redis crashes at 3 AM. Your cache gets flushed accidentally. The database gets hammered because all your cached data vanished. Your app grinds to a halt.

**Not anymore.**

Laravel Cache Cascade creates an intelligent, self-healing cache system that automatically falls back through multiple storage layers, ensuring your app stays fast and your data stays available - no matter what fails.

---

## 🎯 What Makes It Special

### 🏗️ **Multi-Layer Architecture**
```
Cache (Redis) → File Storage → Database → Auto-Seeder
     ↓ fails      ↓ fails       ↓ empty
   Still fast   Still works   Auto-populates
```

### ⚡ **Zero-Config Magic**
```php
// Before: Complex cache management
$data = Cache::remember('settings', 3600, function() {
    return DB::table('settings')->get();
});
// What if cache fails? What if DB is empty?

// After: Bulletproof caching
$data = CacheCascade::get('settings');
// Automatically handles failures, empty states, and recovery
```

### 🔄 **Self-Healing Cache**
```php
class Product extends Model
{
    use CascadeInvalidation; // That's it!

    // Now any update automatically refreshes cache across ALL layers
}
```

---

## ✨ Key Features

### **🛡️ Resilient by Design**
- **Automatic fallback** through multiple storage layers
- **Survives Redis crashes** with file-based persistence
- **Self-recovers** from cache flushes and failures
- **Zero downtime** during cache server maintenance

### **🚀 Performance First**
- **90% fewer database queries** for cached content
- **Request-level statistics** to monitor cache performance
- **Smart invalidation** only refreshes what changed
- **Lazy loading** with efficient memory usage

### **🔒 Enterprise Ready**
- **Visitor isolation** for secure multi-tenant apps
- **90.13% test coverage** with comprehensive test suite
- **Production tested** in high-traffic applications
- **Full documentation** with security best practices

### **👨‍💻 Developer Experience**
- **Simple API** - Just `get()`, `set()`, `remember()`, `refresh()`
- **Testing utilities** - `CacheCascade::fake()` for easy mocking
- **Artisan commands** - Refresh, clear, and monitor cache
- **Laravel native** - Works with config:cache, cache:clear, and tags

---

## 📦 Installation

```bash
composer require skaisser/laravel-cache-cascade
```

That's it! Auto-discovery handles the rest.

---

## 🎮 Quick Start

### Basic Usage
```php
use Skaisser\CacheCascade\Facades\CacheCascade;

// Get with automatic fallback
$settings = CacheCascade::get('settings');

// Remember pattern with 24-hour cache
$products = CacheCascade::remember('products', function() {
    return Product::active()->get();
}, 86400);

// Refresh from database
CacheCascade::refresh('settings');
```

### Auto-Invalidation with Models
```php
class Faq extends Model
{
    use CascadeInvalidation;
    // Updates automatically refresh cache!
}
```

### Visitor Isolation (Perfect for SaaS)
```php
// Each user gets their own secure cache
$preferences = CacheCascade::remember('preferences', function() {
    return auth()->user()->preferences;
}, 3600, true); // true = visitor isolation
```

---

## 📊 Real-World Impact

> "Reduced our database load by 85% and survived three Redis outages without any downtime." - Production user

> "Finally, a caching solution that doesn't require a PhD to implement correctly." - Laravel developer

---

## 🗺️ What's Included

- **📖 [Comprehensive Documentation](https://github.com/skaisser/laravel-cache-cascade#documentation)**
  - API Reference
  - Testing Guide
  - Security Best Practices
  - Advanced Usage Patterns
  - Troubleshooting Guide

- **🛠️ Artisan Commands**
  ```bash
  php artisan cache:cascade:refresh {key}  # Refresh specific cache
  php artisan cache:cascade:clear {key}    # Clear specific cache
  php artisan cache:cascade:stats {key?}   # View cache statistics
  ```

- **🧪 Testing Utilities**
  ```php
  $fake = CacheCascade::fake();
  // Your tests...
  $fake->assertCalled('get', ['settings']);
  $fake->assertHas('products');
  ```

---

## 🏆 Perfect For

- **🏢 SaaS Applications** - Tenant-specific caching with visitor isolation
- **📰 Content Management** - Auto-invalidation on content updates
- **🛒 E-commerce** - Resilient product catalogs and settings
- **🌐 APIs** - Reduce database load for high-traffic endpoints
- **🎛️ Feature Flags** - Always-available configuration

---

## 🙏 Acknowledgments

A huge thank you to the Laravel community for the feedback, testing, and contributions that made this release possible. Special thanks to everyone who battle-tested early versions in production.

---

## 🚦 Getting Started

1. **[Read the Documentation](https://github.com/skaisser/laravel-cache-cascade)**
2. **[View Examples](https://github.com/skaisser/laravel-cache-cascade/tree/main/tests/Examples)**
3. **[Report Issues](https://github.com/skaisser/laravel-cache-cascade/issues)**
4. **[Contribute](https://github.com/skaisser/laravel-cache-cascade/blob/main/CONTRIBUTING.md)**

---

<div align="center">

**Ready to make your Laravel app's cache bulletproof?**

[**Get Started →**](https://github.com/skaisser/laravel-cache-cascade)

---

Built with ❤️ by [Shirleyson Kaisser](https://github.com/skaisser)

</div>
