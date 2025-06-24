<?php

namespace Skaisser\CacheCascade\Tests\Unit\Commands;

use Skaisser\CacheCascade\Tests\TestCase;
use Skaisser\CacheCascade\Facades\CacheCascade;
use Illuminate\Support\Facades\Artisan;

class RefreshCommandTest extends TestCase
{
    public function test_refresh_command_with_valid_key()
    {
        // Mock the refresh method to return data
        CacheCascade::shouldReceive('refresh')
            ->once()
            ->with('test_key')
            ->andReturn(['new' => 'data']);
        
        // Run the command
        $this->artisan('cache:cascade:refresh test_key')
            ->expectsOutput('Refreshing cache key: test_key')
            ->expectsOutput('✓ Cache refreshed successfully with 1 items')
            ->assertExitCode(0);
    }
    
    public function test_refresh_command_with_missing_key()
    {
        // Run command with non-existent key
        $this->artisan('cache:cascade:refresh missing_key')
            ->expectsOutput('Refreshing cache key: missing_key')
            ->expectsOutput('No data found in database for key: missing_key')
            ->assertExitCode(1);
    }
    
    public function test_refresh_command_with_verbose_output()
    {
        // Mock the refresh method to return data
        CacheCascade::shouldReceive('refresh')
            ->once()
            ->with('verbose_test')
            ->andReturn(['test' => 'data']);
        
        // Run with verbose flag
        $this->artisan('cache:cascade:refresh verbose_test -v')
            ->expectsOutput('Refreshing cache key: verbose_test')
            ->expectsOutput('✓ Cache refreshed successfully with 1 items')
            ->expectsOutputToContain('Data preview:')
            ->assertExitCode(0);
    }
    
    public function test_refresh_command_handles_errors()
    {
        // Mock CacheCascade to throw exception
        CacheCascade::shouldReceive('refresh')
            ->once()
            ->with('error_key')
            ->andThrow(new \Exception('Test error'));
        
        // Run command
        $this->artisan('cache:cascade:refresh error_key')
            ->expectsOutput('Refreshing cache key: error_key')
            ->expectsOutput('Failed to refresh cache: Test error')
            ->assertExitCode(1);
    }
}