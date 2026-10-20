<?php namespace Winter\Translate\Tests\Unit;

use App;
use Event;
use stdClass;

/**
 * Winter\Pages\Classes\Menu resolves a menu item's URL from its reference for
 * every type but "url", which owns its URL. The localized fields applied on
 * pages.menu.referencesGenerated must not undo that.
 */
class MenuItemLocalizationTest extends \Winter\Translate\Tests\TranslatePluginTestCase
{
    public function testLocalizedTitleIsApplied()
    {
        $item = $this->makeItem('cms-page', '/localized/contact', [
            'title' => 'Contact (localized)',
        ]);

        $this->generateReferences($item);

        $this->assertEquals('Contact (localized)', $item->title);
    }

    public function testLocalizedUrlIsAppliedToAUrlItem()
    {
        $item = $this->makeItem('url', '/contact', [
            'url' => '/localized/contact',
        ]);

        $this->generateReferences($item);

        $this->assertEquals('/localized/contact', $item->url);
    }

    public function testLocalizedUrlIsIgnoredWhenTheUrlComesFromAReference()
    {
        $item = $this->makeItem('cms-page', '/localized/contact', [
            'title' => 'Contact (localized)',
            'url' => '/stale/contact',
        ]);

        $this->generateReferences($item);

        $this->assertEquals('/localized/contact', $item->url);
        $this->assertEquals('Contact (localized)', $item->title);
    }

    public function testNestedItemsAreTreatedTheSameWay()
    {
        $child = $this->makeItem('static-page', '/localized/about', [
            'url' => '/stale/about',
        ]);
        $parent = $this->makeItem('url', '/contact', [], [$child]);

        $this->generateReferences($parent);

        $this->assertEquals('/localized/about', $child->url);
    }

    /**
     * A stand-in for Winter\Pages\Classes\MenuItemReference, so the test does
     * not require Winter.Pages to be installed.
     */
    protected function makeItem(string $type, string $url, array $localeFields, array $children = []): stdClass
    {
        $item = new stdClass;
        $item->type = $type;
        $item->title = 'Contact';
        $item->url = $url;
        $item->items = $children;
        $item->viewBag = ['locale' => [App::getLocale() => $localeFields]];

        return $item;
    }

    protected function generateReferences(stdClass $item): void
    {
        $items = [$item];

        Event::fire('pages.menu.referencesGenerated', [&$items]);
    }
}
