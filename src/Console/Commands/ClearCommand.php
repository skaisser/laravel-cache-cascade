<?php

namespace Skaisser\CacheCascade\Console\Commands;

use Illuminate\Console\Command;
use Skaisser\CacheCascade\Facades\CacheCascade;

class ClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:cascade:clear 
                            {key? : The cache key to clear (optional)} 
                            {--all : Clear all cascade cache keys}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear cache cascade data for a specific key or all keys';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->clearAll();
        }
        
        $key = $this->argument('key');
        
        if (!$key) {
            $this->error('Please provide a cache key or use --all to clear all cache');
            return Command::FAILURE;
        }
        
        return $this->clearKey($key);
    }
    
    /**
     * Clear a specific cache key
     */
    protected function clearKey(string $key): int
    {
        $this->info("Clearing cache key: {$key}");
        
        try {
            CacheCascade::invalidate($key);
            $this->info("✓ Cache cleared successfully for key: {$key}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to clear cache: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    /**
     * Clear all cascade cache
     */
    protected function clearAll(): int
    {
        if (!$this->confirm('Are you sure you want to clear ALL cascade cache?')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }
        
        $this->info('Clearing all cascade cache...');
        
        try {
            CacheCascade::clearAllCache();
            $this->info('✓ All cascade cache cleared successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to clear all cache: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}