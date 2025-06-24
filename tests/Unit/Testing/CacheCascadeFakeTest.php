<?php

namespace Skaisser\CacheCascade\Tests\Unit\Testing;

use Orchestra\Testbench\TestCase;
use Skaisser\CacheCascade\Testing\CacheCascadeFake;
use PHPUnit\Framework\AssertionFailedError;

class CacheCascadeFakeTest extends TestCase
{
    protected CacheCascadeFake $fake;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new CacheCascadeFake();
    }
    
    public function test_basic_get_and_set()
    {
        $this->fake->set('key', 'value');
        $result = $this->fake->get('key');
        
        $this->assertEquals('value', $result);
    }
    
    public function test_get_with_default()
    {
        $result = $this->fake->get('missing', 'default');
        $this->assertEquals('default', $result);
    }
    
    public function test_get_with_transformation()
    {
        $this->fake->set('numbers', [1, 2, 3]);
        
        $result = $this->fake->get('numbers', [], [
            'transform' => fn($data) => array_sum($data)
        ]);
        
        $this->assertEquals(6, $result);
    }
    
    public function test_remember_pattern()
    {
        $callCount = 0;
        
        $result1 = $this->fake->remember('expensive', function() use (&$callCount) {
            $callCount++;
            return 'computed';
        });
        
        $result2 = $this->fake->remember('expensive', function() use (&$callCount) {
            $callCount++;
            return 'computed';
        });
        
        $this->assertEquals('computed', $result1);
        $this->assertEquals('computed', $result2);
        $this->assertEquals(1, $callCount); // Callback only called once
    }
    
    public function test_visitor_isolation()
    {
        // Set data without isolation
        $this->fake->set('shared', 'public data');
        
        // Set data with isolation
        $this->fake->remember('private', fn() => 'isolated data', null, true);
        
        // Get without isolation
        $this->assertEquals('public data', $this->fake->get('shared'));
        $this->assertNull($this->fake->get('private')); // Can't see isolated data
        
        // Get with isolation
        $this->assertEquals('isolated data', $this->fake->get('private', null, ['visitor_isolation' => true]));
    }
    
    public function test_clear_cache()
    {
        $this->fake->set('key1', 'value1');
        $this->fake->set('key2', 'value2');
        
        $this->fake->clearCache('key1');
        
        $this->assertNull($this->fake->get('key1'));
        $this->assertEquals('value2', $this->fake->get('key2'));
    }
    
    public function test_clear_all_cache()
    {
        $this->fake->set('key1', 'value1');
        $this->fake->set('key2', 'value2');
        
        $this->fake->clearAllCache();
        
        $this->assertNull($this->fake->get('key1'));
        $this->assertNull($this->fake->get('key2'));
    }
    
    public function test_invalidate()
    {
        $this->fake->set('key', 'value');
        $this->fake->invalidate('key');
        
        $this->assertNull($this->fake->get('key'));
    }
    
    public function test_refresh()
    {
        $this->fake->set('key', 'value');
        $result = $this->fake->refresh('key');
        
        $this->assertEquals('value', $result);
    }
    
    public function test_assert_called()
    {
        $this->fake->get('key');
        $this->fake->set('key', 'value');
        
        // Should pass
        $this->fake->assertCalled('get');
        $this->fake->assertCalled('set', ['key', 'value', false]);
        
        // Should throw exception
        $this->expectException(AssertionFailedError::class);
        $this->fake->assertCalled('refresh');
    }
    
    public function test_assert_not_called()
    {
        $this->fake->get('key');
        
        // Should pass
        $this->fake->assertNotCalled('set');
        
        // Should throw exception
        $this->expectException(AssertionFailedError::class);
        $this->fake->assertNotCalled('get');
    }
    
    public function test_assert_has()
    {
        $this->fake->set('exists', 'value');
        
        // Should pass
        $this->fake->assertHas('exists');
        
        // Should throw exception
        $this->expectException(AssertionFailedError::class);
        $this->fake->assertHas('missing');
    }
    
    public function test_assert_missing()
    {
        $this->fake->set('exists', 'value');
        
        // Should pass
        $this->fake->assertMissing('missing');
        
        // Should throw exception
        $this->expectException(AssertionFailedError::class);
        $this->fake->assertMissing('exists');
    }
    
    public function test_called_count()
    {
        $this->fake->get('key1');
        $this->fake->get('key2');
        $this->fake->set('key', 'value');
        
        $this->assertEquals(2, $this->fake->calledCount('get'));
        $this->assertEquals(1, $this->fake->calledCount('set'));
        $this->assertEquals(0, $this->fake->calledCount('refresh'));
    }
    
    public function test_get_calls()
    {
        $this->fake->get('key', 'default');
        $this->fake->set('key', 'value');
        
        $calls = $this->fake->getCalls();
        
        $this->assertCount(2, $calls);
        $this->assertEquals('get', $calls[0]['method']);
        $this->assertEquals(['key', 'default', []], $calls[0]['arguments']);
        $this->assertEquals('set', $calls[1]['method']);
        $this->assertEquals(['key', 'value', false], $calls[1]['arguments']);
    }
    
    public function test_reset()
    {
        $this->fake->set('key', 'value');
        $this->fake->get('key');
        
        $this->assertEquals(1, $this->fake->calledCount('get'));
        $this->fake->assertHas('key');
        
        $this->fake->reset();
        
        $this->assertEquals(0, $this->fake->calledCount('get'));
        $this->fake->assertMissing('key');
    }
    
    public function test_get_stats()
    {
        // CacheCascadeFake extends the manager, so it should have getStats
        $stats = $this->fake->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('writes', $stats);
    }
    
    public function test_skip_database_parameter()
    {
        $this->fake->set('key', 'value', true);
        
        $calls = $this->fake->getCalls();
        $this->assertEquals(['key', 'value', true], $calls[0]['arguments']);
    }
    
    public function test_assert_called_with_wrong_arguments()
    {
        $this->fake->set('key', 'value');
        
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Failed asserting that method 'set' was called with arguments: [\"key\",\"wrong\",false]");
        
        $this->fake->assertCalled('set', ['key', 'wrong', false]);
    }
}