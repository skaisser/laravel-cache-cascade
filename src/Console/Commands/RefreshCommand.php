<?php

namespace Skaisser\CacheCascade\Console\Commands;

use Illuminate\Console\Command;
use Skaisser\CacheCascade\Facades\CacheCascade;

class RefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:cascade:refresh {key : The cache key to refresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh a cache key by reloading from database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = $this->argument('key');
        
        $this->info("Refreshing cache key: {$key}");
        
        try {
            $data = CacheCascade::refresh($key);
            
            if ($data === null) {
                $this->warn("No data found in database for key: {$key}");
                return Command::FAILURE;
            }
            
            $count = is_countable($data) ? count($data) : 1;
            $this->info("✓ Cache refreshed successfully with {$count} items");
            
            if ($this->option('verbose')) {
                $this->line('Data preview:');
                $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to refresh cache: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}