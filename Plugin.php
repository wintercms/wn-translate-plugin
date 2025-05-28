<?php

namespace Winter\Translate;

use Backend;
use Backend\Models\UserRole;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Models\ThemeData;
use DOMDocument;
use DOMElement;
use Event;
use Lang;
use Model;
use System\Classes\CombineAssets;
use System\Classes\PluginBase;
use System\Classes\PluginManager;
use System\Models\File;
use System\Models\MailTemplate;
use Winter\Sitemap\Classes\DefinitionItem;
use Winter\Sitemap\Models\Definition;
use Winter\Translate\Classes\EventRegistry;
use Winter\Translate\Classes\MLPage;
use Winter\Translate\Classes\Translator;
use Winter\Translate\Models\Locale;
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
            \Winter\Translate\FormWidgets\MLBlocks::class => 'mlblocks',
            \Winter\Translate\FormWidgets\MLMarkdownEditor::class => 'mlmarkdowneditor',
            \Winter\Translate\FormWidgets\MLMediaFinder::class => 'mlmediafinder',
            \Winter\Translate\FormWidgets\MLNestedForm::class => 'mlnestedform',
            \Winter\Translate\FormWidgets\MLRepeater::class => 'mlrepeater',
            \Winter\Translate\FormWidgets\MLRichEditor::class => 'mlricheditor',
            \Winter\Translate\FormWidgets\MLText::class => 'mltext',
            \Winter\Translate\FormWidgets\MLTextarea::class => 'mltextarea',
            \Winter\Translate\FormWidgets\MLUrl::class => 'mlurl',
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
        // Verify that the CMS module is installed and enabled before extending it
        if (!class_exists('\Cms\Classes\Page') || !in_array('Cms', config('cms.loadModules'))) {
            return;
        }
        
        /*
         * Handle translated page URLs
         */
        Page::extend(function($model) {
            $this->extendModel($model, 'page', ['title', 'description', 'meta_title', 'meta_description']);
        });

        /*
         * Add translation support to theme settings
         */
        ThemeData::extend(function ($model) {
            $model->bindEvent('model.afterFetch', function() use ($model) {
                $translatable = [];
                foreach ($model->getFormFields() as $id => $field) {
                    if (!empty($field['translatable'])) {
                        $translatable[] = $id;
                    }
                }
                $this->extendModel($model, 'model', $translatable);
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
            $this->extendModel($model, 'model', ['title', 'description']);
        });

        MailTemplate::extend(function ($model) {
            $this->extendModel($model, 'model', ['subject', 'description', 'content_html', 'content_text']);
        });

        // Load localized version of mail templates (akin to localized CMS content files)
        Event::listen('mailer.beforeAddContent', function ($mailer, $message, $view, $data, $raw, $plain) {
            if (!empty($data['_current_locale'])) {
                $translator = Translator::instance();
                $translator->setLocale($data['_current_locale']);
            }

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
     * ref. document: https://developers.google.com/search/blog/2012/05/multilingual-and-multinational-site
     */
    protected function extendWinterSitemapPlugin(): void
    {
        $pluginManager = PluginManager::instance();
        if (!$pluginManager->exists('Winter.Sitemap')) {
            return;
        }

        // Ensure that CMS Pages include their alternateLinks data when generating the sitemap
        Event::listen('pages.menuitem.resolveItem', function ($type, $item, $url, $theme) {
            if ($item->type === 'cms-page') {
                return MLPage::resolveMenuItem($item, $url, $theme);
            }
        }, 1);

        $defaultLocale = Locale::getDefault();
        Event::listen('winter.sitemap.addItem',
            function (DefinitionItem $item, array $itemInfo, Definition $definition, DOMDocument $xml, DOMElement $urlSet, DOMElement $urlElement) use ($defaultLocale) {
                if (isset($itemInfo['alternateLinks'])) {
                    foreach ($itemInfo['alternateLinks'] as $locale => $altUrl) {
                        $linkElement = $xml->createElement('xhtml:link');
                        $linkElement->setAttribute('rel', 'alternate');
                        $linkElement->setAttribute('hreflang', $locale);
                        $linkElement->setAttribute('href', $altUrl);
                        $urlElement->appendChild($linkElement);
                    }
                    foreach ($itemInfo['alternateLinks'] as $locale => $altUrl) {
                        if ($locale === $defaultLocale->code) {
                            $loc = $urlElement->getElementsByTagName('loc')->item(0);
                            $loc->nodeValue = $altUrl;
                            continue;
                        }
                        $newElement = $urlElement->cloneNode(true);
                        $loc = $newElement->getElementsByTagName('loc')->item(0);
                        $loc->nodeValue = $itemInfo['alternateLinks'][$locale];
                        $urlSet->appendChild($newElement);
                    }
                }
            }
        );
    }

    /**
     * Helper method to extend the provided model with translation support
     */
    public function extendModel($model, string $type, array $translatableAttributes = [])
    {
        if (!$model->propertyExists('translatable')) {
            $model->addDynamicProperty('translatable', $translatableAttributes);
        } else {
            $model->translatable = array_merge($model->translatable, $translatableAttributes);
        }

        if ($type === 'page') {
            if (!$model->isClassExtendedWith('Winter\Translate\Behaviors\TranslatablePageUrl')) {
                $model->extendClassWith('Winter\Translate\Behaviors\TranslatablePageUrl');
            }
            if (!$model->isClassExtendedWith('Winter\Translate\Behaviors\TranslatablePage')) {
                $model->extendClassWith('Winter\Translate\Behaviors\TranslatablePage');
            }
        } elseif ($type === 'model') {
            if (!$model->isClassExtendedWith('Winter\Translate\Behaviors\TranslatableModel')) {
                $model->extendClassWith('Winter\Translate\Behaviors\TranslatableModel');
            }
        }
    }
}
