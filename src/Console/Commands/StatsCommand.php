<?php

namespace Skaisser\CacheCascade\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class StatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:cascade:stats {key? : Show stats for a specific key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display cache cascade statistics and storage layer information';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = $this->argument('key');
        
        if ($key) {
            return $this->showKeyStats($key);
        }
        
        return $this->showGeneralStats();
    }
    
    /**
     * Show statistics for a specific key
     */
    protected function showKeyStats(string $key): int
    {
        $this->info("Cache Cascade Statistics for: {$key}");
        $this->line(str_repeat('-', 50));
        
        $stats = $this->gatherKeyStats($key);
        
        $this->table(
            ['Layer', 'Status', 'Size', 'Last Modified'],
            [
                ['Cache', $stats['cache']['status'], $stats['cache']['size'], $stats['cache']['modified']],
                ['File', $stats['file']['status'], $stats['file']['size'], $stats['file']['modified']],
                ['Database', $stats['database']['status'], $stats['database']['count'] . ' records', $stats['database']['modified']],
            ]
        );
        
        return Command::SUCCESS;
    }
    
    /**
     * Show general statistics
     */
    protected function showGeneralStats(): int
    {
        $this->info('Cache Cascade General Statistics');
        $this->line(str_repeat('-', 50));
        
        $config = config('cache-cascade');
        
        // Configuration info
        $this->line('<comment>Configuration:</comment>');
        $this->line('  Cache Driver: ' . config('cache.default'));
        $this->line('  Default TTL: ' . ($config['default_ttl'] ?? 86400) . ' seconds');
        $this->line('  Config Path: ' . ($config['config_path'] ?? 'config/dynamic'));
        $this->line('  Visitor Isolation: ' . ($config['visitor_isolation'] ? 'Enabled' : 'Disabled'));
        $this->line('  Auto-seeding: ' . ($config['auto_seed'] ? 'Enabled' : 'Disabled'));
        
        // File storage stats
        $this->line('');
        $this->line('<comment>File Storage:</comment>');
        $path = base_path($config['config_path'] ?? 'config/dynamic');
        if (File::exists($path)) {
            $files = File::files($path);
            $totalSize = 0;
            foreach ($files as $file) {
                $totalSize += $file->getSize();
            }
            $this->line('  Files: ' . count($files));
            $this->line('  Total Size: ' . $this->formatBytes($totalSize));
        } else {
            $this->line('  No files found');
        }
        
        // Cache stats (if Redis)
        if (config('cache.default') === 'redis') {
            $this->line('');
            $this->line('<comment>Redis Cache:</comment>');
            try {
                $info = Cache::connection()->info();
                $this->line('  Used Memory: ' . ($info['used_memory_human'] ?? 'N/A'));
                $this->line('  Connected Clients: ' . ($info['connected_clients'] ?? 'N/A'));
            } catch (\Exception $e) {
                $this->line('  Unable to retrieve Redis stats');
            }
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Gather statistics for a specific key
     */
    protected function gatherKeyStats(string $key): array
    {
        $config = config('cache-cascade');
        $cachePrefix = $config['cache_prefix'] ?? 'cascade:';
        $cacheKey = $cachePrefix . $key;
        
        // Cache layer
        $cacheExists = Cache::has($cacheKey);
        $cacheStats = [
            'status' => $cacheExists ? '✓ Present' : '✗ Missing',
            'size' => 'N/A',
            'modified' => 'N/A',
        ];
        
        if ($cacheExists) {
            try {
                $data = Cache::get($cacheKey);
                $cacheStats['size'] = $this->formatBytes(strlen(serialize($data)));
            } catch (\Exception $e) {
                // Ignore serialization errors
            }
        }
        
        // File layer
        $filePath = base_path(($config['config_path'] ?? 'config/dynamic') . '/' . $key . '.php');
        $fileExists = File::exists($filePath);
        $fileStats = [
            'status' => $fileExists ? '✓ Present' : '✗ Missing',
            'size' => $fileExists ? $this->formatBytes(File::size($filePath)) : 'N/A',
            'modified' => $fileExists ? File::lastModified($filePath) : 'N/A',
        ];
        
        if ($fileStats['modified'] !== 'N/A') {
            $fileStats['modified'] = date('Y-m-d H:i:s', $fileStats['modified']);
        }
        
        // Database layer
        $dbStats = [
            'status' => '✗ Unknown',
            'count' => 0,
            'modified' => 'N/A',
        ];
        
        // Try to determine model from key
        try {
            $modelClass = $config['model_namespace'] ?? 'App\\Models\\';
            $modelClass .= str($key)->singular()->studly()->toString();
            
            if (class_exists($modelClass)) {
                $count = $modelClass::count();
                $dbStats['status'] = $count > 0 ? '✓ Present' : '✗ Empty';
                $dbStats['count'] = $count;
                
                $latest = $modelClass::latest('updated_at')->first();
                if ($latest) {
                    $dbStats['modified'] = $latest->updated_at->format('Y-m-d H:i:s');
                }
            }
        } catch (\Exception $e) {
            // Model doesn't exist or other error
        }
        
        return [
            'cache' => $cacheStats,
            'file' => $fileStats,
            'database' => $dbStats,
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}