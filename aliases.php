<?php
/**
 * To allow compatibility with plugins that extend the original RainLab.Translate plugin, this will alias those classes to
 * use the new Winter.Translate classes.
 */
$aliases = [
    // Reverse alias to fix issue on PHP 7.2, see https://github.com/wintercms/wn-user-plugin/runs/2122181184
    'RainLab\Pages\Classes\Page'                                    => Winter\Pages\Classes\Page::class,

    // Regular aliases
    Winter\Translate\Plugin::class                                  => 'RainLab\Translate\Plugin',
    Winter\Translate\Classes\EventRegistry::class                   => 'RainLab\Translate\Classes\EventRegistry',
    Winter\Translate\Classes\LocaleMiddleware::class                => 'RainLab\Translate\Classes\LocaleMiddleware',
    Winter\Translate\Classes\MLCmsObject::class                     => 'RainLab\Translate\Classes\MLCmsObject',
    Winter\Translate\Classes\MLContent::class                       => 'RainLab\Translate\Classes\MLContent',
    Winter\Translate\Classes\MLStaticPage::class                    => 'RainLab\Translate\Classes\MLStaticPage',
    Winter\Translate\Classes\ThemeScanner::class                    => 'RainLab\Translate\Classes\ThemeScanner',
    Winter\Translate\Classes\TranslatableBehavior::class            => 'RainLab\Translate\Classes\TranslatableBehavior',
    Winter\Translate\Classes\Translator::class                      => 'RainLab\Translate\Classes\Translator',
    Winter\Translate\Components\AlternateHrefLangElements::class    => 'RainLab\Translate\Components\AlternateHrefLangElements',
    Winter\Translate\Components\LocalePicker::class                 => 'RainLab\Translate\Components\LocalePicker',
    Winter\Translate\Models\Attribute::class                        => 'RainLab\Translate\Models\Attribute',
    Winter\Translate\Models\Locale::class                           => 'RainLab\Translate\Models\Locale',
    Winter\Translate\Models\Message::class                          => 'RainLab\Translate\Models\Message',
    Winter\Translate\Models\MessageExport::class                    => 'RainLab\Translate\Models\MessageExport',
    Winter\Translate\Models\MessageImport::class                    => 'RainLab\Translate\Models\MessageImport',
    Winter\Translate\Controllers\Locales::class                     => 'RainLab\Translate\Controllers\Locales',
    Winter\Translate\Controllers\Messages::class                    => 'RainLab\Translate\Controllers\Messages',
];

foreach ($aliases as $original => $alias) {
    if (!class_exists($alias)) {
        class_alias($original, $alias);
    }
}
