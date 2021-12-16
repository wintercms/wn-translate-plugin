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
            ["{ 'hello'|_ }", 0],
            ["{{ var|_ }}", 0],
            ["{{ 'hello'|var|_ }}", 0],
            ["{{ 'hello\"|_ }}", 0],
            ["{{ \"hello'|_ }}", 0],
            ["{{ 'hello'|_()) }}", 0],

            // Should find 1 match
            ["{{ 'hello'|_ }}}}", 1],
            ["{{{{ 'hello'|_ }}", 1],
            ["{{ 'hello'|_ }}", 1],
            ["{{ \"hello\"|_ }}", 1],
            ["{{ \"'hello\"|_ }}", 1],
            ["{{ '\"hello'|_ }}", 1],
            ["{{ 'hello'|__ }}", 1],
            ["{{ 'hello'|transRaw }}", 1],
            ["{{ 'hello'|transRawPlural }}", 1],
            ["{{ 'hello'|localeUrl }}", 1],
            ["{{ 'hello'|_() }}", 1],
            ["{{ 'hello'|_(func()) }}", 1],
            ["{{ 'hello'|_({var: val}) }}", 1],
            ["{{ 'hello'|_({var: func(param)}) }}", 1],
            ["{{ 'hello'|_({var: func(nestedFunc())}) }}", 1],
            ["{{ 'hello'|_|filter }}", 1],
            ["{{ 'hello'|_|filter|otherfilter }}", 1],

            // Should find 2 matches
            ["{{ 'hello'|_|filter|otherfilter }}{{ 'hello'|_|filter|otherfilter }}", 2],
        ];

        foreach ($check_strings as $check) {
            $this->assertEquals($scanner->countMatches($check[0]), $check[1]);
        }
    }
}
