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
        
        // Pre-populate cache
        CacheCascade::set('test_models', [
            ['id' => 1, 'name' => 'Cached Item', 'value' => 'cached']
        ]);
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
        
        // Delete model
        $model->delete();
        
        // Cache should be refreshed (soft deleted, so still in DB)
        $cached = CacheCascade::get('test_models');
        $this->assertEmpty($cached); // Default scope excludes soft deleted
    }

    public function test_restoring_model_invalidates_cache()
    {
        $model = TestModel::create([
            'name' => 'To Restore',
            'value' => 'restore_me'
        ]);
        
        $model->delete();
        
        // Restore model
        $model->restore();
        
        // Cache should include restored model
        $cached = CacheCascade::get('test_models');
        $this->assertCount(1, $cached);
        $this->assertEquals('To Restore', $cached[0]['name']);
    }

    public function test_manual_cache_refresh()
    {
        $model = TestModel::create([
            'name' => 'Manual Refresh',
            'value' => 'manual'
        ]);
        
        // Manually set different cache
        CacheCascade::set('test_models', [['name' => 'Wrong Data']]);
        
        // Manual refresh
        $result = $model->refreshCascadeCache();
        
        $this->assertCount(1, $result);
        $this->assertEquals('Manual Refresh', $result[0]['name']);
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
        
        // Should only have active items, ordered
        $this->assertCount(2, $cached);
        $this->assertEquals('Active 2', $cached[0]['name']); // order = 1
        $this->assertEquals('Active 1', $cached[1]['name']); // order = 2
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