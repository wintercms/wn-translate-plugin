<?php namespace Winter\Translate\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

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
    }
}
