<?php

namespace Winter\Translate;

use Backend;
use Backend\Models\UserRole;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Models\ThemeData;
use Event;
use Lang;
use Request;
use System\Classes\CombineAssets;
use System\Classes\PluginBase;
use System\Classes\PluginManager;
use System\Models\File;
use Winter\Translate\Classes\EventRegistry;
use Winter\Translate\Classes\MLPage;
use Winter\Translate\Classes\Translator;
use Winter\Translate\Models\Message;

/**
 * Translate Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'winter.translate::lang.plugin.name',
            'description' => 'winter.translate::lang.plugin.description',
            'author'      => 'Winter CMS',
            'icon'        => 'icon-language',
            'homepage'    => 'https://github.com/wintercms/wn-translate-plugin',
            'replaces'    => ['RainLab.Translate' => '<= 1.9.0'],
        ];
    }

    /**
     * Registers the components provided by this plugin.
     */
    public function registerComponents(): array
    {
        return [
           \Winter\Translate\Components\LocalePicker::class => 'localePicker',
           \Winter\Translate\Components\AlternateHrefLangElements::class => 'alternateHrefLangElements'
        ];
    }

    /**
     * Registers the permissions provided by this plugin.
     */
    public function registerPermissions(): array
    {
        return [
            'winter.translate.manage_locales'  => [
                'tab'   => 'winter.translate::lang.plugin.tab',
                'label' => 'winter.translate::lang.plugin.manage_locales',
                'roles' => [UserRole::CODE_DEVELOPER],
            ],
            'winter.translate.manage_messages' => [
                'tab'   => 'winter.translate::lang.plugin.tab',
                'label' => 'winter.translate::lang.plugin.manage_messages',
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
            ]
        ];
    }

    /**
     * Registers the settings provided by this plugin
     */
    public function registerSettings(): array
    {
        return [
            'locales' => [
                'label'       => 'winter.translate::lang.locale.title',
                'description' => 'winter.translate::lang.plugin.description',
                'icon'        => 'icon-language',
                'url'         => Backend::url('winter/translate/locales'),
                'order'       => 550,
                'category'    => 'winter.translate::lang.plugin.name',
                'permissions' => ['winter.translate.manage_locales']
            ],
            'messages' => [
                'label'       => 'winter.translate::lang.messages.title',
                'description' => 'winter.translate::lang.messages.description',
                'icon'        => 'icon-list-alt',
                'url'         => Backend::url('winter/translate/messages'),
                'order'       => 551,
                'category'    => 'winter.translate::lang.plugin.name',
                'permissions' => ['winter.translate.manage_messages']
            ]
        ];
    }

    /**
     * Register Twig extensions provided by this plugin
     */
    public function registerMarkupTags(): array
    {
        return [
            'filters' => [
                '_'  => function ($string, $params = [], $locale = null) {
                    return Message::trans($string, $params, $locale);
                },
                '__' => function ($string, $count = 0, $params = [], $locale = null) {
                    return Lang::choice(Message::trans($string, $params, $locale), $count, $params);
                },
                'transRaw'  => function ($string, $params = [], $locale = null) {
                    return Message::transRaw($string, $params, $locale);
                },
                'transRawPlural' => function ($string, $count = 0, $params = [], $locale = null) {
                    return Lang::choice(Message::transRaw($string, $params, $locale), $count, $params);
                },
                'localeUrl' => function ($url, $locale) {
                    $translator = Translator::instance();
                    $parts = parse_url($url);
                    $path = array_get($parts, 'path');
                    return http_build_url($parts, [
                        'path' => '/' . $translator->getPathInLocale($path, $locale)
                    ]);
                },
            ]
        ];
    }

    /**
     * Registers FormWidgets provided by this plugin
     */
    public function registerFormWidgets(): array
    {
        return [
            \Winter\Translate\FormWidgets\MLText::class => 'mltext',
            \Winter\Translate\FormWidgets\MLTextarea::class => 'mltextarea',
            \Winter\Translate\FormWidgets\MLRichEditor::class => 'mlricheditor',
            \Winter\Translate\FormWidgets\MLMarkdownEditor::class => 'mlmarkdowneditor',
            \Winter\Translate\FormWidgets\MLRepeater::class => 'mlrepeater',
            \Winter\Translate\FormWidgets\MLMediaFinder::class => 'mlmediafinder',
        ];
    }

    /**
     * Registers Asset Combiner bundles provided by this plugin
     */
    protected function registerAssetBundles()
    {
        CombineAssets::registerCallback(function ($combiner) {
            $combiner->registerBundle('$/winter/translate/assets/less/messages.less');
            $combiner->registerBundle('$/winter/translate/assets/less/multilingual.less');
        });
    }

    /**
     * Registers the plugin
     */
    public function register(): void
    {
        /*
         * Register console commands
         */
        $this->registerConsoleCommand('translate.scan', \Winter\Translate\Console\ScanCommand::class);

        $this->registerAssetBundles();
    }

    /**
     * Boots the plugin
     */
    public function boot(): void
    {
        $this->extendBackendModule();
        $this->extendCmsModule();
        $this->extendSystemModule();
        $this->extendWinterPagesPlugin();
        $this->extendWinterSitemapPlugin();
    }

    /**
     * Extends the Backend module with translation support
     */
    protected function extendBackendModule(): void
    {
        // Defer event with low priority to let others contribute before this registers.
        Event::listen('backend.form.extendFieldsBefore', function ($widget) {
            EventRegistry::instance()->registerFormFieldReplacements($widget);
        }, -1);
    }

    /**
     * Extends the CMS module with translation support
     */
    protected function extendCmsModule(): void
    {
        /*
         * Handle translated page URLs
         */
        Page::extend(function($model) {
            $this->extendModel($model, ['title', 'description', 'meta_title', 'meta_description']);
        });

        /*
         * Add translation support to theme settings
         */
        ThemeData::extend(function ($model) {
            $this->extendModel($model);

            $model->bindEvent('model.afterFetch', function() use ($model) {
                foreach ($model->getFormFields() as $id => $field) {
                    if (!empty($field['translatable'])) {
                        $model->translatable[] = $id;
                    }
                }
            });
        });

        // Look at session for locale using middleware
        \Cms\Classes\CmsController::extend(function($controller) {
            $controller->middleware(\Winter\Translate\Classes\LocaleMiddleware::class);
        });

        // Set the page context for translation caching with high priority.
        Event::listen('cms.page.init', function($controller, $page) {
            EventRegistry::instance()->setMessageContext($page);
        }, 100);

        // Import messages defined by the theme
        Event::listen('cms.theme.setActiveTheme', function($code) {
            EventRegistry::instance()->importMessagesFromTheme();
        });

        // Adds language suffixes to content files.
        Event::listen('cms.page.beforeRenderContent', function($controller, $fileName) {
            return EventRegistry::instance()
                ->findTranslatedContentFile($controller, $fileName)
            ;
        });
    }

    /**
     * Extends the System module with translation support
     */
    protected function extendSystemModule(): void
    {
        // Add translation support to file models
        File::extend(function ($model) {
            $this->extendModel($model, ['title', 'description']);
        });

        // Load localized version of mail templates (akin to localized CMS content files)
        Event::listen('mailer.beforeAddContent', function ($mailer, $message, $view, $data, $raw, $plain) {
            return EventRegistry::instance()->findLocalizedMailViewContent($mailer, $message, $view, $data, $raw, $plain);
        }, 1);
    }

    /**
     * Extends the Winter.Pages plugin with translation support
     */
    protected function extendWinterPagesPlugin(): void
    {
        // Populate MenuItem properties with localized values if available
        Event::listen('pages.menu.referencesGenerated', function (&$items) {
            $locale = $this->app->getLocale();
            $iterator = function ($menuItems) use (&$iterator, $locale) {
                $result = [];
                foreach ($menuItems as $item) {
                    $localeFields = array_get($item->viewBag, "locale.$locale", []);
                    foreach ($localeFields as $fieldName => $fieldValue) {
                        if ($fieldValue) {
                            $item->$fieldName = $fieldValue;
                        }
                    }
                    if ($item->items) {
                        $item->items = $iterator($item->items);
                    }
                    $result[] = $item;
                }
                return $result;
            };
            $items = $iterator($items);
        });

        // Prune localized content files from template list
        Event::listen('pages.content.templateList', function($widget, $templates) {
            return EventRegistry::instance()
                ->pruneTranslatedContentTemplates($templates)
            ;
        });

        /**
         * Append current locale to static page's cache keys
         */
        $modifyKey = function (&$key) {
            $key = $key . '-' . Lang::getLocale();
        };
        Event::listen('pages.router.getCacheKey', $modifyKey);
        Event::listen('pages.page.getMenuCacheKey', $modifyKey);
        Event::listen('pages.snippet.getMapCacheKey', $modifyKey);
        Event::listen('pages.snippet.getPartialMapCacheKey', $modifyKey);

        if (class_exists('\Winter\Pages\Classes\SnippetManager')) {
            $handler = function ($controller, $template, $type) {
                if (!$template->methodExists('getDirtyLocales')) {
                    return;
                }

                // Get the locales that have changed
                $dirtyLocales = $template->getDirtyLocales();

                if (!empty($dirtyLocales)) {
                    $theme = Theme::getEditTheme();
                    $currentLocale = Lang::getLocale();

                    foreach ($dirtyLocales as $locale) {
                        if (!$template->isTranslateDirty(null, $locale)) {
                            continue;
                        }

                        // Clear the Winter.Pages caches for each dirty locale
                        $this->app->setLocale($locale);
                        \Winter\Pages\Classes\Page::clearMenuCache($theme);
                        \Winter\Pages\Classes\SnippetManager::clearCache($theme);
                    }

                    // Restore the original locale for this request
                    $this->app->setLocale($currentLocale);
                }
            };

            Event::listen('cms.template.save', $handler);
            Event::listen('pages.object.save', $handler);
        }
    }

    /**
     * Extend the Winter.Sitemap plugin
     */
    protected function extendWinterSitemapPlugin(): void
    {
        $pluginManager = PluginManager::instance();
        if (!$pluginManager->exists('Winter.Sitemap')) {
            return;
        }

        $typeMapping = [
            MLPage::class => ['cms-page'],
        ];

        if ($pluginManager->exists('Winter.Pages')) {
            $typeMapping[MLPage::class] = array_merge($typeMapping[MLPage::class], ['static-page', 'all-static-pages']);
        }

        if ($pluginManager->exists('Winter.Blog')) {
            $typeMapping[MLBlogCategoryModel::class] = ['blog-category', 'all-blog-categories'];
            $typeMapping[MLBlogPostModel::class] = ['blog-post', 'category-blog-posts', 'all-blog-posts'];
        }

        Event::listen('winter.sitemap.processMenuItems', function ($item, $url, $theme, $apiResult) use ($typeMapping) {
            foreach ($typeMapping as $class => $types) {
                if (in_array($item->type, $types)) {
                    return $class::resolveMenuItem($item, $url, $theme);
                }
            }

            return false;
        });

        Event::listen('winter.sitemap.makeUrlSet', function ($definition, $xml, $urlSet) {
            if (Request::has('preview')) {
                // hack to force browser to properly render the XML sitemap
                $nsUrl = 'xmlns:xhtml-namespace-definition-URL-here';
            } else {
                $nsUrl = 'http://www.w3.org/1999/xhtml';
            }
            $urlSet->setAttribute('xmlns:xhtml', $nsUrl);
        });

        Event::listen('winter.sitemap.makeUrlElement',
            function ($definition, $xml, $pageUrl, $lastModified, $itemDefinition, $itemInfo, $itemReference, $urlElement) {
                if (isset($itemInfo['alternateLinks'])) {
                    foreach ($itemInfo['alternateLinks'] as $locale => $altUrl) {
                        $linkElement = $xml->createElement('xhtml:link');
                        $linkElement->setAttribute('rel', 'alternate');
                        $linkElement->setAttribute('hreflang', $locale);
                        $linkElement->setAttribute('href', $altUrl);
                        $urlElement->appendChild($linkElement);
                    }
                }
            }
        );
    }

    /**
     * Helper method to extend the provided model with translation support
     */
    public function extendModel($model, array $translatableAttributes = [])
    {
        if (!$model->propertyExists('translatable')) {
            $model->addDynamicProperty('translatable', []);
        }
        $model->translatable = array_merge($model->translatable, $translatableAttributes);
        if (!$model->isClassExtendedWith('Winter\Translate\Behaviors\TranslatablePageUrl')) {
            $model->extendClassWith('Winter\Translate\Behaviors\TranslatablePageUrl');
        }
        if (!$model->isClassExtendedWith('Winter\Translate\Behaviors\TranslatablePage')) {
            $model->extendClassWith('Winter\Translate\Behaviors\TranslatablePage');
        }
    }
}
