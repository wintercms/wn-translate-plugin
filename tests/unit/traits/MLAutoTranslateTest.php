<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Winter\Translate\Traits\MLAutoTranslate;

class MLAutoTranslateTest extends \Winter\Translate\Tests\TranslatePluginTestCase
{

    /**
     * Builds a bare consumer of the trait that mimics a composite ML widget by
     * exposing the properties getTranslatableFieldDefinitions() reads:
     *  - 'form' => ['fields' => [...]]  (repeater / nestedform single form)
     *  - 'useGroups' + 'groupDefinitions' (repeater groups / blocks)
     */
    protected function createTranslator(array $config = [])
    {
        return new class($config) {
            use MLAutoTranslate;

            public $form = null;
            public $useGroups = false;
            public $groupDefinitions = [];

            public function __construct(array $config = [])
            {
                $this->form = $config['form'] ?? null;
                $this->useGroups = $config['useGroups'] ?? false;
                $this->groupDefinitions = $config['groupDefinitions'] ?? [];
            }
        };
    }

    public function test_get_provider_config_returns_default_provider()
    {
        $translator = $this->createTranslator();

        Config::set('winter.translate::providers.google.url', 'https://fake-endpoint.com/translate');
        Config::set('winter.translate::providers.google.key', 'abc123');

        Http::fake([
            'https://fake-endpoint.com/*' => Http::response([
                'data' => [
                    'translations' => [
                        [
                            'translatedText' => 'Hello world'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $translator->translate(['Hola mundo'], 'en', 'es', 'google');

        $this->assertSame('Hello world', $result[0]);
    }

    public function test_get_provider_config_returns_named_provider()
    {
        $translator = $this->createTranslator();

        Config::set('winter.translate::providers.google.url', 'https://fake-endpoint.com/translate');
        Config::set('winter.translate::providers.google.key', 'google-key');

        Http::fake([
            'https://fake-endpoint.com/*' => Http::response([
                'data' => [
                    'translations' => [
                        [
                            'translatedText' => 'Hello world'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $translator->translate(['Hola mundo'], 'en', 'es', 'google');

        $this->assertSame('Hello world', $result[0]);
    }

    public function test_get_provider_config_throws_if_named_provider_not_found()
    {
        $translator = $this->createTranslator();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No provider found: ghost');

        $translator->translate(['Foo'], 'en', 'es', 'ghost');
    }

    public function test_it_translates_via_provider()
    {
        $translator = $this->createTranslator();
        Config::set('winter.translate::providers.google.url', 'https://fake-endpoint.com/translate');
        Config::set('winter.translate::providers.google.key', 'fakekey');

        Http::fake([
            'https://fake-endpoint.com/*' => Http::response([
                'data' => [
                    'translations' => [
                        [
                            'translatedText' => 'Hello world'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $translator->translate(['Hola mundo'], 'en', 'es', 'google');

        $this->assertSame('Hello world', $result[0]);
    }
    public function test_it_translates_multiple_via_provider()
    {
        $translator = $this->createTranslator();
        Config::set('winter.translate::providers.google.url', 'https://fake-endpoint.com/translate');
        Config::set('winter.translate::providers.google.key', 'fakekey');

        Http::fake([
            'https://fake-endpoint.com/*' => Http::response([
                'data' => [
                    'translations' => [
                        [
                            'translatedText' => 'Hello world'
                        ],
                        [
                            'translatedText' => 'Whats up?'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $translator->translate(['Bonjour monde', 'Ca va?'], 'en', 'es', 'google');

        $this->assertSame(['Hello world', 'Whats up?'], $result);
    }

    public function test_it_throws_when_no_provider_is_provided()
    {
        $translator = $this->createTranslator();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot translate without a provider');

        $translator->translate(['Hello'], 'en', 'es', '');
    }

    public function test_it_throws_when_request_fails()
    {
        $translator = $this->createTranslator();
        Config::set('winter.translate::providers.google.url', 'https://fake-endpoint.com/translate');
        Config::set('winter.translate::providers.google.key', 'fakekey');

        Http::fake([
            'https://fake-endpoint.com/*' => Http::response([
                'error' => 'Something went wrong',
            ], 500)
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Google Translation failed:');

        $translator->translate(['Hola'], 'en', 'es', 'google');
    }

    public function test_google_preserves_literal_percent()
    {
        $translator = $this->createTranslator();
        Config::set('winter.translate::providers.google.url', 'https://fake-endpoint.com/translate');
        Config::set('winter.translate::providers.google.key', 'fakekey');

        Http::fake([
            'https://fake-endpoint.com/*' => Http::response([
                'data' => ['translations' => [['translatedText' => '100% zeker, zie A/B']]],
            ], 200)
        ]);

        // A literal "%" (and "/") must survive: the provider only decodes HTML
        // entities, it must not urldecode the translated text.
        $result = $translator->translate(['100% sure, see A/B'], 'nl', 'en', 'google');
        $this->assertSame('100% zeker, zie A/B', $result[0]);
    }

    public function test_auto_translate_array_returns_unchanged_when_nothing_translatable()
    {
        // No sub-field declares translatable:true, so the values are copied
        // verbatim (no throw, no provider call).
        $translator = $this->createTranslator(['form' => ['fields' => [
            'name'    => ['type' => 'text'],
            'content' => ['type' => 'textarea'],
        ]]]);

        $data = ['0' => ['name' => 'Foo', 'content' => 'Bar']];
        $result = $translator->autoTranslateArray($data, 'nl', 'en', 'google');
        $this->assertSame($data, $result);
    }

    public function test_auto_translate_array_translates_only_flagged_fields()
    {
        Config::set('winter.translate::providers.google.url', 'https://fake-endpoint.com/translate');
        Config::set('winter.translate::providers.google.key', 'fakekey');

        Http::fake([
            'https://fake-endpoint.com/*' => Http::response([
                'data' => ['translations' => [
                    ['translatedText' => 'Naam NL'],
                    ['translatedText' => 'Inhoud NL'],
                ]],
            ], 200)
        ]);

        // Only name + description opt in; the mediafinder value is copied as-is.
        $translator = $this->createTranslator(['form' => ['fields' => [
            'image'       => ['type' => 'mediafinder'],
            'name'        => ['type' => 'text', 'translatable' => true],
            'description' => ['type' => 'textarea', 'translatable' => true],
        ]]]);

        $data = [
            ['image' => 'photo.jpg', 'name' => 'Name EN', 'description' => 'Desc EN'],
        ];

        $result = $translator->autoTranslateArray($data, 'nl', 'en', 'google');

        $this->assertSame('photo.jpg', $result[0]['image']);
        $this->assertSame('Naam NL', $result[0]['name']);
        $this->assertSame('Inhoud NL', $result[0]['description']);
    }

    public function test_get_auto_translatable_fields_derives_from_field_definitions()
    {
        // translatable:true opts a field in; nested form fields are collected
        // recursively; "@context" suffixes are stripped; duplicate names de-duped.
        $translator = $this->createTranslator(['form' => ['fields' => [
            'image'        => ['type' => 'mediafinder'],
            'title@update' => ['type' => 'text', 'translatable' => true],
            'body'         => ['type' => 'textarea', 'translatable' => true],
            'contacts'     => [
                'type' => 'nestedform',
                'form' => ['fields' => [
                    'label' => ['type' => 'text', 'translatable' => true],
                    'phone' => ['type' => 'text'],
                    'body'  => ['type' => 'textarea', 'translatable' => true], // duplicate name
                ]],
            ],
        ]]]);

        $fields = $translator->getAutoTranslatableFields();
        sort($fields);
        $this->assertSame(['body', 'label', 'title'], $fields);
    }

    public function test_get_auto_translatable_fields_collects_group_fields()
    {
        // Repeater groups / blocks: fields are merged across every group.
        $translator = $this->createTranslator([
            'useGroups' => true,
            'groupDefinitions' => [
                'block_a' => ['fields' => [
                    'heading' => ['type' => 'text', 'translatable' => true],
                    'image'   => ['type' => 'mediafinder'],
                ]],
                'block_b' => ['fields' => [
                    'caption' => ['type' => 'text', 'translatable' => true],
                ]],
            ],
        ]);

        $fields = $translator->getAutoTranslatableFields();
        sort($fields);
        $this->assertSame(['caption', 'heading'], $fields);
    }

    public function test_get_auto_translatable_fields_collects_groups_sharing_field_names()
    {
        // Groups routinely reuse the same field name (e.g. a shared `data`
        // nestedform); each group must be scanned separately so later groups
        // are not dropped by a keyed merge.
        $translator = $this->createTranslator([
            'useGroups' => true,
            'groupDefinitions' => [
                'block_a' => ['fields' => [
                    'data' => [
                        'type' => 'nestedform',
                        'form' => ['fields' => [
                            'heading' => ['type' => 'text', 'translatable' => true],
                        ]],
                    ],
                ]],
                'block_b' => ['fields' => [
                    'data' => [
                        'type' => 'nestedform',
                        'form' => ['fields' => [
                            'caption' => ['type' => 'text', 'translatable' => true],
                        ]],
                    ],
                ]],
            ],
        ]);

        $fields = $translator->getAutoTranslatableFields();
        sort($fields);
        $this->assertSame(['caption', 'heading'], $fields);
    }

    public function test_get_auto_translatable_fields_collects_tab_organized_nested_forms()
    {
        // A nested form may organize its fields under form.tabs.fields /
        // form.secondaryTabs.fields instead of form.fields.
        $translator = $this->createTranslator(['form' => ['fields' => [
            'data' => [
                'type' => 'nestedform',
                'form' => [
                    'tabs' => ['fields' => [
                        'heading' => ['type' => 'text', 'translatable' => true],
                    ]],
                    'secondaryTabs' => ['fields' => [
                        'caption' => ['type' => 'text', 'translatable' => true],
                    ]],
                ],
            ],
        ]]]);

        $fields = $translator->getAutoTranslatableFields();
        sort($fields);
        $this->assertSame(['caption', 'heading'], $fields);
    }

    public function test_flatten_and_expand_object()
    {
        $translator = $this->createTranslator();
        $whitelist = [
            'does_not_work',
            'name',
            'content',
            'works',
            'value',
        ];

        $data =  [
            [
                'is_delayed' => '0',
                'trigger' => [
                    [
                        'works' => '0',
                        'myForm' => [
                            'does_not_work'  => '1',
                            'does_not_work1' => '2',
                            'does_not_work2' => '3',
                            'does_not_work3' => '4',
                            '1does_not_work4' => '5' // it should only translate keys with trailing numbers when defined in whitelist
                        ],
                    ],
                ],
                '_group' => 'block_complex',
            ],

            [
                'value' => '6',
                '_group' => 'block_single'
            ],
        ];

        $flattened = $translator->flatten($data, $whitelist);
        $this->assertSame($flattened, ['0', '1', '2', '3', '4', '6']);
        $expanded = $translator->expand($flattened, $data, $whitelist);
        $this->assertSame($expanded, $data);
    }
    public function test_flatten_and_expand_object_sparse()
    {
        $translator = $this->createTranslator();
        $whitelist = [
            'does_not_work',
            'name',
            'content',
            'works',
            'value',
        ];

        $data =  [
            [
                'is_delayed' => '0',
                'trigger' => [
                    [
                        'works' => '0',
                        'myForm' => [
                            'does_not_work'  => '',
                            'does_not_work1' => '2',
                            'does_not_work2' => '3',
                            'does_not_work3' => '4',
                        ],
                    ],
                ],
                '_group' => 'block_complex',
            ],

            [
                'value' => '5',
                '_group' => 'block_single'
            ],
        ];

        $flattened = $translator->flatten($data, $whitelist);
        $this->assertSame($flattened, ['0', '', '2', '3', '4', '5']);
        $expanded = $translator->expand($flattened, $data, $whitelist);
        $this->assertSame($expanded, $data);
    }
    public function test_flatten_and_expand_object_simple()
    {
        $translator = $this->createTranslator();
        $whitelist = [
            'does_not_work',
            'name',
            'content',
            'works',
            'value',
        ];

        $data = [
            [
                'data' => [
                    'name'    => 'Bonjour',
                    'content' => '',
                ],
            ],
        ];

        $flattened = $translator->flatten($data, $whitelist);
        $this->assertSame($flattened, ['Bonjour', '']);
        $expanded = $translator->expand($flattened, $data, $whitelist);
        $this->assertSame($expanded, $data);
    }
    public function test_flatten_and_expand_array_simple()
    {
        $translator = $this->createTranslator();
        $whitelist = [
            'does_not_work',
            'name',
            'content',
            'works',
            'value',
        ];

        $data = [
            'name'    => 'Bonjour',
            'content' => 'Content',
        ];

        $flattened = $translator->flatten($data, $whitelist);
        $this->assertSame($flattened, ['Bonjour', 'Content']);
        $expanded = $translator->expand($flattened, $data, $whitelist);
        $this->assertSame($expanded, $data);
    }
}
