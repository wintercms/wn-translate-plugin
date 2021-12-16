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
        $should_match = [
            "{{ 'hello'|_ }}}}",
            "{{{{ 'hello'|_ }}",
            "{{ 'hello'|_ }}",
            "{{ \"hello\"|_ }}",
            "{{ \"'hello\"|_ }}",
            "{{ '\"hello'|_ }}",
            "{{ 'hello'|__ }}",
            "{{ 'hello'|transRaw }}",
            "{{ 'hello'|transRawPlural }}",
            "{{ 'hello'|localeUrl }}",
            "{{ 'hello'|_() }}",
            "{{ 'hello'|_(func()) }}",
            "{{ 'hello'|_({var: val}) }}",
            "{{ 'hello'|_({var: func(param)}) }}",
            "{{ 'hello'|_({var: func(nestedFunc())}) }}",
        ];

        $should_not_match = [
            "{ 'hello'|_ }",
            "{{ var|_ }}",
            "{{ 'hello'|var|_ }}",
            "{{ 'hello\"|_ }}",
            "{{ \"hello'|_ }}",
            "{{ 'hello'|_()) }}",
        ];

        $scanner = new MessageScanner();

        foreach ($should_match as $string) {
            $this->assertTrue($scanner->doesStringMatch($string));
        }

        foreach ($should_not_match as $string) {
            $this->assertFalse($scanner->doesStringMatch($string));
        }
    }
}
