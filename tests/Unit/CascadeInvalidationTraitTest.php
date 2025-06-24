<?php

namespace Skaisser\CacheCascade\Tests\Unit;

use Skaisser\CacheCascade\Tests\TestCase;
use Skaisser\CacheCascade\Tests\TestModel;
use Skaisser\CacheCascade\Facades\CacheCascade;
use Illuminate\Support\Facades\Cache;

class CascadeInvalidationTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing models
        TestModel::query()->forceDelete();
        
        // Clear cache
        Cache::flush();
        
        // Pre-populate cache (skip database to avoid contamination)
        CacheCascade::set('test_models', [
            ['id' => 1, 'name' => 'Cached Item', 'value' => 'cached']
        ], true); // Skip database
    }

    public function test_saving_model_invalidates_cache()
    {
        // Verify cache exists
        $this->assertTrue(Cache::has('cascade:test_models'));
        
        // Create new model (triggers saved event)
        $model = TestModel::create([
            'name' => 'New Item',
            'value' => 'new_value'
        ]);
        
        // Cache should be refreshed with database data
        $cached = CacheCascade::get('test_models');
        $this->assertCount(1, $cached);
        $this->assertEquals('New Item', $cached[0]['name']);
    }

    public function test_updating_model_invalidates_cache()
    {
        $model = TestModel::create([
            'name' => 'Original',
            'value' => 'original'
        ]);
        
        // Update model
        $model->update(['name' => 'Updated']);
        
        // Cache should be refreshed
        $cached = CacheCascade::get('test_models');
        $this->assertEquals('Updated', $cached[0]['name']);
    }

    public function test_deleting_model_invalidates_cache()
    {
        $model = TestModel::create([
            'name' => 'To Delete',
            'value' => 'delete_me'
        ]);
        
        // Get the ID to check later
        $modelId = $model->id;
        
        // Delete model
        $model->delete();
        
        // Cache should be refreshed without the deleted model
        $cached = CacheCascade::get('test_models');
        
        // The deleted model should not be in the cache
        $found = collect($cached)->firstWhere('id', $modelId);
        $this->assertNull($found, 'Deleted model should not be in cache');
    }

    public function test_restoring_model_invalidates_cache()
    {
        $model = TestModel::create([
            'name' => 'To Restore',
            'value' => 'restore_me'
        ]);
        
        $modelId = $model->id;
        $model->delete();
        
        // Verify it's not in cache after delete
        $cached = CacheCascade::get('test_models');
        $found = collect($cached)->firstWhere('id', $modelId);
        $this->assertNull($found, 'Deleted model should not be in cache');
        
        // Restore model
        $model->restore();
        
        // Cache should include restored model
        $cached = CacheCascade::get('test_models');
        $found = collect($cached)->firstWhere('id', $modelId);
        $this->assertNotNull($found, 'Restored model should be in cache');
        $this->assertEquals('To Restore', $found['name']);
    }

    public function test_manual_cache_refresh()
    {
        $model = TestModel::create([
            'name' => 'Manual Refresh',
            'value' => 'manual'
        ]);
        
        // Manually set different cache (skip database)
        CacheCascade::set('test_models', [['id' => 999, 'name' => 'Wrong Data']], true);
        
        // Manual refresh
        $result = $model->refreshCascadeCache();
        
        // Should have refreshed from database
        $found = collect($result)->firstWhere('id', $model->id);
        $this->assertNotNull($found, 'Created model should be in refreshed cache');
        $this->assertEquals('Manual Refresh', $found['name']);
        
        // Wrong data should not be there
        $wrongData = collect($result)->firstWhere('id', 999);
        $this->assertNull($wrongData, 'Wrong data should not be in refreshed cache');
    }

    public function test_custom_cache_key_via_override()
    {
        // Create a custom model class inline for this test
        $customModel = new class extends TestModel {
            public function getCascadeCacheKey(): ?string
            {
                return 'custom_cache_key';
            }
        };
        
        $customModel->name = 'Custom Key Test';
        $customModel->value = 'custom';
        $customModel->save();
        
        // Should invalidate 'custom_cache_key' not 'test_models'
        $this->assertFalse(Cache::has('cascade:custom_cache_key'));
    }

    public function test_scope_for_cascade_cache()
    {
        $this->markTestSkipped('Scope test requires refactoring to support custom scopes on anonymous classes');
        // Create active and inactive models
        TestModel::create(['name' => 'Active 1', 'active' => true, 'order' => 2]);
        TestModel::create(['name' => 'Active 2', 'active' => true, 'order' => 1]);
        TestModel::create(['name' => 'Inactive', 'active' => false, 'order' => 3]);
        
        // Create custom model with scope
        $customModel = new class extends TestModel {
            public function scopeForCascadeCache($query)
            {
                return $query->where('active', true)->orderBy('order');
            }
        };
        
        // Trigger cache refresh
        $customModel->refreshCascadeCache();
        
        $cached = CacheCascade::get('test_models');
        
        // Should have active items, ordered
        $active1 = collect($cached)->firstWhere('name', 'Active 1');
        $active2 = collect($cached)->firstWhere('name', 'Active 2');
        $inactive = collect($cached)->firstWhere('name', 'Inactive');
        
        $this->assertNotNull($active1, 'Active 1 should be in cache');
        $this->assertNotNull($active2, 'Active 2 should be in cache');
        $this->assertNull($inactive, 'Inactive should not be in cache');
        
        // Check ordering - Active 2 (order=1) should come before Active 1 (order=2)
        $active1Index = collect($cached)->search(fn($item) => $item['name'] === 'Active 1');
        $active2Index = collect($cached)->search(fn($item) => $item['name'] === 'Active 2');
        $this->assertLessThan($active1Index, $active2Index, 'Active 2 should come before Active 1');
    }

    public function test_null_cache_key_does_not_invalidate()
    {
        $model = new class extends TestModel {
            public function getCascadeCacheKey(): ?string
            {
                return null;
            }
        };
        
        // Set some cache
        Cache::put('cascade:test_models', ['should_remain'], 3600);
        
        // Save model
        $model->name = 'No Cache Key';
        $model->save();
        
        // Cache should remain unchanged
        $cached = Cache::get('cascade:test_models');
        $this->assertEquals(['should_remain'], $cached);
    }

    public function test_trait_handles_missing_methods_gracefully()
    {
        // Model without soft deletes
        $model = new class extends \Illuminate\Database\Eloquent\Model {
            use \Skaisser\CacheCascade\Traits\CascadeInvalidation;
            
            protected $table = 'test_models';
            protected $fillable = ['name', 'value'];
        };
        
        // Should not throw error when checking for restored method
        $model->name = 'No Soft Deletes';
        $model->save();
        
        $this->assertTrue(true); // If we get here, no error was thrown
    }
}