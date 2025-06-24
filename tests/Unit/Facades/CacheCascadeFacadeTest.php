<?php

namespace Skaisser\CacheCascade\Tests\Unit\Facades;

use Skaisser\CacheCascade\Tests\TestCase;
use Skaisser\CacheCascade\Facades\CacheCascade;
use Skaisser\CacheCascade\Testing\CacheCascadeFake;

class CacheCascadeFacadeTest extends TestCase  
{
    public function test_facade_resolves_to_correct_instance()
    {
        $instance = CacheCascade::getFacadeRoot();
        
        $this->assertInstanceOf(\Skaisser\CacheCascade\Services\CacheCascadeManager::class, $instance);
    }
    
    public function test_facade_fake_returns_fake_instance()
    {
        $fake = CacheCascade::fake();
        
        $this->assertInstanceOf(CacheCascadeFake::class, $fake);
        
        // Verify the facade now uses the fake
        $this->assertSame($fake, CacheCascade::getFacadeRoot());
    }
    
    public function test_facade_methods_work()
    {
        // Test that facade methods are properly proxied
        CacheCascade::set('facade_test', 'value');
        $result = CacheCascade::get('facade_test');
        
        $this->assertEquals('value', $result);
    }
}