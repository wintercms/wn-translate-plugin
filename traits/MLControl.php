<?php

namespace Winter\Translate\Traits;

use Str;
use Winter\Storm\Html\Helper as HtmlHelper;
use Winter\Translate\Models\Locale;

/**
 * Generic ML Control
 * Renders a multi-lingual control.
 *
 * @package winter\translate
 * @author Alexey Bobkov, Samuel Georges
 */
trait MLControl
{
    /**
     * @var boolean Determines whether translation services are available
     */
    public $isAvailable;

    /**
     * @var string Stores the original asset path when acting as the parent control
     */
    public $originalAssetPath;

    /**
     * @var string Stores the original view path when acting as the parent control
     */
    public $originalViewPath;

    /**
     * @var Winter\Translate\Models\Locale Object
     */
    protected $defaultLocale;

    /**
     * Initialize control
     * @return void
     */
    public function initLocale()
    {
        $this->defaultLocale = Locale::getDefault();
        $this->isAvailable = Locale::isAvailable();
    }

    /**
     * Returns the parent control's view path
     *
     * @return string
     */
    protected function getParentViewPath()
    {
        // return base_path().'/modules/backend/formwidgets/parentcontrol/partials';
    }

    /**
     * Returns the parent control's asset path
     *
     * @return string
     */
    protected function getParentAssetPath()
    {
        // return '/modules/backend/formwidgets/parentcontrol/assets';
    }

