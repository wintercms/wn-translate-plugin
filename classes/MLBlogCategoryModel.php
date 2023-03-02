<?php

namespace Winter\Translate\Classes;

use Cms\Classes\Page;
use Url;
use Winter\Blog\Models\Category;
use Winter\Storm\Router\Router;
use Winter\Translate\Models\Locale;

class MLBlogCategoryModel
{
    public static function resolveMenuItem($item, $url, $theme)
    {
        $result = null;
        $locales = Locale::listEnabled();

        # TODO: add category nesting ($item->nesting)
        # ref. https://github.com/wintercms/wn-blog-plugin/blob/main/models/Category.php#L228-L252
        if ($item->type == 'blog-category') {
            if (!$item->reference || !$item->cmsPage) {
                return;
            }

            $record = Category::find($item->reference);
            if (!$record) {
                return;
            }

            $alternateLinks = [];
            foreach ($locales as $locale => $name) {
                if ($pageUrl = static::getMLPageUrl($item->cmsPage, $record, $theme, $locale)) {
                    $alternateLinks[$locale] = Url::to($pageUrl);
                }
            }

            foreach ($alternateLinks as $locale => $link) {
                $result[] = [
                    'url' => $link,
                    'mtime' => $record->updated_at,
                    'alternateLinks' => $alternateLinks,
                ];
            }

        } elseif ($item->type == 'all-blog-categories') {
            $recordItems = [];

            $records = Category::orderBy('name')->get();
            foreach ($records as $record) {
                $alternateLinks = [];
                foreach ($locales as $locale => $name) {
                    if ($pageUrl = static::getMLPageUrl($item->cmsPage, $record, $theme, $locale)) {
                        $alternateLinks[$locale] = Url::to($pageUrl);
                    }
                }

                foreach ($alternateLinks as $locale => $link) {
                    $recordItems['items'][] = [
                        'url' => $link,
                        'mtime' => $record->updated_at,
                        'alternateLinks' => $alternateLinks,
                    ];
                }
            }
            $result[] = $recordItems;
        }

        return $result;
    }

    protected static function getMLPageUrl($pageCode, $record, $theme, $locale)
    {
        if (!$page = Page::loadCached($theme, $pageCode)) {
            return;
        }

        $properties = $page->getComponentProperties('blogPosts');
        if (!isset($properties['categoryFilter'])) {
            return;
        }

        /*
         * Extract the routing parameter name from the category filter
         * eg: {{ :someRouteParam }}
         */
        if (!preg_match('/^\{\{([^\}]+)\}\}$/', $properties['categoryFilter'], $matches)) {
            return;
        }

        $paramName = substr(trim($matches[1]), 1);

        $translator = Translator::instance();
        $translator->setLocale($locale);

        $page->rewriteTranslatablePageUrl($locale);
        $url = $translator->getPathInLocale($page->url, $locale);

        $record->translateContext($locale);
        $params = [$paramName => $record->slug];

        return (new Router)->urlFromPattern($url, $params);
    }
}
