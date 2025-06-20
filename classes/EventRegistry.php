<?php

namespace Winter\Translate\Classes;

use App;
use Cms\Classes\Content;
use Cms\Classes\Page;
use Exception;
use File;
use Str;
use System\Classes\MailManager;
use System\Classes\PluginManager;
use Winter\Translate\Classes\ThemeScanner;
use Winter\Translate\Classes\Translator;
use Winter\Translate\Models\Locale as LocaleModel;
use Winter\Translate\Models\Message;

/**
 * Registrant class for bootstrapping events
 *
 * @package winter\system
 * @author Alexey Bobkov, Samuel Georges
 */
class EventRegistry
{
    use \Winter\Storm\Support\Traits\Singleton;

    //
    // Utility
    //

    public function registerFormFieldReplacements($widget)
    {
        // Replace with ML Controls for translatable attributes
        $this->registerModelTranslation($widget);

        // Handle URL translations
        $this->registerPageUrlTranslation($widget);

        // Handle Winter.Pages MenuItem translations
        if (PluginManager::instance()->exists('Winter.Pages')) {
            $this->registerMenuItemTranslation($widget);
        }
    }

    /**
     * Translate Winter.Pages MenuItem data
     *
     * @param Backend\Widgets\Form $widget
     * @return void
     */
    public function registerMenuItemTranslation($widget)
    {
        if ($widget->model instanceof \Winter\Pages\Classes\MenuItem) {
            $defaultLocale = LocaleModel::getDefault();
            $availableLocales = LocaleModel::listAvailable();
            $fieldsToTranslate = ['title', 'url'];

            // Replace specified fields with multilingual versions
            foreach ($fieldsToTranslate as $fieldName) {
                $widget->fields[$fieldName]['type'] = 'mltext';

                foreach ($availableLocales as $code => $locale) {
                    if (!$defaultLocale || $defaultLocale->code === $code) {
                        continue;
                    }

                    // Add data locker fields for the different locales under the `viewBag[locale]` property
                    $widget->fields["viewBag[locale][$code][$fieldName]"] = [
                        'cssClass' => 'hidden',
                        'attributes' => [
                            'data-locale' => $code,
                            'data-field-name' => $fieldName,
                        ],
                    ];
                }
            }
        }
    }

    //
    // Translate URLs
    //

    public function registerPageUrlTranslation($widget)
    {
        if (!$model = $widget->model) {
            return;
        }

        if (
            $model instanceof Page &&
            isset($widget->fields['settings[url]'])
        ) {
            $widget->fields['settings[url]']['type'] = 'mltext';
        }
        elseif (
            $model instanceof \Winter\Pages\Classes\Page &&
            isset($widget->fields['viewBag[url]'])
        ) {
            $widget->fields['viewBag[url]']['type'] = 'mltext';
        }
    }

    //
    // Translatable behavior
    //

    /**
     * Automatically replace form fields for multi lingual equivalents
     */
    public function registerModelTranslation($widget)
    {
        if (!$model = $widget->model) {
            return;
        }

        if (!method_exists($model, 'isClassExtendedWith')) {
            return;
        }

        if (
            !$model->isClassExtendedWith('Winter.Translate.Behaviors.TranslatableModel') &&
            !$model->isClassExtendedWith('Winter.Translate.Behaviors.TranslatablePage') &&
            !$model->isClassExtendedWith('Winter.Translate.Behaviors.TranslatableCmsObject')
        ) {
            return;
        }


        if (!$model->hasTranslatableAttributes() || $widget->isNested) {
            return;
        }

        if (!empty($widget->fields)) {
            $widget->fields = $this->processFormMLFields($widget->fields, $model);
        }

        if (!empty($widget->tabs['fields'])) {
            $widget->tabs['fields'] = $this->processFormMLFields($widget->tabs['fields'], $model);
        }

        if (!empty($widget->secondaryTabs['fields'])) {
            $widget->secondaryTabs['fields'] = $this->processFormMLFields($widget->secondaryTabs['fields'], $model);
        }
    }

