<?php

namespace Winter\Translate\Providers;

interface TranslationProvider
{
    /**
     * @param string[] $input
     * @return string[] translated output
     */
    public function translate(array $input, string $targetLocale, string $currentLocale): array;
}
