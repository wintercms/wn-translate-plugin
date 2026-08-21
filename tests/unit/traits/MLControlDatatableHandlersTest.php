<?php

use Winter\Translate\Traits\MLControl;

/**
 * Covers the datatable postback-handler injection that keeps a datatable's client-memory
 * data from being lost when an enclosing multilingual widget switches locale.
 *
 * @see https://github.com/wintercms/wn-translate-plugin/issues/33
 */
class MLControlDatatableHandlersTest extends \Winter\Translate\Tests\TranslatePluginTestCase
{
    protected function makeControl()
    {
        return new class {
            use MLControl;

            // Expose the protected helpers for testing.
            public function apply(array $fields, array $handlers): array
            {
                return $this->applyLocaleDatatableHandlers($fields, $handlers);
            }
        };
    }

    public function test_injects_handlers_into_a_datatable_field()
    {
        $handlers = ['w::onSwitchItemLocale', 'w::onCopyItemLocale'];

        $out = $this->makeControl()->apply([
            'title' => ['type' => 'text'],
            'specs' => ['type' => 'datatable'],
        ], $handlers);

        // Non-datatable field is untouched.
        $this->assertArrayNotHasKey('postbackHandlerName', $out['title']);

        // Datatable field gains the handlers, keeping the default onSave.
        $names = explode(',', $out['specs']['postbackHandlerName']);
        $this->assertContains('onSave', $names);
        $this->assertContains('w::onSwitchItemLocale', $names);
        $this->assertContains('w::onCopyItemLocale', $names);
    }

    public function test_preserves_and_dedupes_existing_handlers()
    {
        $out = $this->makeControl()->apply([
            'specs' => ['type' => 'datatable', 'postbackHandlerName' => 'onSave,onCustom'],
        ], ['w::onSwitchItemLocale', 'onCustom']);

        $names = explode(',', $out['specs']['postbackHandlerName']);
        $this->assertSame(['onSave', 'onCustom', 'w::onSwitchItemLocale'], $names);
    }

    public function test_injects_into_nested_form_datatables()
    {
        $handlers = ['w::onSwitchItemLocale', 'w::onCopyItemLocale'];

        $out = $this->makeControl()->apply([
            'contacts' => [
                'type' => 'nestedform',
                'form' => ['fields' => [
                    'inner_specs' => ['type' => 'datatable'],
                    'inner_text' => ['type' => 'text'],
                ]],
            ],
        ], $handlers);

        $inner = $out['contacts']['form']['fields'];
        $this->assertStringContainsString('w::onSwitchItemLocale', $inner['inner_specs']['postbackHandlerName']);
        $this->assertArrayNotHasKey('postbackHandlerName', $inner['inner_text']);
    }
}
