<?php namespace Winter\Translate\Updates;

use Winter\Storm\Database\Updates\Seeder;
use Winter\Translate\Models\Locale;

class SeedAllTables extends Seeder
{

    public function run()
    {
        if(Locale::count() === 0) {
            Locale::create([
                'code' => 'en',
                'name' => 'English',
                'is_default' => true,
                'is_enabled' => true
            ]);
        }
    }

}
