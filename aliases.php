<?php

use Winter\Storm\Support\ClassLoader;

/**
 * To allow compatibility with plugins that extend the original RainLab.Translate plugin, this will alias those classes to
 * use the new Winter.Translate classes.
 */
$aliases = [
    // Reverse alias to fix issue on PHP 7.2, see https://github.com/wintercms/wn-user-plugin/runs/2122181184
    'RainLab\Pages\Classes\Page'                                    => Winter\Pages\Classes\Page::class,

    // Regular aliases
    Winter\Translate\Plugin::class                                  => RainLab\Translate\Plugin::class,
    Winter\Translate\Classes\EventRegistry::class                   => RainLab\Translate\Classes\EventRegistry::class,
    Winter\Translate\Classes\LocaleMiddleware::class                => RainLab\Translate\Classes\LocaleMiddleware::class,
    Winter\Translate\Classes\MLCmsObject::class                     => RainLab\Translate\Classes\MLCmsObject::class,
    Winter\Translate\Classes\MLContent::class                       => RainLab\Translate\Classes\MLContent::class,
    Winter\Translate\Classes\MLStaticPage::class                    => RainLab\Translate\Classes\MLStaticPage::class,
    Winter\Translate\Classes\ThemeScanner::class                    => RainLab\Translate\Classes\ThemeScanner::class,
    Winter\Translate\Classes\TranslatableBehavior::class            => RainLab\Translate\Classes\TranslatableBehavior::class,
    Winter\Translate\Classes\Translator::class                      => RainLab\Translate\Classes\Translator::class,
    Winter\Translate\Components\AlternateHrefLangElements::class    => RainLab\Translate\Components\AlternateHrefLangElements::class,
    Winter\Translate\Components\LocalePicker::class                 => RainLab\Translate\Components\LocalePicker::class,
    Winter\Translate\Models\Attribute::class                        => RainLab\Translate\Models\Attribute::class,
    Winter\Translate\Models\Locale::class                           => RainLab\Translate\Models\Locale::class,
    Winter\Translate\Models\Message::class                          => RainLab\Translate\Models\Message::class,
    Winter\Translate\Models\MessageExport::class                    => RainLab\Translate\Models\MessageExport::class,
    Winter\Translate\Models\MessageImport::class                    => RainLab\Translate\Models\MessageImport::class,
    Winter\Translate\Controllers\Locales::class                     => RainLab\Translate\Controllers\Locales::class,
    Winter\Translate\Controllers\Messages::class                    => RainLab\Translate\Controllers\Messages::class,
];

app(ClassLoader::class)->addAliases($aliases);

