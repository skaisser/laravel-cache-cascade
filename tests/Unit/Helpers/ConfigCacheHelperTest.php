<?php

namespace Skaisser\CacheCascade\Tests\Unit\Helpers;

use Skaisser\CacheCascade\Tests\TestCase;
use Skaisser\CacheCascade\Helpers\ConfigCacheHelper;
use Illuminate\Support\Facades\File;

class ConfigCacheHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test config structure
        $configPath = config_path();
        $dynamicPath = config_path('dynamic');
        
        // Ensure directories exist
        File::ensureDirectoryExists($configPath);
        File::ensureDirectoryExists($dynamicPath);
        
        // Create test config files
        File::put($configPath . '/app.php', '<?php return ["name" => "Test App"];');
        File::put($configPath . '/database.php', '<?php return ["default" => "mysql"];');
        File::put($dynamicPath . '/dynamic1.php', '<?php return ["data" => "dynamic"];');
        File::put($dynamicPath . '/dynamic2.php', '<?php return ["data" => "cascade"];');
        
        // Create a non-PHP file that should be ignored
        File::put($configPath . '/readme.txt', 'This is not a PHP file');
    }
    
    protected function tearDown(): void
    {
        // Clean up test files
        $configPath = config_path();
        $dynamicPath = config_path('dynamic');
        
        File::delete($configPath . '/app.php');
        File::delete($configPath . '/database.php');
        File::delete($configPath . '/readme.txt');
        
        if (File::exists($dynamicPath)) {
            File::deleteDirectory($dynamicPath);
        }
        
        parent::tearDown();
    }
    
    public function test_get_static_config_files_excludes_dynamic_files()
    {
        // Ensure the config is set to use config/dynamic path
        config(['cache-cascade.config_path' => 'config/dynamic']);
        
        $files = ConfigCacheHelper::getStaticConfigFiles();
        
        // Get just the basenames for easier comparison
        $fileNames = array_map('basename', $files);
        
        // Should include regular config files
        $this->assertContains('app.php', $fileNames);
        $this->assertContains('database.php', $fileNames);
        
        // Should NOT include dynamic files
        $this->assertNotContains('dynamic1.php', $fileNames);
        $this->assertNotContains('dynamic2.php', $fileNames);
        
        // Should NOT include non-PHP files (no txt files at all)
        $this->assertNotContains('readme.txt', $fileNames);
    }
    
    public function test_get_static_config_files_with_custom_dynamic_path()
    {
        // Change dynamic path config
        config(['cache-cascade.config_path' => 'custom/dynamic']);
        
        // Create custom dynamic directory
        $customPath = base_path('custom/dynamic');
        File::ensureDirectoryExists($customPath);
        File::put($customPath . '/custom.php', '<?php return ["custom" => true];');
        
        $files = ConfigCacheHelper::getStaticConfigFiles();
        
        // Should still include regular config files
        $this->assertContains(config_path('app.php'), $files);
        
        // Should exclude files from custom dynamic path
        $this->assertNotContains($customPath . '/custom.php', $files);
        
        // Clean up
        File::deleteDirectory(base_path('custom'));
    }
    
    public function test_should_include_in_config_cache_default()
    {
        // Default should be false
        $this->assertFalse(ConfigCacheHelper::shouldIncludeInConfigCache());
    }
    
    public function test_should_include_in_config_cache_custom()
    {
        // Test with custom config
        config(['cache-cascade.include_in_config_cache' => true]);
        $this->assertTrue(ConfigCacheHelper::shouldIncludeInConfigCache());
        
        config(['cache-cascade.include_in_config_cache' => false]);
        $this->assertFalse(ConfigCacheHelper::shouldIncludeInConfigCache());
    }
    
    public function test_get_file_storage_keys()
    {
        // Set up the config path to point to our test directory
        $testPath = config_path('dynamic');
        config(['cache-cascade.config_path' => 'config/dynamic']);
        
        $keys = ConfigCacheHelper::getFileStorageKeys();
        
        // Should return keys from dynamic files
        $this->assertContains('dynamic1', $keys);
        $this->assertContains('dynamic2', $keys);
        $this->assertCount(2, $keys);
    }
    
    public function test_get_file_storage_keys_with_no_files()
    {
        // Remove dynamic directory
        File::deleteDirectory(config_path('dynamic'));
        
        $keys = ConfigCacheHelper::getFileStorageKeys();
        
        $this->assertIsArray($keys);
        $this->assertEmpty($keys);
    }
    
    public function test_get_file_storage_keys_with_custom_path()
    {
        // Change config path
        config(['cache-cascade.config_path' => 'custom/cascade']);
        
        // Create custom path with files
        $customPath = base_path('custom/cascade');
        File::ensureDirectoryExists($customPath);
        File::put($customPath . '/key1.php', '<?php return [];');
        File::put($customPath . '/key2.php', '<?php return [];');
        
        $keys = ConfigCacheHelper::getFileStorageKeys();
        
        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        
        // Clean up
        File::deleteDirectory(base_path('custom'));
    }
}