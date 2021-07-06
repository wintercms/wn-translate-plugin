<?php namespace Winter\Translate\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class RenameIndexes extends Migration
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
            $this->updateIndexNames($from, $to, $to);
        }
    }

    public function down()
    {
        foreach (self::TABLES as $table) {
            $from = 'winter_translate_' . $table;
            $to   = 'rainlab_translate_' . $table;
            $this->updateIndexNames($from, $to, $from);
        }
    }

    public function updateIndexNames($from, $to, $table)
    {
        $sm = Schema::getConnection()->getDoctrineSchemaManager();

        $table = $sm->listTableDetails($table);

        foreach ($table->getIndexes() as $index) {
            if ($index->isPrimary()) {
                continue;
            }

            $old = $index->getName();
            $new = str_replace($from, $to, $old);

            $table->renameIndex($old, $new);
        }
    }
}
