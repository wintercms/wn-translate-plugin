<?php namespace Winter\Translate\FormWidgets;

use Backend\FormWidgets\NestedForm;
use Winter\Translate\Models\Locale;
use Winter\Storm\Html\Helper as HtmlHelper;
use ApplicationException;
use Request;

/**
 * ML NestedForm
 * Renders a multi-lingual nestedform field.
 *
 * @author Luke Towers
 */
class MLNestedForm extends NestedForm
{
    use \Winter\Translate\Traits\MLControl;

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mlnestedform';

    /**
     * The nestedform translation mode (default|fields)
     */
    protected $translationMode = 'default';

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->fillFromConfig(['translationMode']);

        // make the translationMode available to the nestedform formwidgets
        if (isset($this->config->form)) {
            $this->config->form['translationMode'] = $this->translationMode;
        }

        parent::init();
        $this->initLocale();

        if ($this->translationMode === 'fields' && $this->model) {
            $this->model->extend(function () {
                $this->addDynamicMethod('getJsonAttributeTranslated', function ($key, $locale) {
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

        $this->vars['nestedform'] = $parentContent;
        return $this->makePartial('mlnestedform');
    }

    public function prepareVars()
    {
        parent::prepareVars();
        if ($this->translationMode === 'default') {
            $this->prepareLocaleVars();
        }
    }

    /**
     * Returns an array of translated values for this field
     * @return array
     */
    public function getSaveValue($value)
    {
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
     * {@inheritDoc}
     */
    protected function loadAssets()
    {
        $this->actAsParent();
        parent::loadAssets();
        $this->actAsParent(false);

        if (Locale::isAvailable() && $this->translationMode === 'default') {
            $this->loadLocaleAssets();
            $this->addJs('js/mlnestedform.js');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getParentViewPath()
    {
        return base_path().'/modules/backend/formwidgets/nestedform/partials';
    }

    /**
     * {@inheritDoc}
     */
    protected function getParentAssetPath()
    {
        return '/modules/backend/formwidgets/nestedform/assets';
    }

    public function onSwitchItemLocale()
    {
        if (!$locale = post('_nestedform_locale')) {
            throw new ApplicationException('Unable to find a nestedform locale for: '.$locale);
        }

        // Store previous value
        $previousLocale = post('_nestedform_previous_locale');
        $previousValue = $this->getPrimarySaveDataAsArray();

        // Update widget to show form for switched locale
        $lockerData = $this->getLocaleSaveDataAsArray($locale) ?: [];

        $this->reprocessLocaleItems($lockerData);

        $this->formWidget->setFormValues($lockerData);

        $this->actAsParent();
        $parentContent = parent::render();
        $this->actAsParent(false);

        return [
            '#'.$this->getId('mlNestedForm') => $parentContent,
            'updateValue' => json_encode($previousValue),
            'updateLocale' => $previousLocale,
        ];
    }

    /**
     * Ensure that the current locale data is processed by the nestedform instead of the original non-translated data
     * @return void
     */
    protected function reprocessLocaleItems($data)
    {
        $this->formField->value = $data;

        $key = implode('.', HtmlHelper::nameToArray($this->formField->getName()));
        $requestData = Request::all();
        array_set($requestData, $key, $data);
        Request::merge($requestData);
    }

    /**
     * Gets the active values from the selected locale.
     * @return array
     */
    protected function getPrimarySaveDataAsArray()
    {
        $data = post($this->formField->getName()) ?: [];

        return $data;
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
     * will take the save data from the nestedform and merge it in to the
     * locker based on which ever locale is selected using an item map
     * @return void
     */
    protected function rewritePostValues()
    {
        /*
         * Get the selected locale at postback
         */
        $data = post('RLTranslateNestedFormLocale');
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
