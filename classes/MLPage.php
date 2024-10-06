<?php

namespace Winter\Translate\Classes;

use Cms\Classes\Page as CmsPage;
use Cms\Classes\Theme;
use Url;
use Winter\Storm\Router\Router;
use Winter\Translate\Models\Locale;

class MLPage
{
    /**
     * Resolves a menu item to a CMS page with support for translated pages
     *
     * @see Cms\Classes\Page::resolveMenuItem()
     * @param \Winter\Sitemap\Classes\DefinitionItem|\Winter\Pages\Classes\MenuItem $item Specifies the menu item.
     */
    public static function resolveMenuItem(object $item, string $url, Theme $theme): ?array
    {
        $result = CmsPage::resolveMenuItem($item, $url, $theme);
        $locales = Locale::listEnabled();

        if (count($locales) > 1 && $result && ($page = CmsPage::loadCached($theme, $item->reference))) {
            $defaultLocale = Locale::getDefault();

            $alternateLinks = [];
            foreach ($locales as $locale => $name) {
                $pageUrl = static::getLocalizedPageUrl($page, $locale) ?: $result['url'];
                $alternateLinks[$locale] = Url::to($pageUrl);
            }

            if ($alternateLinks) {
                $result['alternateLinks'] = $alternateLinks;
            }
        }

        return $result;
    }

    /**
     * Gets the localized URL for the provided page
     */
    protected static function getLocalizedPageUrl(CmsPage $page, string $locale): string
    {
        $translator = Translator::instance();

        $page->rewriteTranslatablePageUrl($locale);
        $url = $translator->getPathInLocale($page->url, $locale);

        return (new Router)->urlFromPattern($url);
    }
}
