# Security Best Practices

This guide covers security considerations when using Laravel Cache Cascade in production applications.

## Visitor Isolation

### Preventing Cache Poisoning

Cache poisoning occurs when one user can manipulate cached data that affects other users. Laravel Cache Cascade provides visitor isolation to prevent this:

```php
// Enable visitor isolation for user-specific data
$userData = CacheCascade::remember('user.preferences', function() {
    return auth()->user()->preferences;
}, 3600, true); // true enables visitor isolation
```

### When to Use Visitor Isolation

 **Use for:**
- User preferences and settings
- Shopping cart data
- Personalized content
- API tokens or temporary credentials
- Any user-specific data

L **Don't use for:**
- Public content (articles, products)
- Application settings
- Shared reference data
- Static assets

### How Visitor Isolation Works

When enabled, cache keys are automatically suffixed with a hashed visitor identifier:

```
Regular key: cascade:user.preferences
Isolated key: cascade:user.preferences:a1b2c3d4e5f6...
```

The visitor ID is derived from:
1. Lead persistence system (if available)
2. Laravel session ID (fallback)

## File Storage Security

### Directory Permissions

Ensure proper permissions on cache directories:

```bash
# Recommended permissions
chmod 755 storage/app/cache-cascade
chmod 755 config/dynamic

# Files should be readable by web server only
chmod 644 config/dynamic/*.php
```

### Location Considerations

```php
// L BAD: Publicly accessible
'config_path' => 'public/cache',

//  GOOD: Outside document root
'config_path' => 'config/dynamic',
'config_path' => 'storage/app/cache-cascade',
```

### Sensitive Data

For sensitive data, consider encryption:

```php
use Illuminate\Support\Facades\Crypt;

// Encrypt before caching
CacheCascade::set('api.keys', Crypt::encryptString($apiKeys));

// Decrypt after retrieval
$apiKeys = Crypt::decryptString(CacheCascade::get('api.keys'));
```

## Input Validation

### Cache Key Sanitization

Cache keys are automatically sanitized to prevent directory traversal:

```php
// These are automatically sanitized
CacheCascade::get('../../../etc/passwd'); // Becomes 'etcpasswd'
CacheCascade::get('../../sensitive'); // Becomes 'sensitive'
```

### Custom validation for critical keys:

```php
public function getCachedData(string $key)
{
    // Validate key format
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $key)) {
        throw new InvalidArgumentException('Invalid cache key');
    }
    
    return CacheCascade::get($key);
}
```

## Database Security

### Model Scopes

Use query scopes to limit what data can be cached:

```php
class User extends Model
{
    use CascadeInvalidation;
    
    // Only cache active, public profiles
    public function scopeForCascadeCache($query)
    {
        return $query->where('active', true)
                     ->where('public', true)
                     ->select(['id', 'name', 'avatar']); // Exclude sensitive fields
    }
}
```

### Seeder Security

Be cautious with auto-seeding in production:

```php
// config/cache-cascade.php
return [
    // Disable in production if seeders contain sensitive data
    'auto_seed' => env('APP_ENV') !== 'production',
];
```

## Access Control

### Artisan Commands

Restrict access to cache management commands:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Only allow cache refresh from trusted sources
    if ($this->app->environment('production')) {
        $schedule->command('cache:cascade:refresh settings')
                 ->dailyAt('03:00')
                 ->runInBackground()
                 ->onOneServer();
    }
}
```

### API Endpoints

If exposing cache operations via API:

```php
Route::middleware(['auth:api', 'can:manage-cache'])->group(function () {
    Route::post('/cache/refresh/{key}', function ($key) {
        // Validate permission for specific key
        if (!Gate::allows('refresh-cache', $key)) {
            abort(403);
        }
        
        return CacheCascade::refresh($key);
    });
});
```

## Logging and Monitoring

### Security Events

Log security-relevant cache operations:

```php
// config/cache-cascade.php
'logging' => [
    'enabled' => true,
    'channel' => 'security',
    'level' => 'warning',
    'log_invalidations' => true, // Track cache clears
    'log_visitor_isolation' => true, // Monitor isolation usage
],
```

### Monitoring Patterns

Watch for suspicious patterns:

```php
// Custom monitoring
Event::listen('cache.cascade.invalidated', function ($key) {
    if (RateLimiter::tooManyAttempts("cache-invalidate:{$key}", 10)) {
        Log::warning('Excessive cache invalidation', [
            'key' => $key,
            'ip' => request()->ip(),
            'user' => auth()->id(),
        ]);
    }
});
```

## Environment-Specific Configuration

### Production Settings

```php
// config/cache-cascade.php
return [
    // Production: More conservative settings
    'visitor_isolation' => true, // Default to isolated
    'auto_seed' => false, // Manual seeding only
    'logging' => [
        'enabled' => true,
        'level' => 'warning', // Less verbose
    ],
    'default_ttl' => 3600, // Shorter TTL for security
];
```

### Development Settings

```php
// config/cache-cascade.dev.php
return [
    // Development: More permissive
    'visitor_isolation' => false,
    'auto_seed' => true,
    'logging' => [
        'enabled' => true,
        'level' => 'debug',
    ],
];
```

## Common Vulnerabilities

### 1. Cache Key Injection

**Vulnerability:**
```php
// L BAD: Direct user input
$data = CacheCascade::get(request('cache_key'));
```

**Mitigation:**
```php
//  GOOD: Whitelist approach
$allowedKeys = ['settings', 'products', 'categories'];
$key = request('cache_key');

if (!in_array($key, $allowedKeys)) {
    abort(400, 'Invalid cache key');
}

$data = CacheCascade::get($key);
```

### 2. Information Disclosure

**Vulnerability:**
```php
// L BAD: Caching sensitive data without isolation
CacheCascade::set('user.'.auth()->id(), User::with('passwords')->find(auth()->id()));
```

**Mitigation:**
```php
//  GOOD: Selective caching with isolation
CacheCascade::remember('user.profile', function() {
    return auth()->user()->only(['name', 'email', 'avatar']);
}, 3600, true); // Visitor isolation enabled
```

### 3. Timing Attacks

**Vulnerability:**
Cache hits vs misses can reveal information through timing differences.

**Mitigation:**
```php
// Add random delay for sensitive operations
public function checkApiKey($key)
{
    $start = microtime(true);
    $valid = CacheCascade::get("api.keys.{$key}");
    
    // Constant time response
    $elapsed = microtime(true) - $start;
    if ($elapsed < 0.05) {
        usleep((0.05 - $elapsed) * 1000000);
    }
    
    return $valid;
}
```

## Security Checklist

Before deploying to production:

- [ ] Enable visitor isolation for user-specific data
- [ ] Verify file permissions (755 for directories, 644 for files)
- [ ] Ensure cache directories are outside document root
- [ ] Implement input validation for dynamic cache keys
- [ ] Configure appropriate logging levels
- [ ] Set reasonable TTLs (not too long for sensitive data)
- [ ] Review model scopes for data exposure
- [ ] Disable auto-seeding if using sensitive seed data
- [ ] Implement rate limiting for cache operations
- [ ] Monitor for unusual cache patterns
- [ ] Encrypt sensitive cached data
- [ ] Use HTTPS for all cache-dependent operations

## Reporting Security Issues

If you discover a security vulnerability, please email security@example.com instead of using the issue tracker. All security vulnerabilities will be promptly addressed.

## Further Reading

- [Laravel Security Documentation](https://laravel.com/docs/security)
- [OWASP Caching Guidelines](https://owasp.org/www-community/controls/Cache_Management)
- [Redis Security](https://redis.io/docs/manual/security/)