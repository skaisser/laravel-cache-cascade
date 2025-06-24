<?php

namespace Skaisser\CacheCascade\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Contracts\Foundation\Application;

class CacheCascadeManager
{
    /**
     * The application instance
     */
    protected Application $app;

    /**
     * Configuration array
     */
    protected array $config;

    /**
     * Track hits for statistics
     */
    protected array $stats = [
        'hits' => ['cache' => 0, 'file' => 0, 'database' => 0],
        'misses' => 0,
        'writes' => 0,
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app['config']['cache-cascade'] ?? [];
    }

    /**
     * Get a configuration value with multi-layer fallback
     *
     * @param string $key
     * @param mixed|null $default
     * @param array $options
     * @return mixed
     */
    public function get(string $key, mixed $default = null, array $options = []): mixed
    {
        $ttl = $options['ttl'] ?? $this->config['default_ttl'] ?? 86400; // 24 hours default
        $transform = $options['transform'] ?? null;
        $useVisitorIsolation = $options['visitor_isolation'] ?? $this->config['visitor_isolation'] ?? false;
        
        $cacheKey = $this->getCacheKey($key, $useVisitorIsolation);

        // Try to get from cache first
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $this->stats['hits']['cache']++;
            if ($this->config['logging']['log_hits'] ?? true) {
                $this->log('debug', "Cache hit for key: {$key}", ['layer' => 'cache', 'key' => $key]);
            }
            return $transform ? $transform($cached) : $cached;
        }

        // Try to get from file
        $configPath = $this->config['config_path'] ?? 'config/dynamic';
        $configFile = base_path($configPath . '/' . $key . '.php');
        
        if (File::exists($configFile)) {
            $config = require $configFile;
            $data = $config['data'] ?? [];
            Cache::put($cacheKey, $data, $ttl);
            $this->stats['hits']['file']++;
            if ($this->config['logging']['log_hits'] ?? true) {
                $this->log('debug', "File hit for key: {$key}", ['layer' => 'file', 'key' => $key]);
            }
            return $transform ? $transform($data) : $data;
        }

        // Try to get from database and seed if necessary
        if ($this->config['use_database'] ?? true) {
            $data = $this->loadFromDatabase($key);
            if ($data !== null) {
                Cache::put($cacheKey, $data, $ttl);
                // Also save to file for persistence
                $this->set($key, $data, true);
                $this->stats['hits']['database']++;
                if ($this->config['logging']['log_hits'] ?? true) {
                    $this->log('debug', "Database hit for key: {$key}", ['layer' => 'database', 'key' => $key]);
                }
                return $transform ? $transform($data) : $data;
            }
        }

