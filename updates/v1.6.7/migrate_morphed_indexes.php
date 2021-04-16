<?php namespace Winter\Translate\Updates;

use Db;
use Winter\Storm\Database\Relations\Relation;
use Winter\Storm\Database\Updates\Migration;
use Winter\Translate\Models\Attribute;

/**
 * Because attributes are loaded using a proper morphMany relation starting from version 1.6.3,
 * custom morph map aliases are now taken into account. This migration updates all existing
 * model_types to use the registered alias.
 *
 * @see https://github.com/rainlab/translate-plugin/issues/539
 */
class MigrateMorphedIndexes extends Migration
{
    const TABLE_NAME = 'rainlab_translate_indexes';

    public function up()
    {
        foreach (Relation::$morphMap as $alias => $class) {
            Db::table(self::TABLE_NAME)->where('model_type', $class)->update(['model_type' => $alias]);
        }
    }

    public function down()
    {
        foreach (Relation::$morphMap as $alias => $class) {
            Db::table(self::TABLE_NAME)->where('model_type', $alias)->update(['model_type' => $class]);
        }
    }
}
