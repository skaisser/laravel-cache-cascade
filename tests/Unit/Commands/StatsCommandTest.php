<?php

namespace Skaisser\CacheCascade\Tests\Unit\Commands;

use Skaisser\CacheCascade\Tests\TestCase;
use Skaisser\CacheCascade\Tests\TestModel;
use Skaisser\CacheCascade\Facades\CacheCascade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class StatsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        CacheCascade::set('stats_test', ['data' => 'value']);
        
        // Create a test model
        TestModel::create(['name' => 'Stats Test', 'value' => 'test_value']);
    }
    
    public function test_show_general_stats()
    {
        // Trigger some cache operations to populate stats
        CacheCascade::get('stats_test'); // Cache hit
        CacheCascade::get('missing_key', 'default'); // Cache miss
        CacheCascade::set('new_key', 'data'); // Write operation
        
        $this->artisan('cache:cascade:stats')
            ->expectsOutput('Cache Cascade General Statistics')
            ->expectsOutputToContain('Runtime Statistics:')
            ->expectsOutputToContain('Cache Hits:')
            ->expectsOutputToContain('File Hits:')
            ->expectsOutputToContain('Database Hits:')
            ->expectsOutputToContain('Total Misses:')
            ->expectsOutputToContain('Write Operations:')
            ->expectsOutputToContain('Configuration:')
            ->expectsOutputToContain('File Storage:')
            ->assertExitCode(0);
    }
    
    public function test_show_key_specific_stats()
    {
        // Create test data first
        CacheCascade::set('stats_test', ['data' => 'value'], true);
        
        $this->artisan('cache:cascade:stats stats_test')
            ->expectsOutput('Cache Cascade Statistics for: stats_test')
            ->assertExitCode(0);
    }
    
    public function test_stats_for_missing_key()
    {
        $this->artisan('cache:cascade:stats missing_key')
            ->expectsOutput('Cache Cascade Statistics for: missing_key')
            ->assertExitCode(0);
    }
    
    public function test_stats_with_redis_cache()
    {
        // Temporarily set cache driver to redis for this test
        config(['cache.default' => 'redis']);
        
        // Mock Redis info
        Cache::shouldReceive('connection->info')
            ->once()
            ->andReturn([
                'used_memory_human' => '10M',
                'connected_clients' => '5'
            ]);
        
        $this->artisan('cache:cascade:stats')
            ->expectsOutputToContain('Redis Cache:')
            ->expectsOutputToContain('Used Memory: 10M')
            ->expectsOutputToContain('Connected Clients: 5')
            ->assertExitCode(0);
        
        // Reset cache driver
        config(['cache.default' => 'array']);
    }
    
    public function test_stats_with_redis_error()
    {
        // Temporarily set cache driver to redis
        config(['cache.default' => 'redis']);
        
        // Mock Redis to throw exception
        Cache::shouldReceive('connection->info')
            ->once()
            ->andThrow(new \Exception('Redis connection failed'));
        
        $this->artisan('cache:cascade:stats')
            ->expectsOutputToContain('Redis Cache:')
            ->expectsOutputToContain('Unable to retrieve Redis stats')
            ->assertExitCode(0);
        
        // Reset cache driver
        config(['cache.default' => 'array']);
    }
    
    public function test_stats_with_no_files()
    {
        // Remove all files
        $path = base_path(config('cache-cascade.config_path', 'config/dynamic'));
        if (File::exists($path)) {
            File::deleteDirectory($path);
        }
        
        $this->artisan('cache:cascade:stats')
            ->expectsOutputToContain('File Storage:')
            ->expectsOutputToContain('No files found')
            ->assertExitCode(0);
    }
    
    public function test_key_stats_calculates_sizes()
    {
        // Create data with known size
        $testData = str_repeat('a', 1000); // 1KB of data
        Cache::put('cascade:size_test', $testData);
        
        // Create file
        $path = base_path(config('cache-cascade.config_path', 'config/dynamic'));
        File::ensureDirectoryExists($path);
        File::put($path . '/size_test.php', '<?php return ["data" => "' . $testData . '"];');
        
        $this->artisan('cache:cascade:stats size_test')
            ->expectsOutput('Cache Cascade Statistics for: size_test')
            ->assertExitCode(0);
    }
}