<?php

namespace Winter\Translate\FormWidgets;

use Backend\Classes\FormWidgetBase;

/**
 * ML Textarea
 * Renders a multi-lingual textarea field.
 *
 * @package winter\translate
 * @author Alexey Bobkov, Samuel Georges
 */
class MLTextarea extends FormWidgetBase
{
    use \Winter\Translate\Traits\MLControl;

    /**
     * {@inheritDoc}
     */
    protected $defaultAlias = 'mltextarea';

    /**
     * @var string If translation is unavailable, fall back to this standard field.
     */
    const FALLBACK_TYPE = 'textarea';

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->initLocale();
    }

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        $this->prepareLocaleVars();

        if ($this->isAvailable) {
            return $this->makePartial('mltextarea');
        } else {
            return $this->renderFallbackField();
        }
    }

    /**
     * Returns an array of translated values for this field
     * @return array
     */
    public function getSaveValue($value)
    {
        return $this->getLocaleSaveValue($value);
    }

    /**
     * {@inheritDoc}
     */
    protected function loadAssets()
    {
        $this->loadLocaleAssets();
        $this->addJs('js/mltextarea.js');
    }

}