        $this->stats['misses']++;
        if ($this->config['logging']['log_misses'] ?? true) {
            $this->log('info', "Cache miss for key: {$key}", ['key' => $key, 'default_used' => true]);
        }
        return $transform ? $transform($default) : $default;
    }

    /**
     * Set a configuration value
     *
     * @param string $key
     * @param mixed $data
     * @param bool $skipDatabase
     * @return void
     */
    public function set(string $key, mixed $data, bool $skipDatabase = false): void
    {
        try {
            $this->stats['writes']++;
            
            // Ensure directory exists
            $configPath = base_path($this->config['config_path'] ?? 'config/dynamic');
            if (!File::exists($configPath)) {
                File::makeDirectory($configPath, 0755, true);
            }

            // Write to file
            $configFile = $configPath . '/' . $key . '.php';
            File::put($configFile, '<?php return ' . var_export(['data' => $data], true) . ';');

            // Update cache (clear all visitor-specific versions if visitor isolation is enabled)
            if ($this->config['visitor_isolation'] ?? false) {
                $this->clearAllVisitorCaches($key);
            } else {
                Cache::put($this->getCacheKey($key, false), $data, $this->config['default_ttl'] ?? 86400);
            }

            // Skip database operations if requested
            if ($skipDatabase || !($this->config['use_database'] ?? true)) {
                if ($this->config['logging']['log_writes'] ?? true) {
                    $this->log('debug', "Data written for key: {$key}", ['key' => $key, 'layers' => ['cache', 'file']]);
                }
                return;
            }

            // Update database if model exists
            $this->saveToDatabase($key, $data);
            
            if ($this->config['logging']['log_writes'] ?? true) {
                $this->log('debug', "Data written for key: {$key}", ['key' => $key, 'layers' => ['cache', 'file', 'database']]);
            }
        } catch (\Exception $e) {
            Log::error("CacheCascade: Error setting {$key}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Refresh data from database and update all cache layers
     *
     * @param string $key
     * @return mixed
     */
    public function refresh(string $key): mixed
    {
        // First, clear all cache layers
        $this->invalidate($key);
        
        // Load fresh data from database
        $data = $this->loadFromDatabase($key);
        
        if ($data !== null) {
            // Update file storage
            $configPath = base_path($this->config['config_path'] ?? 'config/dynamic');
            if (!File::exists($configPath)) {
                File::makeDirectory($configPath, 0755, true);
            }
            
            $configFile = $configPath . '/' . $key . '.php';
            File::put($configFile, '<?php return ' . var_export(['data' => $data], true) . ';');
            
            // Update cache
            $cacheKey = $this->getCacheKey($key, false);
            Cache::put($cacheKey, $data, $this->config['default_ttl'] ?? 86400);
        }
        
        return $data;
    }

    /**
     * Invalidate all cache layers for a key
     *
     * @param string $key
     * @return void
     */
    public function invalidate(string $key): void
    {
        // Clear cache
        $this->clearCache($key);
        
        // Remove file if it exists
        $configFile = base_path($this->config['config_path'] ?? 'config/dynamic') . '/' . $key . '.php';
        if (File::exists($configFile)) {
            File::delete($configFile);
        }
    }

    /**
     * Clear the cache for a specific key
     *
     * @param string $key
     * @return void
     */
    public function clearCache(string $key): void
    {
        if ($this->config['visitor_isolation'] ?? false) {
            $this->clearAllVisitorCaches($key);
        } else {
            Cache::forget($this->getCacheKey($key, false));
        }
    }

    /**
     * Clear all config caches
     *
     * @return void
     */
    public function clearAllCache(): void
    {
        if ($this->config['use_tags'] ?? false) {
            if (Cache::supportsTags()) {
                Cache::tags($this->config['cache_tag'] ?? 'config-cache')->flush();
            }
        } else {
            // Clear cache by prefix - this is a simplified approach
            // In production you might want to track all keys
            Cache::flush();
        }
        
        // Clear all files in the config path
        $path = base_path($this->config['config_path'] ?? 'config/dynamic');
        if (File::exists($path)) {
            $files = File::files($path);
            foreach ($files as $file) {
                File::delete($file);
            }
        }
    }

    /**
     * Remember a value in cache with visitor isolation support
     *
     * @param string $key
     * @param \Closure $callback
     * @param int|null $ttl
     * @param bool $useVisitorIsolation
     * @return mixed
     */
    public function remember(string $key, \Closure $callback, ?int $ttl = null, bool $useVisitorIsolation = false): mixed
    {
        $cacheKey = $this->getCacheKey($key, $useVisitorIsolation);
        $ttl = $ttl ?? $this->config['default_ttl'] ?? 86400;

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Get the cache key with optional visitor isolation
     */
    protected function getCacheKey(string $key, bool $useVisitorIsolation = false): string
    {
        $prefix = $this->config['cache_prefix'] ?? 'config:';
        $cacheKey = $prefix . $key;

        if ($useVisitorIsolation) {
            $visitorId = $this->getVisitorId();
            if ($visitorId) {
                $cacheKey .= ':' . md5($visitorId);
            }
        }

        return $cacheKey;
    }

    /**
     * Get visitor ID for cache isolation
     */
    protected function getVisitorId(): ?string
    {
        // Try to get from lead persistence
        if ($this->app->bound('lead-persistence')) {
            $leadPersistence = $this->app->make('lead-persistence');
            $lead = $leadPersistence->getCurrentLead();
            if ($lead && method_exists($lead, 'getKey')) {
                return (string) $lead->getKey();
            }
        }

        // Fallback to session ID
        return session()->getId();
    }

    /**
     * Clear all visitor-specific caches for a key
     */
    protected function clearAllVisitorCaches(string $key): void
    {
        // If using tags, clear by tag
        if ($this->config['use_tags'] ?? false) {
            $tag = ($this->config['cache_tag'] ?? 'config-cache') . ':' . $key;
            Cache::tags($tag)->flush();
        } else {
            // Clear the main cache key
            Cache::forget($this->getCacheKey($key, false));
            // Note: Without tags, we can't easily clear all visitor-specific versions
            Log::warning("CacheCascade: Unable to clear all visitor caches for {$key} without tag support");
        }
    }

    /**
     * Load data from database
     */
    protected function loadFromDatabase(string $key): mixed
    {
        try {
            // Handle both singular and plural forms (e.g., 'faq' or 'faqs')
            $singularKey = Str::singular($key);
            $modelName = Str::studly($singularKey);
            $modelClass = $this->config['model_namespace'] ?? 'App\\Models\\';
            $modelClass .= $modelName;

            if (class_exists($modelClass)) {
                $query = $modelClass::query();
                
                // Apply ordering if the model has an 'order' column
                if (in_array('order', $modelClass::make()->getFillable())) {
                    $query->orderBy('order');
                }
                
                $data = $query->get()->toArray();

                if (!empty($data)) {
                    return $data;
                }

                // If no data and auto-seeding is enabled, try to seed
                if (empty($data) && ($this->config['auto_seed'] ?? true)) {
                    return $this->autoSeed($key, $modelClass);
                }
            }
        } catch (\Exception $e) {
            Log::error("CacheCascade: Error loading {$key} from database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return null;
    }

    /**
     * Save data to database
     */
    protected function saveToDatabase(string $key, mixed $data): void
    {
        try {
            $singularKey = Str::singular($key);
            $modelName = Str::studly($singularKey);
            $modelClass = $this->config['model_namespace'] ?? 'App\\Models\\';
            $modelClass .= $modelName;

            if (class_exists($modelClass)) {
                $modelClass::truncate();
                $modelClass::insert($data);
            }
        } catch (\Exception $e) {
            Log::error("CacheCascade: Error saving {$key} to database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Auto-seed data if seeder exists
     */
    protected function autoSeed(string $key, string $modelClass): mixed
    {
        $singularKey = Str::singular($key);
        $modelName = Str::studly($singularKey);
        $seederClass = $this->config['seeder_namespace'] ?? 'Database\\Seeders\\';
        $seederClass .= $modelName . 'Seeder';

        if (class_exists($seederClass)) {
            try {
                $seeder = new $seederClass();
                $seeder->run();

                // Get fresh data after seeding
                $query = $modelClass::query();
                if (in_array('order', $modelClass::make()->getFillable())) {
                    $query->orderBy('order');
                }
                
                $data = $query->get()->toArray();
                if (!empty($data)) {
                    return $data;
                }
            } catch (\Exception $e) {
                Log::error("CacheCascade: Failed to run seeder for {$key}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return [];
    }
    
    /**
     * Log cache operation if logging is enabled
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (!($this->config['logging']['enabled'] ?? false)) {
            return;
        }
        
        $channel = $this->config['logging']['channel'] ?? 'stack';
        $logLevel = $this->config['logging']['level'] ?? 'debug';
        
        // Only log if the message level is appropriate
        $levels = ['debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3, 'error' => 4];
        if (($levels[$level] ?? 0) >= ($levels[$logLevel] ?? 0)) {
            Log::channel($channel)->$level("CacheCascade: {$message}", $context);
        }
    }
    
    /**
     * Get runtime statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }
}