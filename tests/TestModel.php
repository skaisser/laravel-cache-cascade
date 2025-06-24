<?php

namespace Skaisser\CacheCascade\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Skaisser\CacheCascade\Traits\CascadeInvalidation;

class TestModel extends Model
{
    use SoftDeletes, CascadeInvalidation;

    protected $table = 'test_models';
    
    protected $fillable = ['name', 'value', 'order', 'active'];
    
    protected $casts = [
        'active' => 'boolean',
    ];
    
    // Override to use a custom cache key
    public function getCascadeCacheKey(): ?string
    {
        return 'test_models';
    }
}