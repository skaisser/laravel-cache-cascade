<?php

namespace Skaisser\CacheCascade\Tests\Examples;

use Orchestra\Testbench\TestCase;
use Skaisser\CacheCascade\Facades\CacheCascade;
use Skaisser\CacheCascade\CacheCascadeServiceProvider;

/**
 * Example test cases showing how to use CacheCascade::fake() in your tests
 */
class ExampleCacheCascadeTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CacheCascadeServiceProvider::class];
    }
    
    protected function getPackageAliases($app)
    {
        return [
            'CacheCascade' => CacheCascade::class,
        ];
    }
    
    /**
     * Example: Testing basic get/set operations
     */
    public function test_basic_cache_operations()
    {
        // Arrange: Set up the fake
        $fake = CacheCascade::fake();
        
        // Act: Perform cache operations
        CacheCascade::set('app.name', 'My Application');
        $result = CacheCascade::get('app.name');
        
        // Assert: Verify the results
        $this->assertEquals('My Application', $result);
        
        // Assert method was called
        $fake->assertCalled('set', ['app.name', 'My Application', false]);
        $fake->assertCalled('get');
        
        // Assert cache has the key
        $fake->assertHas('app.name');
    }
    
    /**
     * Example: Testing remember functionality
     */
    public function test_remember_pattern()
    {
        // Arrange
        $fake = CacheCascade::fake();
        $callCount = 0;
        
        // Act: Call remember multiple times
        $result1 = CacheCascade::remember('expensive.operation', function() use (&$callCount) {
            $callCount++;
            return 'expensive result';
        }, 3600);
        
        $result2 = CacheCascade::remember('expensive.operation', function() use (&$callCount) {
            $callCount++;
            return 'expensive result';
        }, 3600);
        
        // Assert: Callback should only be called once
        $this->assertEquals(1, $callCount);
        $this->assertEquals('expensive result', $result1);
        $this->assertEquals('expensive result', $result2);
        
        // Assert remember was called twice
        $this->assertEquals(2, $fake->calledCount('remember'));
    }
    
    /**
     * Example: Testing cache invalidation
     */
    public function test_cache_invalidation()
    {
        // Arrange
        $fake = CacheCascade::fake();
        
        // Act
        CacheCascade::set('user.settings', ['theme' => 'dark']);
        CacheCascade::invalidate('user.settings');
        $result = CacheCascade::get('user.settings', ['theme' => 'light']);
        
        // Assert: Should return default after invalidation
        $this->assertEquals(['theme' => 'light'], $result);
        
        // Assert invalidate was called
        $fake->assertCalled('invalidate', ['user.settings']);
        
        // Assert cache doesn't have the key
        $fake->assertMissing('user.settings');
    }
    
    /**
     * Example: Testing visitor isolation
     */
    public function test_visitor_isolation()
    {
        // Arrange
        $fake = CacheCascade::fake();
        
        // Act: Set data with visitor isolation
        CacheCascade::set('user.preferences', ['lang' => 'en']);
        $regularData = CacheCascade::get('user.preferences');
        
        $isolatedData = CacheCascade::get('user.preferences', null, [
            'visitor_isolation' => true
        ]);
        
        // Assert: Isolated data should be different (null in this case)
        $this->assertEquals(['lang' => 'en'], $regularData);
        $this->assertNull($isolatedData);
        
        // Set isolated data
        CacheCascade::remember('user.preferences', function() {
            return ['lang' => 'es'];
        }, 3600, true);
        
        // Get isolated data again
        $isolatedData2 = CacheCascade::get('user.preferences', null, [
            'visitor_isolation' => true
        ]);
        
        // Assert has both regular and isolated keys
        $fake->assertHas('user.preferences', false);
        $fake->assertHas('user.preferences', true);
        
        $this->assertEquals(['lang' => 'es'], $isolatedData2);
    }
    
    /**
     * Example: Testing transformations
     */
    public function test_data_transformation()
    {
        // Arrange
        $fake = CacheCascade::fake();
        
        // Act: Set array data
        CacheCascade::set('products', [
            ['id' => 1, 'name' => 'Product A', 'price' => 100],
            ['id' => 2, 'name' => 'Product B', 'price' => 200],
        ]);
        
        // Get with transformation
        $expensiveProducts = CacheCascade::get('products', [], [
            'transform' => function($products) {
                return collect($products)
                    ->filter(fn($p) => $p['price'] > 150)
                    ->values()
                    ->toArray();
            }
        ]);
        
        // Assert: Should only have expensive products
        $this->assertCount(1, $expensiveProducts);
        $this->assertEquals('Product B', $expensiveProducts[0]['name']);
    }
    
    /**
     * Example: Testing clear all cache
     */
    public function test_clear_all_cache()
    {
        // Arrange
        $fake = CacheCascade::fake();
        
        // Act: Set multiple cache entries
        CacheCascade::set('config.app', ['name' => 'App']);
        CacheCascade::set('config.mail', ['driver' => 'smtp']);
        CacheCascade::set('config.cache', ['driver' => 'redis']);
        
        // Clear all
        CacheCascade::clearAllCache();
        
        // Assert: All cache should be cleared
        $fake->assertMissing('config.app');
        $fake->assertMissing('config.mail');
        $fake->assertMissing('config.cache');
        
        // Assert method was called
        $fake->assertCalled('clearAllCache');
    }
    
    /**
     * Example: Testing method call assertions
     */
    public function test_method_not_called()
    {
        // Arrange
        $fake = CacheCascade::fake();
        
        // Act: Perform some operations but not refresh
        CacheCascade::get('some.key', 'default');
        CacheCascade::set('another.key', 'value');
        
        // Assert: refresh was never called
        $fake->assertNotCalled('refresh');
        
        // But other methods were called
        $this->assertEquals(1, $fake->calledCount('get'));
        $this->assertEquals(1, $fake->calledCount('set'));
    }
    
    /**
     * Example: Resetting the fake between tests
     */
    public function test_reset_fake()
    {
        // Arrange
        $fake = CacheCascade::fake();
        
        // Act: First operation
        CacheCascade::set('key1', 'value1');
        $this->assertEquals(1, $fake->calledCount('set'));
        
        // Reset the fake
        $fake->reset();
        
        // Act: After reset
        CacheCascade::set('key2', 'value2');
        
        // Assert: Call count should restart
        $this->assertEquals(1, $fake->calledCount('set'));
        $fake->assertMissing('key1'); // Previous data is gone
        $fake->assertHas('key2'); // New data exists
    }
}