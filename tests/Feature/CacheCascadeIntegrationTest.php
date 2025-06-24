<?php

namespace Skaisser\CacheCascade\Tests\Feature;

use Skaisser\CacheCascade\Tests\TestCase;
use Skaisser\CacheCascade\Tests\TestModel;
use Skaisser\CacheCascade\Facades\CacheCascade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CacheCascadeIntegrationTest extends TestCase
{
    public function test_complete_cascade_flow()
    {
        // 1. Start with empty state
        $this->assertNull(CacheCascade::get('test_models'));
        
        // 2. Auto-seed should kick in
        $result = CacheCascade::get('test_models', null, ['auto_seed' => true]);
        $this->assertCount(2, $result); // Seeder creates 2 items
        
        // 3. Verify all layers have data
        $this->assertTrue(Cache::has('cascade:test_models'));
        $this->assertTrue(File::exists(base_path('tests/fixtures/dynamic/test_models.php')));
        
        // 4. Update via model (should trigger invalidation)
        $model = TestModel::first();
        $model->update(['name' => 'Integration Updated']);
        
        // 5. Get should return fresh data
        $freshData = CacheCascade::get('test_models');
        $found = collect($freshData)->firstWhere('id', $model->id);
        $this->assertEquals('Integration Updated', $found['name']);
    }

    public function test_visitor_isolation_prevents_data_leakage()
    {
        // Visitor 1 sets data
        session()->put('id', 'visitor1');
        CacheCascade::set('private_data', ['user' => 'visitor1'], false);
        
        // Visitor 2 tries to access with isolation
        session()->put('id', 'visitor2');
        $result = CacheCascade::get('private_data', null, ['visitor_isolation' => true]);
        
        $this->assertNull($result);
        
        // Visitor 1 can still access
        session()->put('id', 'visitor1');
        $result = CacheCascade::get('private_data', null, ['visitor_isolation' => true]);
        
        $this->assertEquals(['user' => 'visitor1'], $result);
    }

    public function test_concurrent_model_updates()
    {
        // Create multiple models
        $model1 = TestModel::create(['name' => 'Model 1']);
        $model2 = TestModel::create(['name' => 'Model 2']);
        
        // Rapid updates
        $model1->update(['name' => 'Model 1 Updated']);
        $model2->update(['name' => 'Model 2 Updated']);
        $model1->delete();
        
        // Final state should be accurate
        $cached = CacheCascade::get('test_models');
        $this->assertCount(1, $cached);
        $this->assertEquals('Model 2 Updated', $cached[0]['name']);
    }

    public function test_error_recovery()
    {
        // Simulate corrupted file
        $path = base_path('tests/fixtures/dynamic');
        File::makeDirectory($path, 0755, true);
        File::put($path . '/corrupted.php', '<?php invalid syntax');
        
        // Should fall back to database
        TestModel::create(['name' => 'Recovery Test']);
        $result = CacheCascade::get('test_models');
        
        $this->assertNotNull($result);
        $this->assertEquals('Recovery Test', $result[0]['name']);
    }

    public function test_transform_with_database_fallback()
    {
        TestModel::create(['name' => 'Transform Test', 'value' => 'test']);
        
        $result = CacheCascade::get('test_models', [], [
            'transform' => function($data) {
                return collect($data)->pluck('name')->toArray();
            }
        ]);
        
        $this->assertEquals(['Transform Test'], $result);
    }

    public function test_cache_tagging_when_enabled()
    {
        config(['cache-cascade.use_tags' => true]);
        config(['cache-cascade.cache_tag' => 'test-tag']);
        
        // This test would work with Redis/Memcached
        // For array driver, we just verify no errors
        CacheCascade::set('tagged_data', ['tagged' => true]);
        $result = CacheCascade::get('tagged_data');
        
        $this->assertEquals(['tagged' => true], $result);
    }

    public function test_file_format_persistence()
    {
        $complexData = [
            'nested' => [
                'array' => ['with', 'values'],
                'boolean' => true,
                'null' => null,
                'number' => 123.45
            ]
        ];
        
        CacheCascade::set('complex_data', $complexData);
        
        // Clear cache to force file read
        Cache::forget('cascade:complex_data');
        
        $result = CacheCascade::get('complex_data');
        $this->assertEquals($complexData, $result);
    }

    public function test_performance_with_request_caching()
    {
        TestModel::create(['name' => 'Performance Test']);
        
        // First call hits database
        $start = microtime(true);
        CacheCascade::get('test_models');
        $firstTime = microtime(true) - $start;
        
        // Second call should be much faster (from cache)
        $start = microtime(true);
        CacheCascade::get('test_models');
        $secondTime = microtime(true) - $start;
        
        // Cache should be significantly faster
        $this->assertLessThan($firstTime, $secondTime);
    }
}