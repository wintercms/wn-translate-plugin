<?php

namespace Winter\Translate\Providers;

use Illuminate\Support\Facades\Config;
use Winter\Translate\Providers\GoogleTranslateProvider;
use Winter\Translate\Providers\DeepLTranslateProvider;
use Winter\Translate\Providers\TranslationProvider;

class ProviderFactory
{
    public static function create(string $provider): TranslationProvider
    {
        $config = Config::get("winter.translate::providers.$provider");

        if (!$config) {
            throw new \Exception("No provider found: $provider");
        }


        switch ($provider) {
            case 'google': return new GoogleTranslateProvider($config); break;
            case 'deepl': return new DeepLTranslateProvider($config); break;
            default: throw new \Exception("No provider found: $provider"); break;
        };
    }
}
