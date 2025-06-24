# Testing with Cache Cascade

This guide shows you how to test code that uses Laravel Cache Cascade in your application.

## Using CacheCascade::fake()

The package provides a powerful fake implementation that makes testing cache-dependent code simple and reliable.

### Basic Setup

```php
use Skaisser\CacheCascade\Facades\CacheCascade;

class MyTest extends TestCase
{
    public function test_something()
    {
        // Replace the real implementation with a fake
        $fake = CacheCascade::fake();
        
        // Your test code here...
    }
}
```

### Available Assertions

The fake provides several assertion methods to verify cache interactions:

#### assertCalled($method, $arguments = null)

Assert that a method was called with optional argument verification:

```php
CacheCascade::set('key', 'value');

$fake->assertCalled('set', ['key', 'value', false]);
$fake->assertCalled('set'); // Just verify method was called
```

#### assertNotCalled($method)

Assert that a method was never called:

```php
CacheCascade::get('key');

$fake->assertNotCalled('set');
$fake->assertNotCalled('refresh');
```

#### assertHas($key, $withVisitorIsolation = false)

Assert that cache contains a specific key:

```php
CacheCascade::set('settings', ['theme' => 'dark']);

$fake->assertHas('settings');
$fake->assertHas('user.prefs', true); // Check with visitor isolation
```

#### assertMissing($key, $withVisitorIsolation = false)

Assert that cache doesn't contain a specific key:

```php
CacheCascade::invalidate('old.data');

$fake->assertMissing('old.data');
```

### Utility Methods

#### calledCount($method)

Get the number of times a method was called:

```php
CacheCascade::get('key1');
CacheCascade::get('key2');

$count = $fake->calledCount('get'); // Returns 2
```

#### getCalls()

Get all recorded method calls for custom assertions:

```php
$calls = $fake->getCalls();
// Returns array of ['method' => 'get', 'arguments' => [...], 'timestamp' => ...]
```

#### reset()

Clear all fake data and call history:

```php
$fake->reset();
// All stored data and call history is cleared
```

## Testing Examples

### Testing a Service Class

```php
// app/Services/SettingsService.php
class SettingsService
{
    public function getTheme(): string
    {
        return CacheCascade::remember('app.theme', function() {
            return Setting::where('key', 'theme')->value('value') ?? 'light';
        }, 3600);
    }
    
    public function updateTheme(string $theme): void
    {
        Setting::updateOrCreate(['key' => 'theme'], ['value' => $theme]);
        CacheCascade::refresh('app.theme');
    }
}

// tests/Unit/SettingsServiceTest.php
class SettingsServiceTest extends TestCase
{
    public function test_get_theme_uses_cache()
    {
        $fake = CacheCascade::fake();
        $service = new SettingsService();
        
        // First call should remember
        $theme = $service->getTheme();
        
        $fake->assertCalled('remember');
        $this->assertEquals('light', $theme);
    }
    
    public function test_update_theme_refreshes_cache()
    {
        $fake = CacheCascade::fake();
        $service = new SettingsService();
        
        // Update theme
        $service->updateTheme('dark');
        
        // Assert cache was refreshed
        $fake->assertCalled('refresh', ['app.theme']);
    }
}
```

### Testing Cache Invalidation

```php
public function test_model_update_invalidates_cache()
{
    $fake = CacheCascade::fake();
    
    // Set initial cache
    CacheCascade::set('faqs', [
        ['question' => 'What?', 'answer' => 'This.']
    ]);
    
    // Simulate model with CascadeInvalidation trait
    $faq = new Faq(['question' => 'What?', 'answer' => 'Updated.']);
    $faq->save();
    
    // Assert invalidation was triggered
    $fake->assertCalled('invalidate', ['faqs']);
}
```

### Testing Visitor Isolation

```php
public function test_visitor_isolation_prevents_data_leak()
{
    $fake = CacheCascade::fake();
    
    // Regular cache
    CacheCascade::set('preferences', ['public' => true]);
    
    // Visitor-isolated cache
    CacheCascade::remember('preferences', function() {
        return ['private' => true];
    }, 3600, true);
    
    // Assert both exist independently
    $fake->assertHas('preferences', false); // Regular
    $fake->assertHas('preferences', true);  // Isolated
    
    // Verify different data
    $public = CacheCascade::get('preferences');
    $private = CacheCascade::get('preferences', null, ['visitor_isolation' => true]);
    
    $this->assertTrue($public['public']);
    $this->assertTrue($private['private']);
}
```

### Testing Transformations

```php
public function test_transformation_applied_on_get()
{
    $fake = CacheCascade::fake();
    
    // Set raw data
    CacheCascade::set('numbers', [1, 2, 3, 4, 5]);
    
    // Get with transformation
    $doubled = CacheCascade::get('numbers', [], [
        'transform' => fn($nums) => array_map(fn($n) => $n * 2, $nums)
    ]);
    
    $this->assertEquals([2, 4, 6, 8, 10], $doubled);
}
```

## Integration Testing

For integration tests where you want to test the full cascade behavior, don't use the fake:

```php
class CacheCascadeIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing cache
        Cache::flush();
        
        // Clean up file storage
        $path = base_path('config/dynamic');
        if (File::exists($path)) {
            File::deleteDirectory($path);
        }
        File::makeDirectory($path, 0755, true);
    }
    
    public function test_full_cascade_flow()
    {
        // This tests the real implementation
        $data = CacheCascade::get('test.key', 'default');
        $this->assertEquals('default', $data);
        
        // Set and verify cascade
        CacheCascade::set('test.key', 'value');
        
        // Should be in cache
        $this->assertTrue(Cache::has('cascade:test.key'));
        
        // Should be in file
        $this->assertFileExists(base_path('config/dynamic/test.key.php'));
    }
}
```

## Best Practices

1. **Always use fake() for unit tests** - It's faster and more predictable
2. **Reset between tests if needed** - Use `$fake->reset()` to ensure clean state
3. **Test one thing at a time** - Focus assertions on the specific behavior being tested
4. **Use integration tests sparingly** - Only when you need to verify the full cascade behavior
5. **Mock dependencies** - When testing services that use CacheCascade, mock other dependencies

## Common Patterns

### Testing Cache Miss Behavior

```php
public function test_cache_miss_returns_default()
{
    $fake = CacheCascade::fake();
    
    $result = CacheCascade::get('missing.key', 'default value');
    
    $this->assertEquals('default value', $result);
    $fake->assertMissing('missing.key');
}
```

### Testing TTL Behavior

```php
public function test_remember_with_custom_ttl()
{
    $fake = CacheCascade::fake();
    
    CacheCascade::remember('temp.data', fn() => 'temporary', 300);
    
    $calls = $fake->getCalls();
    $rememberCall = collect($calls)->firstWhere('method', 'remember');
    
    $this->assertEquals(300, $rememberCall['arguments'][2]); // TTL argument
}
```

### Testing Error Handling

```php
public function test_handles_invalid_data_gracefully()
{
    $fake = CacheCascade::fake();
    
    // Your error handling test
    $result = CacheCascade::get('invalid.key', 'safe default');
    
    $this->assertEquals('safe default', $result);
}
```