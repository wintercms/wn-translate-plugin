<?php namespace Winter\Translate\Tests\Unit\Models;

use Winter\Translate\Models\Message;
use Winter\Translate\Models\MessageExport;
use Winter\Translate\Models\Locale;

class ExportMessageTest extends \Winter\Translate\Tests\TranslatePluginTestCase
{

    public function testCanHandleNoMessages()
    {
        $exportModel = new MessageExport();
        $expected = [];

        $result = $exportModel->exportData([]);

        $this->assertEquals($expected, $result);
    }

    public function testCanHandleNoColumn()
    {
        $exportModel = new MessageExport();
        $this->createMessages();
        $expected = [[], []];

        $result = $exportModel->exportData([]);

        $this->assertEquals($expected, $result);
    }

    public function testExportSomeColumns()
    {
        $exportMode = new MessageExport();
        $this->createMessages();
        $expected = [
            ['code' => 'hello'],
            ['code' => 'bye'],
        ];

        $result = $exportMode->exportData(['code']);

        $this->assertEquals($expected, $result);
    }

    public function testExportAllColumns()
    {
        $exportMode = new MessageExport();
        $this->createMessages();
        $expected = [
            ['code' => 'hello', 'de' => 'Hallo, Welt', 'en' => 'Hello, World'],
            ['code' => 'bye', 'de' => 'Auf Wiedersehen', 'en' => 'Goodbye'],
        ];

        $result = $exportMode->exportData(['code', 'de', 'en']);

        $this->assertEquals($expected, $result);
    }

    public function testCanHandleNonExistingColumns()
    {
        $exportMode = new MessageExport();
        $this->createMessages();
        $expected = [
            ['dummy' => ''],
            ['dummy' => ''],
        ];

        $result = $exportMode->exportData(['dummy']);

        $this->assertEquals($expected, $result);
    }

    private function createMessages()
    {
        Message::create([
            'code' => 'hello', 'message_data' => ['de' => 'Hallo, Welt', 'en' => 'Hello, World']
        ]);
        Message::create([
            'code' => 'bye', 'message_data' => ['de' => 'Auf Wiedersehen', 'en' => 'Goodbye']
        ]);
    }

    public function testGetColumns()
    {
        Locale::unguard();
        Locale::create(['code' => 'de', 'name' => 'German', 'is_enabled' => true]);

        $columns = MessageExport::getColumns();

        $this->assertEquals([
            MessageExport::CODE_COLUMN_NAME => MessageExport::CODE_COLUMN_NAME,
            Message::DEFAULT_LOCALE => MessageExport::DEFAULT_COLUMN_NAME,
            'en' => 'en',
            'de' => 'de',
        ], $columns);
    }
}
