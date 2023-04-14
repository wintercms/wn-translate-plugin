<?php

namespace Winter\Translate\Behaviors;

use Winter\Storm\Halcyon\Model as HalcyonModel;
use Winter\Translate\Classes\MLCmsObject;
use Winter\Translate\Classes\TranslatableBehavior;
use Winter\Translate\Models\Locale;

/**
 * Translatable CMS Object extension
 *
 * Usage:
 *
 * In the CMS object class definition:
 *
 *   public $implement = ['@Winter.Translate.Behaviors.TranslatableCmsObject'];
 *
 *   public $translatable = ['title', 'markup'];
 *
 * This definition is optional and defaults to Winter\Translate\Classes\MLCmsObject
 *
 *   public $translatableModel = 'Winter\Translate\Classes\MLStaticPage';
 *
 */
class TranslatableCmsObject extends TranslatableBehavior
{
    /**
     * @var array Data store for translated viewbag attributes.
     */
    protected $translatableViewBag = [];

    /**
     * Constructor
     * @param \Winter\Storm\Database\Model $model The extended model.
     */
    public function __construct($model)
    {
        parent::__construct($model);

        $this->model->bindEvent('cmsObject.fillViewBagArray', function() {
            $this->mergeViewBagAttributes();
        });

        $this->model->bindEvent('cmsObject.getTwigCacheKey', function($key) {
            return $this->overrideTwigCacheKey($key);
        });

        // delete all translation files associated with the default language static page
        $this->model->bindEvent('model.afterDelete', function() use ($model) {
            foreach (Locale::listEnabled() as $locale => $label) {
                if ($locale == $this->translatableDefault) {
                    continue;
                }
                if ($obj = $this->getCmsObjectForLocale($locale)) {
                    $obj->delete();
                }
            }
        });
    }

    /**
     * Merge the viewBag array for the base and translated objects.
     * @return void
     */
    protected function mergeViewBagAttributes()
    {
        $locale = $this->translatableContext;

        if (!array_key_exists($locale, $this->translatableAttributes)) {
            $this->loadTranslatableData($locale);
        }

        if (isset($this->translatableViewBag[$locale])) {
            $this->model->viewBag = array_merge(
                $this->model->viewBag,
                $this->translatableViewBag[$locale]
            );
        }
    }

    /**
     * Translated CMS objects need their own unique cache key in twig.
     * @return string|null
     */
    protected function overrideTwigCacheKey($key)
    {
        if (!$locale = $this->translatableContext) {
            return null;
        }

        return $key . '-' . $locale;
    }

    /**
     * {@inheritDoc}
     */
    public function syncTranslatableAttributes()
    {
        parent::syncTranslatableAttributes();

        if ($this->model->isDirty('fileName')) {
            $this->syncTranslatableFileNames();
        }
    }

    /**
     * If the parent model file name is changed, this should
     * be reflected in the translated models also.
     */
    protected function syncTranslatableFileNames()
    {
        $knownLocales = array_keys($this->translatableAttributes);
        foreach ($knownLocales as $locale) {
            if ($locale == $this->translatableDefault) {
                continue;
            }

            if ($obj = $this->getCmsObjectForLocale($locale)) {
                $obj->fileName = $this->model->fileName;
                $obj->forceSave();
            }
        }
    }

    /**
     * Saves the translation data in the join table.
     * @param  string $locale
     * @return void
     */
    protected function storeTranslatableData($locale = null)
    {
        if (!$locale) {
            $locale = $this->translatableContext;
        }

        /*
         * Model doesn't exist yet, defer this logic in memory
         */
        if (!$this->model->exists) {
            $this->model->bindEventOnce('model.afterCreate', function() use ($locale) {
                $this->storeTranslatableData($locale);
            });

            return;
        }

        $data = $this->translatableAttributes[$locale];

        if (!$obj = $this->getCmsObjectForLocale($locale)) {
            $model = $this->createModel();
            $obj = $model::forLocale($locale, $this->model);
            $obj->fileName = $this->model->fileName;
        }

        if (!$this->isEmptyDataSet($data)) {
            $obj->fill($data);
            $obj->forceSave();
        }
    }

    /**
     * Returns true if all attributes are empty (false when converted to booleans).
     * @param  array $data
     * @return bool
     */
    protected function isEmptyDataSet($data)
    {
        return !array_get($data, 'markup') &&
            !count(array_filter(array_get($data, 'viewBag', []))) &&
            !count(array_filter(array_get($data, 'placeholders', [])));
    }

    /**
     * Loads the translation data from the join table.
     * @param  string $locale
     * @return array
     */
    protected function loadTranslatableData($locale = null)
    {
        if (!$locale) {
            $locale = $this->translatableContext;
        }

        if (!$this->model->exists) {
            return $this->translatableAttributes[$locale] = [];
        }

        $obj = $this->getCmsObjectForLocale($locale);

        $result = $obj ? $obj->getAttributes() : [];

        $this->translatableViewBag[$locale] = $obj ? $obj->viewBag : [];

        return $this->translatableOriginals[$locale] = $this->translatableAttributes[$locale] = $result;
    }

    protected function getCmsObjectForLocale($locale)
    {
        if ($locale == $this->translatableDefault) {
            return $this->model;
        }

        $model = $this->createModel();
        return $model::findLocale($locale, $this->model);
    }

    /**
     * Internal method, prepare the form model object
     */
    protected function createModel(): HalcyonModel
    {
        $class = $this->getTranslatableModelClass();
        $model = new $class;
        return $model;
    }

    /**
     * Returns the translateable model class name.
     */
    public function getTranslatableModelClass(): string
    {
        if (property_exists($this->model, 'translatableModel')) {
            return $this->model->translatableModel;
        }

        return MLCmsObject::class;
    }
}
