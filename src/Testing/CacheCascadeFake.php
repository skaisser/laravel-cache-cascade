<?php

namespace Skaisser\CacheCascade\Testing;

use Skaisser\CacheCascade\Services\CacheCascadeManager;

class CacheCascadeFake extends CacheCascadeManager
{
    /**
     * In-memory storage for fake cache
     */
    protected array $fakeStorage = [];
    
    /**
     * Track method calls for assertions
     */
    protected array $calls = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Don't call parent constructor as we don't need Laravel app
        $this->config = [
            'default_ttl' => 3600,
            'cache_prefix' => 'test:',
            'visitor_isolation' => false,
            'use_tags' => false,
        ];
    }
    
    /**
     * Get configuration value with fallback
     */
    public function get(string $key, mixed $default = null, array $options = []): mixed
    {
        $this->recordCall('get', [$key, $default, $options]);
        
        $storageKey = $this->getStorageKey($key, $options['visitor_isolation'] ?? false);
        
        if (isset($this->fakeStorage[$storageKey])) {
            $data = $this->fakeStorage[$storageKey]['data'];
            
            // Apply transformation if provided
            if (isset($options['transform']) && is_callable($options['transform'])) {
                return $options['transform']($data);
            }
            
            return $data;
        }
        
        return $default;
    }
    
    /**
     * Set configuration value
     */
    public function set(string $key, mixed $data, bool $skipDatabase = false): void
    {
        $this->recordCall('set', [$key, $data, $skipDatabase]);
        
        $storageKey = $this->getStorageKey($key);
        $this->fakeStorage[$storageKey] = [
            'data' => $data,
            'timestamp' => time(),
            'skipDatabase' => $skipDatabase,
        ];
    }
    
    /**
     * Remember a value in cache
     */
    public function remember(string $key, \Closure $callback, ?int $ttl = null, bool $useVisitorIsolation = false): mixed
    {
        $this->recordCall('remember', [$key, 'closure', $ttl, $useVisitorIsolation]);
        
        $storageKey = $this->getStorageKey($key, $useVisitorIsolation);
        
        if (!isset($this->fakeStorage[$storageKey])) {
            $this->fakeStorage[$storageKey] = [
                'data' => $callback(),
                'timestamp' => time(),
                'ttl' => $ttl,
            ];
        }
        
        return $this->fakeStorage[$storageKey]['data'];
    }
    
    /**
     * Clear cache for a specific key
     */
    public function clearCache(string $key): void
    {
        $this->recordCall('clearCache', [$key]);
        
        // Clear with and without visitor isolation
        unset($this->fakeStorage[$this->getStorageKey($key, false)]);
        unset($this->fakeStorage[$this->getStorageKey($key, true)]);
    }
    
    /**
     * Clear all cache
     */
    public function clearAllCache(): void
    {
        $this->recordCall('clearAllCache', []);
        $this->fakeStorage = [];
    }
    
    /**
     * Invalidate cache and file layers
     */
    public function invalidate(string $key): void
    {
        $this->recordCall('invalidate', [$key]);
        $this->clearCache($key);
    }
    
    /**
     * Refresh cache from database
     */
    public function refresh(string $key): mixed
    {
        $this->recordCall('refresh', [$key]);
        
        // In fake mode, just return what's in storage or null
        $storageKey = $this->getStorageKey($key);
        return $this->fakeStorage[$storageKey]['data'] ?? null;
    }
    
    /**
     * Get storage key with optional visitor isolation
     */
    protected function getStorageKey(string $key, bool $useVisitorIsolation = false): string
    {
        $storageKey = $this->config['cache_prefix'] . $key;
        
        if ($useVisitorIsolation) {
            $storageKey .= ':visitor';
        }
        
        return $storageKey;
    }
    
    /**
     * Record method call for assertions
     */
    protected function recordCall(string $method, array $arguments): void
    {
        $this->calls[] = [
            'method' => $method,
            'arguments' => $arguments,
            'timestamp' => microtime(true),
        ];
    }
    
    /**
     * Assert that a method was called
     */
    public function assertCalled(string $method, ?array $arguments = null): void
    {
        $called = false;
        
        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                if ($arguments === null || $call['arguments'] === $arguments) {
                    $called = true;
                    break;
                }
            }
        }
        
        if (!$called) {
            $message = "Failed asserting that method '{$method}' was called";
            if ($arguments !== null) {
                $message .= " with arguments: " . json_encode($arguments);
            }
            throw new \PHPUnit\Framework\AssertionFailedError($message);
        }
    }
    
    /**
     * Assert that a method was not called
     */
    public function assertNotCalled(string $method): void
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Failed asserting that method '{$method}' was not called"
                );
            }
        }
    }
    
    /**
     * Assert that cache has a specific key
     */
    public function assertHas(string $key, bool $withVisitorIsolation = false): void
    {
        $storageKey = $this->getStorageKey($key, $withVisitorIsolation);
        
        if (!isset($this->fakeStorage[$storageKey])) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Failed asserting that cache has key '{$key}'"
            );
        }
    }
    
    /**
     * Assert that cache doesn't have a specific key
     */
    public function assertMissing(string $key, bool $withVisitorIsolation = false): void
    {
        $storageKey = $this->getStorageKey($key, $withVisitorIsolation);
        
        if (isset($this->fakeStorage[$storageKey])) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Failed asserting that cache doesn't have key '{$key}'"
            );
        }
    }
    
    /**
     * Get the number of times a method was called
     */
    public function calledCount(string $method): int
    {
        $count = 0;
        
        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get all recorded calls
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
    
    /**
     * Reset the fake
     */
    public function reset(): void
    {
        $this->fakeStorage = [];
        $this->calls = [];
    }
}