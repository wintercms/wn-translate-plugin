<?php

namespace Winter\Translate\Traits;

use Exception;
use Winter\Translate\Providers\ProviderFactory;

/**
 * Used to intercept locale copy actions and auto translate
 * the response so it fits the target language
 */
trait MLAutoTranslate
{
    /**
     * Flatten nested widgets into a list of whitelisted values, preserving order.
     *
     * @param array $items
     * @param array $whitelist
     * @return array
     */
    public function flatten(array $items, array $whitelist): array
    {
        $result = [];

        $walk = function($array) use (&$walk, &$result, $whitelist) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $walk($value);
                } else {
                    $baseKey = preg_replace('/\d+$/', '', $key); // strip trailing numbers

                    if (in_array($baseKey, $whitelist, true)) {
                        $result[] = $value;
                    }
                }
            }
        };

        $walk($items);
        return $result;
    }

    /**
     * Rebuild the original structure replacing only whitelisted values in original order.
     *
     * @param array $flatValues
     * @param array $original
     * @param array $whitelist
     * @return array
     */
    public function expand(array $flatValues, array $original, array $whitelist): array
    {
        $idx = 0;

        $replace = function (&$array) use (&$replace, &$idx, $flatValues, $whitelist) {
            foreach ($array as $key => &$value) {
                if (is_array($value)) {
                    $replace($value);
                } else {
                    $baseKey = preg_replace('/\d+$/', '', $key); // strip trailing numbers

                    if (in_array($baseKey, $whitelist, true)) {
                        $value = $flatValues[$idx] ?? $value;
                        $idx++;
                    }
                }
            }
        };

        $copy = $original;
        $replace($copy);
        return $copy;
    }

    public function autoTranslateArray(array $copyFromValues, string $currentLocale, string $copyFromLocale, string $provider): array
    {
        $whitelist = $this->getAutoTranslatableFields();
        $flattenedValues = $this->flatten($copyFromValues, $whitelist);

        // No sub-field opted into translation on this widget: keep the plain copy
        // rather than failing. Nested translation is opt-in per field via
        // `translatable: true` in the field definition (see getAutoTranslatableFields).
        if (count($flattenedValues) === 0) {
            return $copyFromValues;
        }

        $translatedValues = $this->translate(
            $flattenedValues,
            $currentLocale,
            $copyFromLocale,
            $provider
        );

        return $this->expand($translatedValues, $copyFromValues, $whitelist);
    }

    public function onAutoTranslate()
    {
        $copyFromLocale = post('_copy_from_locale');
        $copyFromValue = post('_copy_from_value');
        $currentLocale = post('_current_locale');
        $provider = (string) post('_provider');

        if (!$copyFromLocale || !$currentLocale) {
            throw new Exception("Missing locale selection");
        }

        // Note: a literal "0" is valid translatable content, so only treat null/"" as empty.
        if ($copyFromValue === null || $copyFromValue === '') {
            throw new Exception("Nothing to translate");
        }

        $translated = $this->translate(
            [$copyFromValue],
            $currentLocale,
            $copyFromLocale,
            $provider
        );

        return [
            'translatedValue' => $translated,
            'translatedLocale' => $currentLocale
        ];
    }

    /**
     * Returns the names of the sub-fields that opt into machine translation.
     *
     * A composite widget's nested field is translated on copy when its field
     * definition declares `translatable: true`; every other value is copied
     * verbatim. Names are collected recursively (so nested repeater/nestedform/
     * blocks fields are included) and de-duplicated, since flatten()/expand()
     * match on the bare field name.
     *
     * @return string[]
     */
    public function getAutoTranslatableFields(): array
    {
        $names = [];

        // Repeater / Blocks group mode: scan every group separately — groups
        // routinely reuse the same field names (e.g. a shared `data` nestedform),
        // so merging their fields by key would drop all but the first group.
        if (!empty($this->useGroups) && !empty($this->groupDefinitions)) {
            foreach ($this->groupDefinitions as $group) {
                $this->collectTranslatableFieldNames((array) array_get($group, 'fields', []), $names);
            }
        } else {
            $this->collectTranslatableFieldNames($this->getTranslatableFieldDefinitions(), $names);
        }

        return array_values(array_unique($names));
    }

    /**
     * Returns the form field definitions to scan for `translatable: true`.
     *
     * Handles the single `form:` definition shape (repeater / nestedform);
     * group mode is handled in getAutoTranslatableFields. Non-widget
     * consumers get an empty set.
     *
     * @return array
     */
    protected function getTranslatableFieldDefinitions(): array
    {
        // Single form definition (repeater / nestedform).
        if (isset($this->form)) {
            return $this->resolveConfigFields($this->form);
        }

        return [];
    }

    /**
     * Normalises a `form` definition (inline array, config object, or a `$/path`
     * reference) into a flat `[name => config]` array of its fields.
     *
     * @param mixed $form
     * @return array
     */
    protected function resolveConfigFields($form): array
    {
        if (is_string($form) && !empty($form) && method_exists($this, 'makeConfig')) {
            $form = $this->makeConfig($form);
        }

        if (is_object($form)) {
            $form = json_decode(json_encode($form), true);
        }

        if (!is_array($form)) {
            return [];
        }

        if (isset($form['fields']) && is_array($form['fields'])) {
            return $form['fields'];
        }

        $fields = [];
        foreach (['tabs', 'secondaryTabs'] as $tabKey) {
            $tabFields = array_get($form, $tabKey . '.fields');
            if (is_array($tabFields)) {
                $fields += $tabFields;
            }
        }
        if ($fields) {
            return $fields;
        }

        // Assume a bare [name => config] fields array.
        return $form;
    }

    /**
     * Walks a set of field definitions, collecting the names of fields declared
     * `translatable: true`, descending into any nested form/group definitions.
     *
     * @param mixed $fields
     * @param string[] $names
     * @return void
     */
    protected function collectTranslatableFieldNames($fields, array &$names): void
    {
        if (!is_array($fields)) {
            return;
        }

        foreach ($fields as $name => $config) {
            if (!is_array($config)) {
                continue;
            }

            // Field names may carry a "@context" suffix (e.g. "title@update").
            if (is_string($name) && str_contains($name, '@')) {
                $name = explode('@', $name, 2)[0];
            }

            if (!empty($config['translatable'])) {
                $names[] = $name;
            }

            // Descend into nested composite widgets (repeater / nestedform).
            foreach ([
                'form.fields',
                'form.tabs.fields',
                'form.secondaryTabs.fields',
                'fields',
                'tabs.fields',
                'secondaryTabs.fields',
            ] as $childPath) {
                $this->collectTranslatableFieldNames(array_get($config, $childPath, []), $names);
            }

            // Descend into repeater/blocks group definitions.
            foreach ((array) array_get($config, 'groups', []) as $group) {
                $this->collectTranslatableFieldNames(array_get($group, 'fields', []), $names);
            }
        }
    }

    /**
     * @param string[] $input
     */
    public function translate($input, string $targetLocale, string $currentLocale, string $provider)
    {
        if (count($input) == 0) {
            throw new Exception("Cannot translate input of size 0");
        }
        if ($provider === '') {
            throw new Exception("Cannot translate without a provider");
        }

        $translator = ProviderFactory::create($provider);

        return $translator->translate($input, $targetLocale, $currentLocale);
    }
}
