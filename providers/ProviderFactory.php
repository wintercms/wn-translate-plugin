<?php

namespace Winter\Translate\Providers;

use Illuminate\Support\Facades\Config;

class ProviderFactory
{
    /**
     * Resolve a translation provider by its config key. The concrete class is
     * taken from config (winter.translate::providers.<name>.class), so adding a
     * provider is config-only and no request data reaches class instantiation.
     */
    /**
     * @var array<string, class-string> Built-in providers, used when a config
     * entry doesn't specify its own `class` (backwards compatibility).
     */
    protected static $builtIn = [
        'google' => GoogleTranslateProvider::class,
        'deepl'  => DeepLTranslateProvider::class,
    ];

    public static function create(string $provider): TranslationProvider
    {
        $config = Config::get("winter.translate::providers.$provider");

        if (!is_array($config)) {
            throw new \Exception("No provider found: $provider");
        }

        $class = $config['class'] ?? (static::$builtIn[$provider] ?? null);

        if (!$class || !class_exists($class) || !is_subclass_of($class, TranslationProvider::class)) {
            throw new \Exception("No provider found: $provider");
        }

        return new $class($config);
    }
}
