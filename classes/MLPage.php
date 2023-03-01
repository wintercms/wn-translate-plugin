<?php

namespace Winter\Translate\Classes;

use Cms\Classes\Page as CmsPage;
use Url;
use Winter\Pages\Classes\Page as StaticPage;
use Winter\Storm\Router\Router;
use Winter\Translate\Models\Locale;

class MLPage extends CmsPage
{
    public static function resolveMenuItem($item, $url, $theme)
    {
        $result = [];
        $locales = Locale::listEnabled();

        if ($item->type === 'cms-page') {
            if (!$item->reference) {
                return;
            }
            if (!$page = CmsPage::loadCached($theme, $item->reference)) {
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

        } elseif ($item->type == 'static-page') {
            if (!$item->reference) {
                return;
            }
            if (!$page = StaticPage::find($item->reference)) {
                return;
            }

            $alternateLinks = [];
            foreach ($locales as $locale => $name) {
                if ($pageUrl = static::getMLStaticPageUrl($page, $locale)) {
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

        } elseif ($item->type == 'all-static-pages') {
            if (empty($pages = StaticPage::all())) {
                return;
            }
            $recordItems = [];
            foreach ($pages as $page) {
                $alternateLinks = [];
                foreach ($locales as $locale => $name) {
                    if ($pageUrl = static::getMLStaticPageUrl($page, $locale)) {
                        $alternateLinks[$locale] = Url::to($pageUrl);
                    }
                }

                foreach ($alternateLinks as $locale => $link) {
                    $recordItems['items'][] = [
                        'url' => $link,
                        'mtime' => $page->mtime,
                        'alternateLinks' => $alternateLinks,
                    ];
                }
            }
            $result[] = $recordItems;
        }
        return $result;
    }

    protected static function getMLPageUrl($page, $locale)
    {
        $translator = Translator::instance();
        $translator->setLocale($locale);

        $page->rewriteTranslatablePageUrl($locale);
        $url = $translator->getPathInLocale($page->url, $locale);

        return (new Router)->urlFromPattern($url);
    }

    protected static function getMLStaticPageUrl($page, $locale)
    {
        $translator = Translator::instance();
        $translator->setLocale($locale);

        $page->rewriteTranslatablePageUrl($locale);
        $url = $translator->getPathInLocale(array_get($page->attributes, 'viewBag.url'), $locale);

        return (new Router)->urlFromPattern($url);
    }
}
