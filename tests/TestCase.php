<?php

namespace Skaisser\CacheCascade\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Skaisser\CacheCascade\CacheCascadeServiceProvider;
use Illuminate\Support\Facades\File;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setUpDatabase();
        $this->clearTestFiles();
    }

    protected function tearDown(): void
    {
        $this->clearTestFiles();
        
        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            CacheCascadeServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Setup cache
        $app['config']->set('cache.default', 'array');
        
        // Setup package config
        $app['config']->set('cache-cascade.config_path', 'tests/fixtures/dynamic');
        $app['config']->set('cache-cascade.use_database', true);
        $app['config']->set('cache-cascade.auto_seed', true);
        $app['config']->set('cache-cascade.model_namespace', 'Skaisser\\CacheCascade\\Tests\\');
        $app['config']->set('cache-cascade.seeder_namespace', 'Skaisser\\CacheCascade\\Tests\\');
        $app['config']->set('cache-cascade.logging.enabled', true);
    }

    protected function setUpDatabase()
    {
        // Create test tables
        $this->app['db']->connection()->getSchemaBuilder()->create('test_models', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('value')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function clearTestFiles()
    {
        $path = base_path('tests/fixtures/dynamic');
        if (File::exists($path)) {
            File::deleteDirectory($path);
        }
    }
}