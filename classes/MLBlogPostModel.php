<?php

namespace Winter\Translate\Classes;

use Cms\Classes\Page;
use Url;
use Winter\Blog\Models\Category;
use Winter\Blog\Models\Post;
use Winter\Storm\Database\NestedTreeScope;
use Winter\Storm\Router\Router;
use Winter\Translate\Models\Locale;

class MLBlogPostModel extends Post
{
    public static function resolveMenuItem($item, $url, $theme)
    {
        $result = null;
        $locales = Locale::listEnabled();

        if ($item->type == 'blog-post') {
            if (!$item->reference || !$item->cmsPage) {
                return;
            }

            $record = self::find($item->reference);
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

        } elseif ($item->type == 'all-blog-posts') {
            $recordItems = [];

            $records = self::isPublished()->orderBy('title')->get();
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
        } elseif ($item->type == 'category-blog-posts') {
            $recordItems = [];

            if (!$item->reference || !$item->cmsPage) {
                return;
            }

            $category = Category::find($item->reference);
            if (!$category) {
                return;
            }

            $query = Post::isPublished()->orderBy('title');

            $categories = $category->getAllChildrenAndSelf()->lists('id');
            $query->whereHas('categories', function($q) use ($categories) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $categories);
            });

            $records = $query->get();

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
        $page = Page::loadCached($theme, $pageCode);
        if (!$page) {
            return;
        }

        $properties = $page->getComponentProperties('blogPost');
        if (!isset($properties['slug'])) {
            return;
        }

        /*
         * Extract the routing parameter name from the slug
         * eg: {{ :someRouteParam }}
         */
        if (!preg_match('/^\{\{([^\}]+)\}\}$/', $properties['slug'], $matches)) {
            return;
        }

        $paramName = substr(trim($matches[1]), 1);

        $record->translateContext($locale);
        $params = [
            $paramName => $record->slug,
            'year'  => $record->published_at->format('Y'),
            'month' => $record->published_at->format('m'),
            'day'   => $record->published_at->format('d'),
        ];

        $params = [$paramName => $record->slug];

        $translator = Translator::instance();
        $translator->setLocale($locale);

        $page->rewriteTranslatablePageUrl($locale);
        $url = $translator->getPathInLocale($page->url, $locale);

        return (new Router)->urlFromPattern($url, $params);
    }
}
