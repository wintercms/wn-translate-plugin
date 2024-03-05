<?php

namespace Winter\Translate\FormWidgets;

use ApplicationException;
use Backend\FormWidgets\Repeater;
use Request;
use Winter\Storm\Html\Helper as HtmlHelper;
use Winter\Translate\Models\Locale;

/**
 * ML Repeater
 * Renders a multi-lingual repeater field.
 *
 * @package winter\translate
 * @author Alexey Bobkov, Samuel Georges
 */
class MLRepeater extends Repeater
{
    use \Winter\Translate\Traits\MLControl;

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mlrepeater';

    /**
     * The repeater translation mode (default|fields)
     */
    protected $translationMode = 'default';

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->fillFromConfig(['translationMode']);
        // make the translationMode available to the repeater items formwidgets
        if (isset($this->config->form)) {
            $this->config->form['translationMode'] = $this->translationMode;
        }

        parent::init();
        $this->initLocale();

        if ($this->translationMode === 'fields' && $this->model) {
            $this->model->extend(function () {
                $this->addDynamicMethod('WinterTranslateGetJsonAttributeTranslated', function ($key, $locale) {
                    $names = HtmlHelper::nameToArray($key);
                    array_shift($names); // remove model
                    if ($arrayName = array_shift($names)) {
                        return array_get($this->lang($locale)->{$arrayName}, implode('.', $names));
                    }
                });
            });
        }
    }

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        $this->actAsParent();
        $parentContent = parent::render();
        $this->actAsParent(false);

        if ($this->translationMode === 'fields' || !$this->isAvailable) {
            return $parentContent;
        }

        $this->vars['repeater'] = $parentContent;
        return $this->makePartial('mlrepeater');
    }

    public function prepareVars()
    {
        parent::prepareVars();
        if ($this->translationMode === 'default') {
            $this->prepareLocaleVars();
        }
    }

    // make the translationMode available to the repeater groups formwidgets
    protected function getGroupFormFieldConfig($code)
    {
        $config = parent::getGroupFormFieldConfig($code);
        $config['translationMode'] = $this->translationMode;

        return $config;
    }

    /**
     * Returns an array of translated values for this field
     * @return array
     */
    public function getSaveValue($value)
    {
        $value = is_array($value) ? array_values($value) : $value;

        if ($this->translationMode === 'fields') {
            $localeValue = $this->getLocaleSaveValue($value);
            $value = array_replace_recursive($value ?? [], $localeValue ?? []);
        } else {
            $this->rewritePostValues();
            $value = $this->getLocaleSaveValue($value);
        }
        return $value;
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
            if ($this->translationMode === 'fields') {
                foreach (Locale::listEnabled() as $code => $name) {
                    // force translations removal from db
                    $values[$code] = [];
                }
            }
            return $values;
        }

        if ($this->isLongFormNeeded()) {
            $fieldName = implode('.', HtmlHelper::nameToArray($this->formField->getName()));
        } else {
            $fieldName = implode('.', HtmlHelper::nameToArray($this->fieldName));
        }

        $isJson = $this->isLocaleFieldJsonable();

        foreach ($data as $locale => $_data) {
            $i = 0;
            $content = array_get($_data, $fieldName);
            if (is_array($content)) {
                foreach ($content as $index => $value) {
                    // we reindex to fix item reordering index issues
                    $values[$locale][$i++] = $value;
                }
            } else {
                $values[$locale] = $isJson && is_string($content) ? json_decode($content, true) : $content;
            }
        }

        return $values;
    }

    /**
     * {@inheritDoc}
     */
    protected function loadAssets()
    {
        $this->actAsParent();
        parent::loadAssets();
        $this->actAsParent(false);

        if (Locale::isAvailable() && $this->translationMode === 'default') {
            $this->loadLocaleAssets();
            $this->addJs('js/mlrepeater.js');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getParentViewPath()
    {
        return base_path().'/modules/backend/formwidgets/repeater/partials';
    }

    /**
     * {@inheritDoc}
     */
    protected function getParentAssetPath()
    {
        return '/modules/backend/formwidgets/repeater/assets';
    }

    public function onAddItem()
    {
        $this->actAsParent();
        return parent::onAddItem();
    }

    public function onSwitchItemLocale()
    {
        if (!$locale = post('_repeater_locale')) {
            throw new ApplicationException('Unable to find a repeater locale for: '.$locale);
        }

        // Store previous value
        $previousLocale = post('_repeater_previous_locale');
        $previousValue = $this->getPrimarySaveDataAsArray();

        // Update widget to show form for switched locale
        $lockerData = $this->getLocaleSaveDataAsArray($locale) ?: [];
        $this->reprocessLocaleItems($lockerData);

        foreach ($this->formWidgets as $key => $widget) {
            $value = array_shift($lockerData);
            if (!$value) {
                unset($this->formWidgets[$key]);
            }
            else {
                $widget->setFormValues($value);
            }
        }

        $this->actAsParent();
        $parentContent = parent::render();
        $this->actAsParent(false);

        return [
            '#'.$this->getId('mlRepeater') => $parentContent,
            'updateValue' => json_encode($previousValue),
            'updateLocale' => $previousLocale,
        ];
    }

    /**
     * Ensure that the current locale data is processed by the repeater instead of the original non-translated data
     * @return void
     */
    protected function reprocessLocaleItems($data)
    {
        $this->formWidgets = [];
        $this->formField->value = $data;

        $key = implode('.', HtmlHelper::nameToArray($this->formField->getName()));
        $requestData = Request::all();
        array_set($requestData, $key, $data);
        Request::merge($requestData);

        $this->processItems();
    }

    /**
     * Gets the active values from the selected locale.
     * @return array
     */
    protected function getPrimarySaveDataAsArray()
    {
        $data = post($this->formField->getName()) ?: [];

        return $this->processSaveValue($data);
    }

    /**
     * Returns the stored locale data as an array.
     * @return array
     */
    protected function getLocaleSaveDataAsArray($locale)
    {
        $saveData = array_get($this->getLocaleSaveData(), $locale, []);

        if (!is_array($saveData)) {
            $saveData = json_decode($saveData, true);
        }

        return $saveData;
    }

    /**
     * Since the locker does always contain the latest values, this method
     * will take the save data from the repeater and merge it in to the
     * locker based on which ever locale is selected using an item map
     * @return void
     */
    protected function rewritePostValues()
    {
        /*
         * Get the selected locale at postback
         */
        $data = post('RLTranslateRepeaterLocale');
        $fieldName = implode('.', HtmlHelper::nameToArray($this->fieldName));
        $locale = array_get($data, $fieldName);

        if (!$locale) {
            return;
        }

        /*
         * Splice the save data in to the locker data for selected locale
         */
        $data = $this->getPrimarySaveDataAsArray();
        $fieldName = 'RLTranslate.'.$locale.'.'.implode('.', HtmlHelper::nameToArray($this->fieldName));

        $requestData = Request::all();
        array_set($requestData, $fieldName, json_encode($data));
        Request::merge($requestData);
    }
}
