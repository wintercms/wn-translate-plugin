<?php

# use reverse class_alias for external references to RainLab plugins
if (!class_exists(Winter\Pages\Classes\Page::class)) {
    class_alias(RainLab\Pages\Classes\Page::class, Winter\Pages\Classes\Page::class);
}


if (!class_exists(RainLab\Translate\Plugin::class)) {
    class_alias(Winter\Translate\Plugin::class, RainLab\Translate\Plugin::class);

    class_alias(Winter\Translate\Classes\EventRegistry::class, RainLab\Translate\Classes\EventRegistry::class);
    class_alias(Winter\Translate\Classes\LocaleMiddleware::class, RainLab\Translate\Classes\LocaleMiddleware::class);
    class_alias(Winter\Translate\Classes\MLCmsObject::class, RainLab\Translate\Classes\MLCmsObject::class);
    class_alias(Winter\Translate\Classes\MLContent::class, RainLab\Translate\Classes\MLContent::class);
    class_alias(Winter\Translate\Classes\MLStaticPage::class, RainLab\Translate\Classes\MLStaticPage::class);
    class_alias(Winter\Translate\Classes\ThemeScanner::class, RainLab\Translate\Classes\ThemeScanner::class);
    class_alias(Winter\Translate\Classes\TranslatableBehavior::class, RainLab\Translate\Classes\TranslatableBehavior::class);
    class_alias(Winter\Translate\Classes\Translator::class, RainLab\Translate\Classes\Translator::class);

    class_alias(Winter\Translate\Components\AlternateHrefLangElements::class, RainLab\Translate\Components\AlternateHrefLangElements::class);
    class_alias(Winter\Translate\Components\LocalePicker::class, RainLab\Translate\Components\LocalePicker::class);

    class_alias(Winter\Translate\Models\Attribute::class, RainLab\Translate\Models\Attribute::class);
    class_alias(Winter\Translate\Models\Locale::class, RainLab\Translate\Models\Locale::class);
    class_alias(Winter\Translate\Models\Message::class, RainLab\Translate\Models\Message::class);
    class_alias(Winter\Translate\Models\MessageExport::class, RainLab\Translate\Models\MessageExport::class);
    class_alias(Winter\Translate\Models\MessageImport::class, RainLab\Translate\Models\MessageImport::class);

    class_alias(Winter\Translate\Controllers\Locales::class, RainLab\Translate\Controllers\Locales::class);
    class_alias(Winter\Translate\Controllers\Messages::class, RainLab\Translate\Controllers\Messages::class);
}
