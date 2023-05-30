<?php namespace Winter\Translate\Updates;

use Illuminate\Support\Facades\Cache;
use Schema;
use Winter\Storm\Database\Updates\Migration;
use Winter\Translate\Classes\Translator;

class RenameTables extends Migration
{
    const TABLES = [
        'attributes',
        'indexes',
        'locales',
        'messages',
    ];

    public function up()
    {
        foreach (self::TABLES as $table) {
            $from = 'rainlab_translate_' . $table;
            $to   = 'winter_translate_' . $table;
            if (Schema::hasTable($from) && !Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }

        // Clear isConfigured cache
        if (Cache::get(Translator::SESSION_CONFIGURED)) {
            Cache::forget(Translator::SESSION_CONFIGURED);
        }
    }

    public function down()
    {
        foreach (self::TABLES as $table) {
            $from = 'winter_translate_' . $table;
            $to   = 'rainlab_translate_' . $table;
            if (Schema::hasTable($from) && !Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }

        // Clear isConfigured cache
        if (Cache::get(Translator::SESSION_CONFIGURED)) {
            Cache::forget(Translator::SESSION_CONFIGURED);
        }
    }
}
