<?php

namespace Winter\Translate\Classes;

use Cms\Classes\Controller;
use Cms\Classes\Page as CmsPage;
use Url;
use Winter\Storm\Router\Router;
use Winter\Translate\Models\Locale;

class MLPage
{
    public static function resolveMenuItem($item, $url, $theme)
    {
        $result = [];
        $locales = Locale::listEnabled();
        $defaultLocale = Locale::getDefault();

        if ($item->type === 'cms-page') {
            if (!$item->reference) {
                return;
            }
            if (!$page = CmsPage::loadCached($theme, $item->reference)) {
                return;
            }

            $controller = Controller::getController() ?: new Controller;
            $pageUrl = $controller->pageUrl($item->reference, [], false);

            $result = [
                'url' => $pageUrl,
                'isActive' => rtrim($pageUrl, '/') === rtrim($url, '/'),
                'mtime' => $page->mtime,
            ];

            $alternateLinks = [];
            foreach ($locales as $locale => $name) {
                if ($locale === $defaultLocale->code) {
                    $pageUrl = $result['url'];
                } else {
                    $pageUrl = static::getMLPageUrl($page, $locale);
                }
                if ($pageUrl) {
                    $alternateLinks[$locale] = Url::to($pageUrl);
                }
            }

            if ($alternateLinks) {
                $result['alternateLinks'] = $alternateLinks;
            }

            return $result;

        }
        return $result;
    }

    protected static function getMLPageUrl($page, $locale)
    {
        $translator = Translator::instance();

        $page->rewriteTranslatablePageUrl($locale);
        $url = $translator->getPathInLocale($page->url, $locale);

        return (new Router)->urlFromPattern($url);
    }
}