    /**
     * Swap the asset & view paths with the parent control's to
     * act as the parent control
     *
     * @param boolean $switch Defaults to true, determines whether to act as the parent or revert to current
     */
    protected function actAsParent($switch = true)
    {
        if ($switch) {
            $this->originalAssetPath = $this->assetPath;
            $this->originalViewPath = $this->viewPath;
            $this->assetPath = $this->getParentAssetPath();
            $this->viewPath = $this->getParentViewPath();
        }
        else {
            $this->assetPath = $this->originalAssetPath;
            $this->viewPath = $this->originalViewPath;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function renderFallbackField()
    {
        return $this->makeMLPartial('fallback_field');
    }

    /**
     * Used by child classes to render in context of this view path.
     * @param string $partial The view to load.
     * @param array $params Parameter variables to pass to the view.
     * @return string The view contents.
     */
    public function makeMLPartial($partial, $params = [])
    {
        $oldViewPath = $this->viewPath;
        $this->viewPath = $this->guessViewPathFrom(__TRAIT__, '/partials');
        $result = $this->makePartial($partial, $params);
        $this->viewPath = $oldViewPath;

        return $result;
    }

    /**
     * {@deprecated} 1.4.1 Replaced by makeMLPartial
     */
    public function makeParentPartial($partial, $params = [])
    {
        traceLog('Method makeParentPartial has been deprecated, use makeMLPartial instead.');
        return $this->makeMLPartial($partial, $params);
    }

    /**
     * Prepares the list data
     */
    public function prepareLocaleVars()
    {
        $this->vars['defaultLocale'] = $this->defaultLocale;
        $this->vars['locales'] = Locale::listAvailable();
        $this->vars['field'] = $this->makeRenderFormField();
    }

    /**
     * Loads assets specific to ML Controls
     */
    public function loadLocaleAssets()
    {
        $this->addJs('/plugins/winter/translate/assets/js/multilingual.js', 'Winter.Translate');
        $this->addCss('/plugins/winter/translate/assets/css/multilingual.css', 'Winter.Translate');
    }

    /**
     * Returns a translated value for a given locale.
     * @param  string $locale
     * @return string
     */
    public function getLocaleValue($locale)
    {
        $key = $this->valueFrom ?: $this->fieldName;

        /*
         * Get the translated values from the model
         */
        $studKey = Str::studly(implode(' ', HtmlHelper::nameToArray($key)));
        $mutateMethod = 'get'.$studKey.'AttributeTranslated';

        if ($this->objectMethodExists($this->model, $mutateMethod)) {
            $value = $this->model->$mutateMethod($locale);
        }
        elseif ($this->objectMethodExists($this->model, 'getAttributeTranslated') && $this->defaultLocale->code != $locale) {
            $value = $this->model->setTranslatableUseFallback(false)->getAttributeTranslated($key, $locale);
        }
        else {
            $value = $this->formField->value;
        }

        return $value;
    }

    /**
     * If translation is unavailable, render the original field type (text).
     */
    protected function makeRenderFormField()
    {
        if ($this->isAvailable) {
            return $this->formField;
        }

        $field = clone $this->formField;
        $field->type = $this->getFallbackType();

        return $field;
    }

    /**
     * {@inheritDoc}
     */
    public function getLocaleSaveValue($value)
    {
        $localeData = $this->getLocaleSaveData();
        $key = $this->valueFrom ?: $this->fieldName;

        /*
         * Set the translated values to the model
         */
        $studKey = Str::studly(implode(' ', HtmlHelper::nameToArray($key)));
        $mutateMethod = 'set'.$studKey.'AttributeTranslated';

        if ($this->objectMethodExists($this->model, $mutateMethod)) {
            foreach ($localeData as $locale => $value) {
                $this->model->$mutateMethod($value, $locale);
            }
        }
        elseif ($this->objectMethodExists($this->model, 'setAttributeTranslated')) {
            foreach ($localeData as $locale => $value) {
                $this->model->setAttributeTranslated($key, $value, $locale);
            }
        }

        return array_get($localeData, $this->defaultLocale->code, $value);
    }

    /**
     * Returns an array of translated values for this field
     * @return array
     */
    public function getLocaleSaveData()
    {
        $values = [];
        $data = post('RLTranslate');

        if (!is_array($data)) {
            return $values;
        }

        $fieldName = implode('.', HtmlHelper::nameToArray($this->fieldName));
        $isJson = $this->isLocaleFieldJsonable();

        foreach ($data as $locale => $_data) {
            $value = array_get($_data, $fieldName);
            $values[$locale] = $isJson ? json_decode($value, true) : $value;
        }

        return $values;
    }

    /**
     * Returns the fallback field type.
     * @return string
     */
    public function getFallbackType()
    {
        return defined('static::FALLBACK_TYPE') ? static::FALLBACK_TYPE : 'text';
    }

    /**
     * Returns true if widget is a repeater, or the field is specified
     * as jsonable in the model.
     * @return bool
     */
    public function isLocaleFieldJsonable()
    {
        if (
            $this instanceof \Backend\FormWidgets\Repeater ||
            $this instanceof \Backend\FormWidgets\NestedForm
        ) {
            return true;
        }

        if (
            method_exists($this->model, 'isJsonable') &&
            $this->model->isJsonable($this->fieldName)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Internal helper for method existence checks.
     *
     * @param  object $object
     * @param  string $method
     * @return boolean
     */
    protected function objectMethodExists($object, $method)
    {
        if (method_exists($object, 'methodExists')) {
            return $object->methodExists($method);
        }

        return method_exists($object, $method);
    }

    /**
     * Ensures any datatable form widget nested inside this multilingual widget posts
     * its client-memory data during the locale switch/copy AJAX requests, not only on
     * the form's save handler. Without this the datatable's values are dropped when the
     * locale changes, since the Table widget only serialises its data for handlers
     * listed in its `postbackHandlerName` (default `onSave`).
     *
     * This injects this widget's namespaced `onSwitchItemLocale` / `onCopyItemLocale`
     * handlers into the `postbackHandlerName` of every nested datatable field, relying
     * on the Table widget's list support (winter/winter#560).
     *
     * Must run before the inner form is built (i.e. before parent::init()).
     *
     * @see https://github.com/wintercms/wn-translate-plugin/issues/33
     * @return void
     */
    protected function registerLocaleDatatableHandlers()
    {
        if (!isset($this->config)) {
            return;
        }

        $handlers = [
            $this->getEventHandler('onSwitchItemLocale'),
            $this->getEventHandler('onCopyItemLocale'),
        ];

        if (isset($this->config->form)) {
            $this->config->form = $this->addLocaleDatatableHandlers(
                $this->normalizeFormConfig($this->config->form),
                $handlers
            );
        }

        if (isset($this->config->groups)) {
            $groups = $this->normalizeFormConfig($this->config->groups);
            foreach ($groups as $code => $group) {
                if (is_array($group)) {
                    $groups[$code] = $this->addLocaleDatatableHandlers($group, $handlers);
                }
            }
            $this->config->groups = $groups;
        }
    }

    /**
     * Resolves a form/groups config (inline array, config object, or `$/path` reference)
     * into a plain array so its field definitions can be inspected and modified.
     *
     * @param mixed $config
     * @return array
     */
    protected function normalizeFormConfig($config)
    {
        if (is_string($config) && $config !== '') {
            $config = $this->makeConfig($config);
        }

        if (is_object($config)) {
            $config = json_decode(json_encode($config), true);
        }

        return is_array($config) ? $config : [];
    }

    /**
     * Appends the given AJAX handlers to the `postbackHandlerName` of every datatable
     * field within a form config, descending into nested form definitions.
     *
     * @param array $form
     * @param string[] $handlers
     * @return array
     */
    protected function addLocaleDatatableHandlers(array $form, array $handlers)
    {
        if (isset($form['fields']) && is_array($form['fields'])) {
            $form['fields'] = $this->applyLocaleDatatableHandlers($form['fields'], $handlers);
        }

        foreach (['tabs', 'secondaryTabs'] as $tabKey) {
            if (isset($form[$tabKey]['fields']) && is_array($form[$tabKey]['fields'])) {
                $form[$tabKey]['fields'] = $this->applyLocaleDatatableHandlers($form[$tabKey]['fields'], $handlers);
            }
        }

        return $form;
    }

    /**
     * @param array $fields
     * @param string[] $handlers
     * @return array
     */
    protected function applyLocaleDatatableHandlers(array $fields, array $handlers)
    {
        foreach ($fields as $name => $config) {
            if (!is_array($config)) {
                continue;
            }

            if (($config['type'] ?? null) === 'datatable') {
                $existing = $config['postbackHandlerName'] ?? 'onSave';
                $list = is_array($existing)
                    ? $existing
                    : array_map('trim', explode(',', (string) $existing));
                $config['postbackHandlerName'] = implode(',', array_values(array_unique(
                    array_merge(array_filter($list), $handlers)
                )));
            }

            // Descend into nested form definitions (nested form / repeater sub-fields).
            if (isset($config['form']) && is_array($config['form'])) {
                $config['form'] = $this->addLocaleDatatableHandlers($config['form'], $handlers);
            }

            $fields[$name] = $config;
        }

        return $fields;
    }
}
