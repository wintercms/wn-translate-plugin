<?php namespace Winter\Translate\Updates;

use Config;
use Schema;
use Str;
use Winter\Storm\Database\Updates\Migration;
use Winter\Translate\Classes\ThemeScanner;
use Winter\Translate\Models\Message;

class MigrateMessageCode extends Migration
{
    const TABLE_NAME = 'winter_translate_messages';

    public function up()
    {
        if (!Schema::hasColumn(self::TABLE_NAME, 'code_pre_2_1_0')) {
            Schema::table(self::TABLE_NAME, function($table) {
                $table->string('code_pre_2_1_0')->index()->nullable();
            });
        }

        foreach (Message::all() as $message) {
            $message->code_pre_2_1_0 = $message->code;
            $message->code = $message->code_md5 ?: Message::makeMessageCode($message->message_data[Message::DEFAULT_LOCALE]);
            $message->save();
        }

        if (Schema::hasColumn(self::TABLE_NAME, 'code_md5')) {
            Schema::table(self::TABLE_NAME, function($table) {
                $table->dropColumn('code_md5');
           });
        }

        if (in_array('Cms', Config::get('cms.loadModules', []))) {
            ThemeScanner::scan();
        }

        foreach (Message::whereNull('code_pre_2_1_0')->get() as $message) {
            $legacyMessage = Message::firstWhere(
                'code_pre_2_1_0',
                static::makeLegacyMessageCode($message->message_data[Message::DEFAULT_LOCALE])
            );
            if ($legacyMessage) {
                $message->message_data = array_merge($legacyMessage->message_data, $message->message_data);
                $message->save();
            }
        }
    }

    public function down()
    {
        if (!Schema::hasTable(self::TABLE_NAME)) {
            return;
        }
        Schema::table(self::TABLE_NAME, function($table) {
            $table->char('code_md5', 32)->index()->nullable();
            $table->string('code')->change();
        });

        foreach (Message::all() as $message) {
            $message->code_md5 = $message->code;
            $message->code = $message->code_pre_2_1_0 ?: static::makeLegacyMessageCode($message->message_data[Message::DEFAULT_LOCALE]);
            $message->save();
        }

        Schema::table(self::TABLE_NAME, function($table) {
            $table->dropColumn('code_pre_2_1_0');
        });
    }

    public static function makeLegacyMessageCode($messageId)
    {
        $separator = '.';

        // Convert all dashes/underscores into separator
        $messageId = preg_replace('!['.preg_quote('_').'|'.preg_quote('-').']+!u', $separator, $messageId);

        // Remove all characters that are not the separator, letters, numbers, or whitespace.
        $messageId = preg_replace('![^'.preg_quote($separator).'\pL\pN\s]+!u', '', mb_strtolower($messageId));

        // Replace all separator characters and whitespace by a single separator
        $messageId = preg_replace('!['.preg_quote($separator).'\s]+!u', $separator, $messageId);

        return Str::limit(trim($messageId, $separator), 250);
    }
}
