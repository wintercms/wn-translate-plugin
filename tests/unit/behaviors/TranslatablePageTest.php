<?php namespace Winter\Translate\Tests\Unit\Behaviors;

use File;
use Winter\Storm\Halcyon\Model;
use Winter\Storm\Filesystem\Filesystem;
use Winter\Storm\Halcyon\Datasource\FileDatasource;
use Winter\Storm\Halcyon\Datasource\Resolver;
use Winter\Translate\Tests\Fixtures\Classes\MessageScanner;
use Winter\Translate\Tests\Fixtures\Classes\TranslatablePage;

class TranslatablePageTest extends \Winter\Translate\Tests\TranslatePluginTestCase
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

        parent::tearDown();
    }

    /**
     * @since 2.1.5
     */
    public function testUseFallbackFalse()
    {
        $page = TranslatablePage::create([
            'fileName' => 'translatable',
            'title' => 'english title',
            'url' => '/test',
        ]);
        $page->translateContext('fr');
        $this->assertEquals('english title', $page->title);
        $page->setTranslatableUseFallback(false)->translateContext('fr');
        $this->assertEquals(null, $page->title);
    }

    /**
     * @deprecated 2.1.5
     * @see testUseFallbackFalse()
     */
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
            ["{{ 'hello1'|_ }}}}", ['hello1']],
            ["{{{{ 'hello2'|_ }}", ['hello2']],
            ["{{ 'hello3'|_ }}", ['hello3']],
            ["{{ \"hello4\"|_ }}", ['hello4']],
            ["{{ \"'hello5\"|_ }}", ['\'hello5']],
            ["{{ '\"hello6'|_ }}", ['"hello6']],
            ["{{ 'hello7'|__ }}", ['hello7']],
            ["{{ 'hello8'|transRaw }}", ['hello8']],
            ["{{ 'hello9'|transRawPlural }}", ['hello9']],
            ["{{ 'hello10'|localeUrl }}", ['hello10']],
            ["{{ 'hello11'|_() }}", ['hello11']],
            ["{{ 'hello12'|_(func()) }}", ['hello12']],
            ["{{ 'hello13'|_({var: val}) }}", ['hello13']],
            ["{{ 'hello14'|_({var: func(param)}) }}", ['hello14']],
            ["{{ 'hello15'|_({var: func(nestedFunc())}) }}", ['hello15']],
            ["{{ 'hello16'|_|filter }}", ['hello16']],
            ["{{ 'hello17'|_|filter|otherfilter }}", ['hello17']],
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
