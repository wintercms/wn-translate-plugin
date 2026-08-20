<?php

namespace Winter\Translate\Providers;

/**
 * Base translation provider.
 *
 * Handles construction and batching so concrete providers only implement a
 * single-request translateBatch(). Providers have per-request item limits, so
 * larger inputs are split into chunks and reassembled in order.
 */
abstract class AbstractTranslationProvider implements TranslationProvider
{
    protected array $config;

    /**
     * @var int Maximum number of text segments to send in a single request.
     */
    protected int $batchLimit = 100;

    /**
     * @var int Request timeout, in seconds.
     */
    protected int $timeout = 20;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function translate(array $input, string $targetLocale, string $currentLocale): array
    {
        if (count($input) === 0) {
            return [];
        }

        $output = [];
        foreach (array_chunk(array_values($input), max(1, $this->batchLimit)) as $chunk) {
            $output = array_merge($output, $this->translateBatch($chunk, $targetLocale, $currentLocale));
        }

        return $output;
    }

    /**
     * Translate a single batch (already within the provider's item limit).
     *
     * @param string[] $input
     * @return string[]
     */
    abstract protected function translateBatch(array $input, string $targetLocale, string $currentLocale): array;
}