    /**
     * Helper function to replace standard fields with multi lingual equivalents
     * @param  array $fields
     * @param  Model $model
     * @return array
     */
    protected function processFormMLFields($fields, $model)
    {
        $typesMap = [
            'blocks'      => 'mlblocks',
            'markdown'    => 'mlmarkdowneditor',
            'mediafinder' => 'mlmediafinder',
            'nestedform'  => 'mlnestedform',
            'repeater'    => 'mlrepeater',
            'richeditor'  => 'mlricheditor',
            'text'        => 'mltext',
            'textarea'    => 'mltextarea',
            'url'         => 'mlurl',
        ];

        $translatable = array_flip($model->getTranslatableAttributes());

        /*
         * Special: A custom field "markup_html" is used for Content templates.
         */
        if ($model instanceof Content && array_key_exists('markup', $translatable)) {
            $translatable['markup_html'] = true;
        }

        foreach ($fields as $name => $config) {
            $fieldName = $name;
            if (str_contains($name, '@')) {
                // apply to fields with any context
                list($fieldName, $context) = explode('@', $name);
            }
            if (!array_key_exists($fieldName, $translatable)) {
                continue;
            }

            $type = array_get($config, 'type', 'text');

            if (array_key_exists($type, $typesMap)) {
                $fields[$name]['type'] = $typesMap[$type];
            }
        }

        return $fields;
    }

    //
    // Theme
    //

    /**
     * Import messages defined by the theme
     */
    public function importMessagesFromTheme()
    {
        try {
            (new ThemeScanner)->scanThemeConfigForMessages();
        }
        catch (Exception $ex) {}
    }

    //
    // CMS objects
    //

    /**
     * Set the page context for translation caching.
     */
    public function setMessageContext($page)
    {
        if (!$page) {
            return;
        }

        $translator = Translator::instance();

        Message::setContext($translator->getLocale(), $page->url);
    }

    /**
     * Adds language suffixes to content files.
     * @return string|null
     */
    public function findTranslatedContentFile($controller, $fileName)
    {
        if (!strlen(File::extension($fileName))) {
            $fileName .= '.htm';
        }

        /*
         * Splice the active locale in to the filename
         * - content.htm -> content.en.htm
         */
        $locale = Translator::instance()->getLocale();
        $fileName = substr_replace($fileName, '.'.$locale, strrpos($fileName, '.'), 0);
        if (($content = Content::loadCached($controller->getTheme(), $fileName)) !== null) {
            return $content;
        }
    }

    //
    // Static pages
    //

    /**
     * Removes localized content files from templates collection
     * @param \Winter\Storm\Database\Collection $templates
     * @return \Winter\Storm\Database\Collection
     */
    public function pruneTranslatedContentTemplates($templates)
    {
        $locales = LocaleModel::listAvailable();

        $extensions = array_map(function($ext) {
            return '.'.$ext;
        }, array_keys($locales));

        return $templates->filter(function($template) use ($extensions) {
            return !Str::endsWith($template->getBaseFileName(), $extensions);
        });
    }

    /**
     * Adds language suffixes to mail view files.
     * @param  \Winter\Storm\Mail\Mailer $mailer
     * @param  \Illuminate\Mail\Message $message
     * @param  string $view
     * @param  array $data
     * @param  string $raw
     * @param  string $plain
     * @return bool|void Will return false if the translation process successfully replaced the original message with a translated version to prevent the original version from being processed.
     */
    public function findLocalizedMailViewContent($mailer, $message, $view, $data, $raw, $plain)
    {
        // Raw content cannot be localized at this level
        if (!empty($raw)) {
            return;
        }

        // Get the locale to use for this template
        $locale = !empty($data['_current_locale']) ? $data['_current_locale'] : App::getLocale();

        $factory = $mailer->getViewFactory();

        if (!empty($view)) {
            $view = $this->getLocalizedView($factory, $view, $locale);
        }

        if (!empty($plain)) {
            $plain = $this->getLocalizedView($factory, $plain, $locale);
        }

        $code = $view ?: $plain;
        if (empty($code)) {
            return null;
        }

        $plainOnly = empty($view);

        if (MailManager::instance()->addContentToMailer($message, $code, $data, $plainOnly)) {
            // the caller who fired the event is expecting a FALSE response to halt the event
            return false;
        }
    }


    /**
     * Search mail view files based on locale
     * @param  \Winter\Storm\Mail\Mailer $mailer
     * @param  \Illuminate\Mail\Message $message
     * @param  string $code
     * @param  string $locale
     * @return string|null
     */
    public function getLocalizedView($factory, $code, $locale)
    {
        $locale = strtolower($locale);

        $searchPaths[] = $locale;

        if (str_contains($locale, '-')) {
            list($lang) = explode('-', $locale);
            $searchPaths[] = $lang;
        }

        foreach ($searchPaths as $path) {
            // must use "-" as locale seperator since "." is used as path seperator for the view file location
            $localizedView = sprintf('%s-%s', $code, $path);

            if ($factory->exists($localizedView)) {
                return $localizedView;
            }
        }
        return null;
    }
}
