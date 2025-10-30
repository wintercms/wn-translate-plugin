<?php

namespace Winter\Translate;

use Illuminate\Support\Facades\Config;
use Winter\Translate\Providers\GoogleTranslateProvider;
use Winter\Translate\Providers\DeepLTranslateProvider;
use Winter\Translate\Contracts\TranslationProvider;

class ProviderFactory
{
    public static function create(string $provider): TranslationProvider
    {
        $config = Config::get("winter.translate::providers.$provider");

        if (!$config) {
            throw new \Exception("No provider found: $provider");
        }

        return match ($provider) {
            'google' => new GoogleTranslateProvider($config),
            'deepl'  => new DeepLTranslateProvider($config),
            default  => throw new \Exception("No provider found: $provider"),
        };
    }
}
