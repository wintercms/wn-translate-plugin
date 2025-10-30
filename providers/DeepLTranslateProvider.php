<?php

namespace Winter\Translate\Providers;

use Winter\Translate\Contracts\TranslationProvider;
use Exception;
use Illuminate\Support\Facades\Http;

class DeepLTranslateProvider implements TranslationProvider
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function translate(array $input, string $targetLocale, string $currentLocale): array
    {
        $endpoint = rtrim($this->config['url'], '/');

        $payload = [
            'text' => $input,
            'target_lang' => strtoupper($targetLocale),
            'tag_handling' => 'html',
            'source_lang' => strtoupper($currentLocale),
        ];

        $response = Http::withHeaders([
            'Authorization' => "DeepL-Auth-Key {$this->config['key']}",
            'Content-Type'  => 'application/json',
        ])->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new Exception("DeepL Translation failed: " . $response->body());
        }

        $json = $response->json();

        return array_column($json['translations'], 'text');
    }
}
