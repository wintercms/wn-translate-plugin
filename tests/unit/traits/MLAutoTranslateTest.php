<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Winter\Translate\Traits\MLAutoTranslate;

class MLAutoTranslateTest extends \Winter\Translate\Tests\TranslatePluginTestCase
{

    protected function createTranslator()
    {
        return new class {
            use MLAutoTranslate;
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
        Config::set('winter.translate::defaultProvider', 'google');

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
        Config::set('winter.translate::defaultProvider', 'google');

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
        Config::set('winter.translate::defaultProvider', 'google');

        Http::fake([
            'https://fake-endpoint.com/*' => Http::response([
                'error' => 'Something went wrong',
            ], 500)
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Google Translation failed:');

        $translator->translate(['Hola'], 'en', 'es', 'google');
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
