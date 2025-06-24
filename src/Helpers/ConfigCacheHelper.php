<?php

namespace Skaisser\CacheCascade\Helpers;

use Illuminate\Support\Facades\File;

class ConfigCacheHelper
{
    /**
     * Get all config files excluding dynamic cascade files
     * 
     * This is useful when you want to cache Laravel config but exclude
     * the dynamic files managed by Cache Cascade
     * 
     * @return array
     */
    public static function getStaticConfigFiles(): array
    {
        $configPath = config_path();
        $dynamicPath = config('cache-cascade.config_path', 'config/dynamic');
        $dynamicFullPath = base_path($dynamicPath);
        
        $files = [];
        
        foreach (File::allFiles($configPath) as $file) {
            // Skip if file is in the dynamic path
            if (str_starts_with($file->getPathname(), $dynamicFullPath)) {
                continue;
            }
            
            // Only include PHP files
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Check if config:cache should include cascade files
     * 
     * @return bool
     */
    public static function shouldIncludeInConfigCache(): bool
    {
        // By default, don't include dynamic files in config cache
        return config('cache-cascade.include_in_config_cache', false);
    }
    
    /**
     * Get a list of cascade cache keys from file storage
     * 
     * @return array
     */
    public static function getFileStorageKeys(): array
    {
        $path = base_path(config('cache-cascade.config_path', 'config/dynamic'));
        
        if (!File::exists($path)) {
            return [];
        }
        
        $keys = [];
        
        foreach (File::files($path) as $file) {
            $filename = $file->getFilenameWithoutExtension();
            $keys[] = $filename;
        }
        
        return $keys;
    }
}