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
        elseif ($this->defaultLocale->code != $locale && $this->isFieldParentJsonable() &&
                $this->objectMethodExists($this->model, 'WinterTranslateGetJsonAttributeTranslated')
        )
        {
            $value = $this->model->WinterTranslateGetJsonAttributeTranslated($this->formField->getName(), $locale);
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

    public function getLocaleFieldName($code)
    {
        $suffix = '';
        
        if ($this->isLongFormNeeded() && empty($this->formField->arrayName)) {
            $names = HtmlHelper::nameToArray($this->formField->arrayName);
            $suffix = '[' . implode('][', $names) . ']';
        }

        return $this->formField->getName('RLTranslate['.$code.']' . $suffix);
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

        $fieldName = $this->getLongFieldName();
        $isJson = $this->isLocaleFieldJsonable();

        foreach ($data as $locale => $_data) {
            $value = array_get($_data, $fieldName);
            $values[$locale] = $isJson && is_string($value) ? json_decode($value, true) : $value;
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

    public function isFieldParentJsonable()
    {
        $names = HtmlHelper::nameToArray($this->formField->arrayName);
        if (count($names) >= 2) {
            // $names[0] is the Model, $names[1] is the top array name
            $arrayName = $names[1];

            if ($this->model->isClassExtendedWith('System\Behaviors\SettingsModel') ||
                method_exists($this->model, 'isJsonable') && $this->model->isJsonable($arrayName)
            )
            {
                return true;
            }
        }
        return false;
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
     * determine if fieldName needs long form
     *
     * @return boolean
     */
    public function isLongFormNeeded()
    {
        $type = array_get($this->formField->config, 'type');
        $mode = array_get($this->formField->config, 'translationMode', 'default');

        return (!in_array($type, ['mlrepeater','mlnestedform']) || $mode === "fields");
    }

    /**
     * get the proper field name
     *
     * @return string
     */
    public function getLongFieldName()
    {
        if ($this->isLongFormNeeded()) {
            $fieldName = implode('.', HtmlHelper::nameToArray($this->formField->getName()));
        } else {
            $fieldName = implode('.', HtmlHelper::nameToArray($this->fieldName));
        }
        return $fieldName;
    }
}
