<?php

namespace Winter\Translate\Providers;

use Exception;
use Illuminate\Support\Facades\Http;

class GoogleTranslateProvider extends AbstractTranslationProvider
{
    /**
     * @var int Google Cloud Translation v2 allows up to 128 `q` segments per request.
     */
    protected int $batchLimit = 100;

    protected function translateBatch(array $input, string $targetLocale, string $currentLocale): array
    {
        $query = http_build_query([
            'target' => $targetLocale,
            'source' => $currentLocale,
            'key'    => $this->config['key'],
        ]);

        // The payload (key, target/source, q values) travels in the POST body to avoid
        // Google's request-line length limit on long fields.
        foreach ($input as $text) {
            $query .= '&q=' . urlencode($text);
        }

        $endpoint = rtrim($this->config['url'], '/');

        $response = Http::timeout($this->timeout)
            ->withBody($query, 'application/x-www-form-urlencoded')
            ->post($endpoint);

        if (!$response->successful()) {
            throw new Exception('Google Translation failed: HTTP ' . $response->status());
        }

        $json = $response->json();

        if (!isset($json['data']['translations'])) {
            throw new Exception('Google Translation returned an unexpected response.');
        }

        // Google returns HTML-entity-encoded text (not URL-encoded); only decode entities,
        // otherwise literal percent sequences in the source (e.g. "20%25") get corrupted.
        return array_map(
            fn($t) => html_entity_decode($t['translatedText'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $json['data']['translations']
        );
    }
}
