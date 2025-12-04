<?php

namespace Winter\Translate\Traits;
/*
* Used to intercept locale copy actions and auto translate
* the response so it fits the target language
*/

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Winter\Translate\Providers\ProviderFactory;

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

    public function autoTranslateArray($copyFromValues, $currentLocale, $copyFromLocale, $provider)
    {
        $whitelist = $this->getAutoTranslatableFields();
        $flattenedValues = $this->flatten($copyFromValues, $whitelist);
        if (count($flattenedValues) == 0) {
            throw new Exception("None of the fields on this widget are auto translatable, update config.autoTranslateWhiteList with the label of each input you wish to include, eg. name, content");
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
        $provider = post('_provider');

        if (!$copyFromLocale || !$currentLocale) {
            throw new Exception("Missing locale selection");
        }

        if (!$copyFromValue) {
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
    public function getProviderConfig($provider = "")
    {

        if ($provider == "") {
            $provider = Config::get('winter.translate::defaultProvider');
        }

        $providerConfig = Config::get('winter.translate::providers.' . $provider);
        if (!$providerConfig) {
            throw new Exception("No config for provider: " . $provider);
        }
        return $providerConfig;
    }

    public function getAutoTranslatableFields()
    {
        return Config::get("winter.translate::autoTranslateWhiteList", []);
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
