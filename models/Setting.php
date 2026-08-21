<?php namespace Winter\Translate\Models;

use Model;

/**
 * Machine-translation provider settings.
 *
 * Stores the API credentials for the built-in translation providers (Google
 * Cloud Translation, DeepL) so they can be configured from the backend instead
 * of only via env/config. On boot the saved values are pushed into
 * `winter.translate::providers.*` so the existing ProviderFactory keeps working
 * unchanged; anything left blank falls back to the file/env config.
 */
class Setting extends Model
{
    public $implement = [
        \System\Behaviors\SettingsModel::class,
    ];

    public $settingsCode = 'winter_translate_settings';

    public $settingsFields = 'fields.yaml';

    const PLAN_FREE = 'free';
    const PLAN_PRO = 'pro';

    const DEEPL_URL_FREE = 'https://api-free.deepl.com/v2/translate';
    const DEEPL_URL_PRO = 'https://api.deepl.com/v2/translate';

    /**
     * Seed non-secret defaults for a fresh install. API keys are deliberately
     * NOT pulled in from env/config: a key already set via environment keeps
     * working through the config fallback (see applyConfigValues), and copying
     * secrets into the settings record / form would duplicate and expose them.
     * The default DeepL plan is inferred from the configured endpoint so the
     * dropdown matches an env-configured key.
     */
    public function initSettingsData()
    {
        $deeplUrl = (string) config('winter.translate::providers.deepl.url', static::DEEPL_URL_PRO);
        $this->deepl_plan = strpos($deeplUrl, 'api-free.') !== false ? static::PLAN_FREE : static::PLAN_PRO;
    }

    public function getDeeplPlanOptions(): array
    {
        return [
            static::PLAN_FREE => 'winter.translate::lang.settings.deepl_plan_free',
            static::PLAN_PRO => 'winter.translate::lang.settings.deepl_plan_pro',
        ];
    }

    /**
     * The DeepL endpoint for the configured plan.
     */
    public function getDeeplUrl(): string
    {
        return $this->deepl_plan === static::PLAN_FREE ? static::DEEPL_URL_FREE : static::DEEPL_URL_PRO;
    }

    /**
     * Pushes the stored provider credentials into config so ProviderFactory and
     * getUsableTranslateProviders() resolve them. Only non-empty values override
     * the file/env config, so an unconfigured provider still falls back cleanly.
     */
    public static function applyConfigValues(): void
    {
        $settings = static::instance();
        $config = app('config');

        if ($key = trim((string) $settings->google_api_key)) {
            $config->set('winter.translate::providers.google.key', $key);
            if ($url = trim((string) $settings->google_api_url)) {
                $config->set('winter.translate::providers.google.url', $url);
            }
        }

        if ($key = trim((string) $settings->deepl_api_key)) {
            $config->set('winter.translate::providers.deepl.key', $key);
            $config->set('winter.translate::providers.deepl.url', $settings->getDeeplUrl());
        }
    }
}
