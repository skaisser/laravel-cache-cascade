<?php

namespace Skaisser\CacheCascade\Tests\Unit;

use Skaisser\CacheCascade\Tests\TestCase;
use Skaisser\CacheCascade\Tests\TestModel;
use Skaisser\CacheCascade\Services\CacheCascadeManager;
use Skaisser\CacheCascade\Facades\CacheCascade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CacheCascadeManagerTest extends TestCase
{
    protected CacheCascadeManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->manager = new CacheCascadeManager($this->app);
    }

    public function test_get_returns_data_from_cache_when_available()
    {
        $data = ['test' => 'data'];
        Cache::put('cascade:test_key', $data, 3600);
        
        $result = $this->manager->get('test_key');
        
        $this->assertEquals($data, $result);
    }

    public function test_get_returns_data_from_file_when_cache_misses()
    {
        $data = ['test' => 'file_data'];
        $path = base_path('tests/fixtures/dynamic');
        File::makeDirectory($path, 0755, true);
        File::put($path . '/test_key.php', '<?php return ' . var_export(['data' => $data], true) . ';');
        
        $result = $this->manager->get('test_key');
        
        $this->assertEquals($data, $result);
        $this->assertTrue(Cache::has('cascade:test_key')); // Should be cached now
    }

    public function test_get_returns_data_from_database_when_file_misses()
    {
        TestModel::create(['name' => 'Test 1', 'value' => 'value1']);
        TestModel::create(['name' => 'Test 2', 'value' => 'value2']);
        
        $result = $this->manager->get('test_models');
        
        $this->assertCount(2, $result);
        $this->assertEquals('Test 1', $result[0]['name']);
        
        // Should create file
        $filePath = base_path('tests/fixtures/dynamic/test_models.php');
        $this->assertTrue(File::exists($filePath));
    }

    public function test_get_auto_seeds_when_database_is_empty()
    {
        // Clear any existing data
        TestModel::truncate();
        
        $result = $this->manager->get('test_models');
        
        $this->assertCount(2, $result); // Should have seeded 2 items
        $this->assertEquals('Seeded Item 1', $result[0]['name']);
        $this->assertEquals('Seeded Item 2', $result[1]['name']);
    }

    public function test_get_applies_transform_callback()
    {
        Cache::put('cascade:transform_test', [['id' => 1], ['id' => 2]], 3600);
        
        $result = $this->manager->get('transform_test', [], [
            'transform' => fn($data) => collect($data)->pluck('id')->toArray()
        ]);
        
        $this->assertEquals([1, 2], $result);
    }

    public function test_get_uses_visitor_isolation_when_enabled()
    {
        // Simulate different visitor IDs
        session()->put('id', 'visitor1');
        $this->manager->set('isolated_key', ['visitor' => 1]);
        
        session()->put('id', 'visitor2');
        $result = $this->manager->get('isolated_key', null, ['visitor_isolation' => true]);
        
        $this->assertNull($result); // Different visitor should not see the data
    }

    public function test_set_updates_all_layers()
    {
        $data = ['test' => 'new_data'];
        
        $this->manager->set('set_test', $data);
        
        // Check cache
        $this->assertTrue(Cache::has('cascade:set_test'));
        $this->assertEquals($data, Cache::get('cascade:set_test'));
        
        // Check file
        $filePath = base_path('tests/fixtures/dynamic/set_test.php');
        $this->assertTrue(File::exists($filePath));
        
        // Check file content
        $fileData = require $filePath;
        $this->assertEquals($data, $fileData['data']);
    }

    public function test_set_skips_database_when_requested()
    {
        $data = ['test' => 'skip_db'];
        
        $this->manager->set('skip_db_test', $data, true);
        
        // Should still update cache and file
        $this->assertTrue(Cache::has('cascade:skip_db_test'));
        $filePath = base_path('tests/fixtures/dynamic/skip_db_test.php');
        $this->assertTrue(File::exists($filePath));
    }

    public function test_refresh_reloads_from_database()
    {
        // Create initial data
        TestModel::create(['name' => 'Initial', 'value' => 'initial']);
        $this->manager->set('test_models', [['name' => 'Cached', 'value' => 'cached']]);
        
        // Update database
        TestModel::truncate();
        TestModel::create(['name' => 'Updated', 'value' => 'updated']);
        
        // Refresh
        $result = $this->manager->refresh('test_models');
        
        $this->assertCount(1, $result);
        $this->assertEquals('Updated', $result[0]['name']);
        
        // Verify cache was updated
        $cached = Cache::get('cascade:test_models');
        $this->assertEquals('Updated', $cached[0]['name']);
    }

    public function test_invalidate_clears_cache_and_file()
    {
        // Set up data
        $this->manager->set('invalidate_test', ['test' => 'data']);
        
        // Verify it exists
        $this->assertTrue(Cache::has('cascade:invalidate_test'));
        $filePath = base_path('tests/fixtures/dynamic/invalidate_test.php');
        $this->assertTrue(File::exists($filePath));
        
        // Invalidate
        $this->manager->invalidate('invalidate_test');
        
        // Verify cleared
        $this->assertFalse(Cache::has('cascade:invalidate_test'));
        $this->assertFalse(File::exists($filePath));
    }

    public function test_remember_caches_callback_result()
    {
        $result = $this->manager->remember('remember_test', function() {
            return ['computed' => 'value'];
        }, 3600);
        
        $this->assertEquals(['computed' => 'value'], $result);
        $this->assertTrue(Cache::has('cascade:remember_test'));
        
        // Second call should not execute callback
        $result2 = $this->manager->remember('remember_test', function() {
            return ['should' => 'not_run'];
        }, 3600);
        
        $this->assertEquals(['computed' => 'value'], $result2);
    }

    public function test_clear_cache_removes_specific_key()
    {
        Cache::put('cascade:clear_test', ['data'], 3600);
        
        $this->manager->clearCache('clear_test');
        
        $this->assertFalse(Cache::has('cascade:clear_test'));
    }

    public function test_get_handles_missing_model_gracefully()
    {
        $result = $this->manager->get('non_existent_model', 'default');
        
        $this->assertEquals('default', $result);
    }

    public function test_facade_works_correctly()
    {
        CacheCascade::set('facade_test', ['facade' => 'data']);
        
        $result = CacheCascade::get('facade_test');
        
        $this->assertEquals(['facade' => 'data'], $result);
    }
}