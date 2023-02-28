<?php namespace Winter\Translate\Classes;

use Url;

use Cms\Classes\Page;

use Winter\Storm\Router\Router;
use Winter\Translate\Models\Locale;

class MLCmsPage extends Page
{
    public static function resolveMenuItem($item, $url, $theme)
    {
        $locales = Locale::listEnabled();

        if ($item->reference) {
            if (!$page = Page::loadCached($theme, $item->reference)) {
                return;
            }

            $alternateLinks = [];
            foreach ($locales as $locale => $name) {
                if ($pageUrl = static::getMLPageUrl($page, $locale)) {
                    $alternateLinks[$locale] = Url::to($pageUrl);
                }
            }

            foreach ($alternateLinks as $locale => $link) {
                $result[] = [ 
                    'url' => $link,
                    'mtime' => $page->mtime,
                    'alternateLinks' => $alternateLinks,
                ];
            }
            return $result;
        }
    }

    protected static function getMLPageUrl($page, $locale)
    {
        $translator = Translator::instance();
        $translator->setLocale($locale);

        $page->rewriteTranslatablePageUrl($locale);
        $url = $translator->getPathInLocale($page->url, $locale);

        return (new Router)->urlFromPattern($url);
    }
}
