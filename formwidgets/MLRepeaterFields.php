<?php

namespace Winter\Translate\FormWidgets;

use ApplicationException;
use Backend\FormWidgets\Repeater;
use Request;
use Winter\Storm\Html\Helper as HtmlHelper;
use Winter\Translate\Models\Locale;

/**
 * ML Repeater Fields
 * Renders a repeater with multi-lingual fields.
 *
 * @package winter\translate
 * @author Marc Jauvin
 */
class MLRepeaterFields extends Repeater
{
    use \Winter\Translate\Traits\MLControl;

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mlrepeaterfields';

    public function init()
    {   
        parent::init();
        $this->initLocale();

        if ($this->model) {
            $this->model->extend(function () {
                $this->addDynamicMethod('getJsonAttributeTranslated', function ($key, $locale) {
                    $names = HtmlHelper::nameToArray($key);
                    array_shift($names); // remove model

                    $array = array_shift($names);
                    $field = array_pop($names);

                    if ($array && $field && $names) {
                        return array_get($this->{$array}, implode('.', $names) . '.locale' . ucfirst($field) . '.' . $locale);
                    }
                });
            });
        }
    }

    public function render()
    {
        $this->actAsParent();
        $parentContent = parent::render();
        $this->actAsParent(false);

        return $parentContent;
    }

    protected function loadAssets()
    {
        $this->actAsParent();
        parent::loadAssets();
        $this->actAsParent(false);
    }

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

    public function getSaveValue($value)
    {
        return $this->getLocaleSaveValue(is_array($value) ? array_values($value) : $value);
    }

    public function getLocaleSaveValue($value)
    {
        $fieldName = implode('.', HtmlHelper::nameToArray($this->formField->getName()));

        foreach (post('RLTranslate') as $locale => $_data) {
            $items = array_get($_data, $fieldName, []);
            foreach ($items as $index => $item) {
                foreach ($item as $field => $fieldValue) {
                    if ($locale === $this->defaultLocale->code) {
                        $value[$index][$field] = $fieldValue;
                    } else {
                        $key = sprintf("locale%s", ucfirst($field));
                        $value[$index][$key][$locale] = $fieldValue;
                    }
                }
            }
        }

        return $value;
    }
}
