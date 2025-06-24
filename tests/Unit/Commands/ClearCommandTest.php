<?php

namespace Skaisser\CacheCascade\Tests\Unit\Commands;

use Skaisser\CacheCascade\Tests\TestCase;
use Skaisser\CacheCascade\Facades\CacheCascade;
use Illuminate\Support\Facades\Cache;

class ClearCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up some test data
        CacheCascade::set('key1', ['data' => 'value1']);
        CacheCascade::set('key2', ['data' => 'value2']);
        CacheCascade::set('key3', ['data' => 'value3']);
    }
    
    public function test_clear_specific_key()
    {
        // Verify key exists
        $this->assertNotNull(CacheCascade::get('key1'));
        
        // Clear specific key
        $this->artisan('cache:cascade:clear key1')
            ->expectsOutput('Clearing cache key: key1')
            ->expectsOutput('✓ Cache cleared successfully for key: key1')
            ->assertExitCode(0);
        
        // Verify key is cleared
        $this->assertNull(CacheCascade::get('key1'));
        
        // Other keys should still exist
        $this->assertNotNull(CacheCascade::get('key2'));
        $this->assertNotNull(CacheCascade::get('key3'));
    }
    
    public function test_clear_all_keys()
    {
        // Clear all with confirmation
        $this->artisan('cache:cascade:clear --all')
            ->expectsConfirmation('Are you sure you want to clear ALL cascade cache?', 'yes')
            ->expectsOutput('Clearing all cascade cache...')
            ->expectsOutput('✓ All cascade cache cleared successfully')
            ->assertExitCode(0);
        
        // Verify all keys are cleared
        $this->assertNull(CacheCascade::get('key1'));
        $this->assertNull(CacheCascade::get('key2'));
        $this->assertNull(CacheCascade::get('key3'));
    }
    
    public function test_clear_all_cancelled()
    {
        // Cancel clearing all
        $this->artisan('cache:cascade:clear --all')
            ->expectsConfirmation('Are you sure you want to clear ALL cascade cache?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);
        
        // Verify keys still exist
        $this->assertNotNull(CacheCascade::get('key1'));
        $this->assertNotNull(CacheCascade::get('key2'));
        $this->assertNotNull(CacheCascade::get('key3'));
    }
    
    public function test_clear_without_key_or_all_flag()
    {
        $this->artisan('cache:cascade:clear')
            ->expectsOutput('Please provide a cache key or use --all to clear all cache')
            ->assertExitCode(1);
    }
    
    public function test_clear_handles_errors()
    {
        // Mock CacheCascade to throw exception
        CacheCascade::shouldReceive('invalidate')
            ->once()
            ->with('error_key')
            ->andThrow(new \Exception('Clear error'));
        
        $this->artisan('cache:cascade:clear error_key')
            ->expectsOutput('Clearing cache key: error_key')
            ->expectsOutput('Failed to clear cache: Clear error')
            ->assertExitCode(1);
    }
    
    public function test_clear_all_handles_errors()
    {
        // Mock CacheCascade to throw exception
        CacheCascade::shouldReceive('clearAllCache')
            ->once()
            ->andThrow(new \Exception('Clear all error'));
        
        $this->artisan('cache:cascade:clear --all')
            ->expectsConfirmation('Are you sure you want to clear ALL cascade cache?', 'yes')
            ->expectsOutput('Clearing all cascade cache...')
            ->expectsOutput('Failed to clear all cache: Clear all error')
            ->assertExitCode(1);
    }
}