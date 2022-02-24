<?php namespace Winter\Translate\Tests\Unit\Behaviors;

use File;
use PluginTestCase;
use Winter\Storm\Halcyon\Model;
use Winter\Storm\Filesystem\Filesystem;
use Winter\Storm\Halcyon\Datasource\FileDatasource;
use Winter\Storm\Halcyon\Datasource\Resolver;
use Winter\Translate\Tests\Fixtures\Classes\MessageScanner;
use Winter\Translate\Tests\Fixtures\Classes\TranslatablePage;

class TranslatablePageTest extends PluginTestCase
{
    public $themePath;

    public function setUp(): void
    {
        parent::setUp();

        $this->themePath = __DIR__ . '/../../fixtures/themes/test';

        $datasource = new FileDatasource($this->themePath, new Filesystem);
        $resolver = new Resolver(['theme1' => $datasource]);
        $resolver->setDefaultDatasource('theme1');
        Model::setDatasourceResolver($resolver);

        TranslatablePage::extend(function($page) {
            if (!$page->isClassExtendedWith('Winter\Translate\Behaviors\TranslatablePage')) {
                $page->addDynamicProperty('translatable', ['title']);
                $page->extendClassWith('Winter\Translate\Behaviors\TranslatablePage');
            }
        });

    }

    public function tearDown(): void
    {
        File::deleteDirectory($this->themePath.'/pages');
    }

    public function testUseFallback()
    {
        $page = TranslatablePage::create([
            'fileName' => 'translatable',
            'title' => 'english title',
            'url' => '/test',
        ]);
        $page->translateContext('fr');
        $this->assertEquals('english title', $page->title);
        $page->noFallbackLocale()->translateContext('fr');
        $this->assertEquals(null, $page->title);
    }

    public function testAlternateLocale()
    {
        $page = TranslatablePage::create([
            'fileName' => 'translatable',
            'title' => 'english title',
            'url' => '/test',
        ]);
        $page->setAttributeTranslated('title', 'titre francais', 'fr');
        $title_en = $page->title;
        $this->assertEquals('english title', $title_en);
        $page->translateContext('fr');
        $title_fr = $page->title;
        $this->assertEquals('titre francais', $title_fr);
    }
    
    public function testThemeScanner()
    {
        $scanner = new MessageScanner();

        $check_strings = [
            // Should not match
            ["hello", []],
            ["'hello'|_", []],
            ["{ 'hello'|_ }", []],
            ["{{ var|_ }}", []],
            ["{{ 'hello'|upper|_ }}", []],

            // Code is syntactically wrong
            ["{{ 'hello\"|_ }}", []],
            ["{{ \"hello'|_ }}", []],

            // Should find 1 match
            ["{{ 'hello'|_ }}}}", ['hello']],
            ["{{{{ 'hello'|_ }}", ['hello']],
            ["{{ 'hello'|_ }}", ['hello']],
            ["{{ \"hello\"|_ }}", ['hello']],
            ["{{ \"'hello\"|_ }}", ['\'hello']],
            ["{{ '\"hello'|_ }}", ['"hello']],
            ["{{ 'hello'|__ }}", ['hello']],
            ["{{ 'hello'|transRaw }}", ['hello']],
            ["{{ 'hello'|transRawPlural }}", ['hello']],
            ["{{ 'hello'|localeUrl }}", ['hello']],
            ["{{ 'hello'|_() }}", ['hello']],
            ["{{ 'hello'|_(func()) }}", ['hello']],
            ["{{ 'hello'|_({var: val}) }}", ['hello']],
            ["{{ 'hello'|_({var: func(param)}) }}", ['hello']],
            ["{{ 'hello'|_({var: func(nestedFunc())}) }}", ['hello']],
            ["{{ 'hello'|_|filter }}", ['hello']],
            ["{{ 'hello'|_|filter|otherfilter }}", ['hello']],
            ["{{ 'Apostrophe\'s'|_ }}", ['Apostrophe\'s']],
            ['{{ "String with \"Double quote\""|_ }}', ['String with "Double quote"']],

            // Should find 2 matches
            [
                '{{ \'Apostrophe\\\'s\'|_ }}{{ "String with \"Double quote\""|_ }}',
                ['Apostrophe\'s', 'String with "Double quote"']
            ],
            [
                "{{ 'hello'|_|filter|otherfilter }}{{ 'hello'|_|filter|otherfilter }}",
                ['hello', 'hello']
            ],
            [
                "{{ 'hello'|transRaw('nested translation'|_) }}",
                ['hello', 'nested translation']
            ],
        ];

        foreach ($check_strings as $check) {
            $this->assertEquals($scanner->getMessages($check[0]), $check[1]);
        }
    }
}
