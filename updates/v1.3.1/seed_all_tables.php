<?php namespace Winter\Translate\Updates;

use Winter\Storm\Database\Updates\Seeder;
use Winter\Translate\Models\Locale;

class SeedAllTables extends Seeder
{

    public function run()
    {

        Locale::extend(function ($model) {
            $model->setTable('rainlab_translate_locales');
        });

        if(Locale::count() === 0) {
            Locale::create([
                'code' => 'en',
                'name' => 'English',
                'is_default' => true,
                'is_enabled' => true
            ]);
        }

        Locale::extend(function ($model) {
            $model->setTable('winter_translate_locales');
        });

    }

}
