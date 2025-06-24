<?php

namespace Skaisser\CacheCascade\Tests;

use Illuminate\Database\Seeder;

class TestModelSeeder extends Seeder
{
    public function run()
    {
        TestModel::create([
            'name' => 'Seeded Item 1',
            'value' => 'seeded_value_1',
            'order' => 1,
            'active' => true,
        ]);
        
        TestModel::create([
            'name' => 'Seeded Item 2',
            'value' => 'seeded_value_2',
            'order' => 2,
            'active' => true,
        ]);
    }
}