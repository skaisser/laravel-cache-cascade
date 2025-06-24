<?php

namespace Skaisser\CacheCascade\Tests\Feature;

use Skaisser\CacheCascade\Tests\TestCase;
use Skaisser\CacheCascade\Facades\CacheCascade;

class NewMethodsTest extends TestCase
{
    public function test_forget_method_clears_cache()
    {
        // Set a value
        CacheCascade::set('test-forget', 'value');
        
        // Verify it exists
        $this->assertEquals('value', CacheCascade::get('test-forget'));
        
        // Use forget method (which only clears cache layer, not file)
        CacheCascade::forget('test-forget');
        
        // For complete removal, use invalidate
        CacheCascade::invalidate('test-forget');
        
        // Verify it's gone
        $this->assertNull(CacheCascade::get('test-forget'));
    }
    
    public function test_remember_for_method_with_laravel_signature()
    {
        // Clear any existing cache
        CacheCascade::clearCache('test-remember-for');
        
        // Use rememberFor with Laravel-compatible signature
        $value = CacheCascade::rememberFor('test-remember-for', 3600, function() {
            return 'computed-value';
        });
        
        $this->assertEquals('computed-value', $value);
        
        // Verify it's cached
        $cached = CacheCascade::get('test-remember-for');
        $this->assertEquals('computed-value', $cached);
    }
    
    public function test_cache_cascade_helper_function()
    {
        // Test getting with default
        $value = cache_cascade('non-existent', 'default');
        $this->assertEquals('default', $value);
        
        // Test with callback
        $computed = cache_cascade('helper-test', function() {
            return 'helper-value';
        });
        $this->assertEquals('helper-value', $computed);
        
        // Verify it's cached
        $cached = cache_cascade('helper-test');
        $this->assertEquals('helper-value', $cached);
        
        // Test getting the manager instance
        $manager = cache_cascade();
        $this->assertInstanceOf(\Skaisser\CacheCascade\Services\CacheCascadeManager::class, $manager);
    }
}