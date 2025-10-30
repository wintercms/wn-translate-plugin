<?php

namespace Winter\Translate\Providers;

use Winter\Translate\Contracts\TranslationProvider;
use Exception;
use Illuminate\Support\Facades\Http;

class GoogleTranslateProvider implements TranslationProvider
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function translate(array $input, string $targetLocale, string $currentLocale): array
    {
        $query = http_build_query([
            'target' => $targetLocale,
            'source' => $currentLocale,
            'key'    => $this->config['key'],
        ]);

        foreach ($input as $text) {
            $query .= '&q=' . urlencode($text);
        }

        $endpoint = rtrim($this->config['url'], '/') . '?' . $query;

        $response = Http::get($endpoint);

        if (!$response->successful()) {
            throw new Exception("Google Translation failed: " . $response->body());
        }

        $json = $response->json();

        return array_map(fn($t) =>
            urldecode(html_entity_decode($t['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $json['data']['translations']
        );
    }
}
