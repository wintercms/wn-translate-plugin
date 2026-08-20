<?php

namespace Winter\Translate\Providers;

use Exception;
use Illuminate\Support\Facades\Http;

class DeepLTranslateProvider extends AbstractTranslationProvider
{
    /**
     * @var int DeepL allows up to 50 text items per request.
     */
    protected int $batchLimit = 50;

    /**
     * @var string[] DeepL only accepts a region on these target languages;
     * every other target uses the base language code.
     */
    protected const REGIONAL_TARGETS = ['EN-GB', 'EN-US', 'PT-BR', 'PT-PT'];

    protected function translateBatch(array $input, string $targetLocale, string $currentLocale): array
    {
        $endpoint = rtrim($this->config['url'], '/');

        $payload = [
            'text'         => array_values($input),
            'target_lang'  => $this->normalizeTarget($targetLocale),
            'source_lang'  => $this->normalizeSource($currentLocale),
            'tag_handling' => 'html',
        ];

        $response = Http::timeout($this->timeout)->withHeaders([
            'Authorization' => 'DeepL-Auth-Key ' . $this->config['key'],
            'Content-Type'  => 'application/json',
        ])->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new Exception('DeepL Translation failed: HTTP ' . $response->status());
        }

        $json = $response->json();

        if (!isset($json['translations']) || !is_array($json['translations'])) {
            throw new Exception('DeepL Translation returned an unexpected response.');
        }

        return array_column($json['translations'], 'text');
    }

    /**
     * DeepL source languages use base codes only (e.g. "EN", never "EN-US").
     */
    protected function normalizeSource(string $locale): string
    {
        return strtoupper(explode('-', str_replace('_', '-', $locale))[0]);
    }

    /**
     * DeepL targets use the base code unless it's one of the supported regional variants.
     */
    protected function normalizeTarget(string $locale): string
    {
        $upper = strtoupper(str_replace('_', '-', $locale));

        return in_array($upper, self::REGIONAL_TARGETS, true)
            ? $upper
            : explode('-', $upper)[0];
    }
}
