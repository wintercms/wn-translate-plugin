<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Force the Default Locale
    |--------------------------------------------------------------------------
    |
    | Always use the defined locale code as the default.
    | Related to https://github.com/rainlab/translate-plugin/issues/231
    |
    */

    'forceDefaultLocale' => env('TRANSLATE_FORCE_LOCALE', null),

    /*
    |--------------------------------------------------------------------------
    | Prefix the Default Locale
    |--------------------------------------------------------------------------
    |
    | Specifies if the default locale be prefixed by the plugin.
    |
    */

    'prefixDefaultLocale' => env('TRANSLATE_PREFIX_LOCALE', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Timeout in Minutes
    |--------------------------------------------------------------------------
    |
    | By default all translations are cached for 24 hours (1440 min).
    | This setting allows to change that period with given amount of minutes.
    |
    | For example, 43200 for 30 days or 525600 for one year.
    |
    */

    'cacheTimeout' => env('TRANSLATE_CACHE_TIMEOUT', 1440),

    /*
    |--------------------------------------------------------------------------
    | Disable Locale Prefix Routes
    |--------------------------------------------------------------------------
    |
    | Disables the automatically generated locale prefixed routes
    | (i.e. /en/original-route) when enabled.
    |
    */

    'disableLocalePrefixRoutes' => env('TRANSLATE_DISABLE_PREFIX_ROUTES', false),

    /*
    |--------------------------------------------------------------------------
    | Redirect Status Code
    |--------------------------------------------------------------------------
    |
    | Specifies the HTTP status code to use for redirects.
    | Default is 302 (Found).
    |
    */

    'redirectStatus' => env('TRANSLATE_REDIRECT_STATUS', 302),

    /*
    |--------------------------------------------------------------------------
    | Auto Translation Whitelist
    |--------------------------------------------------------------------------
    |
    | Specifies which form inputs should be automatically translated when using
    | auto-translation.
    |
    | Example scenario:
    |       fields:
    |           does_not_work: <- this is the key you put into the whitelist
    |               label: Does not work
    |               trigger:
    |                   action: hide
    |                   field: is_delayed
    |                   condition: checked
    |
    | Important Notes:
    | - Only applies to formwidgets that have multiple inputs (e.g. NestedForm),
    |   otherwise it's ignored.
    |
    | Example:
    |   'autoTranslateWhiteList' => ['name', 'content']
    |
    */

    'autoTranslateWhiteList' => [],

    /*
    |--------------------------------------------------------------------------
    | Default Auto Translation Provider
    |--------------------------------------------------------------------------
    |
    | Sets the default provider used when performing auto translation.
    | This must match one of the providers defined in the "providers" section,
    | otherwise a 'standard' copy will be performed.
    |
    | Default is standard as users will need to setup access to a provider
    |
    | Example:
    |   TRANSLATE_PROVIDER=google
    |
    */

    'defaultProvider' => env('TRANSLATE_PROVIDER', 'standard'),

    /*
    |--------------------------------------------------------------------------
    | Auto Translation Providers
    |--------------------------------------------------------------------------
    |
    | Configure the translation API services available to your application.
    | You may define multiple providers; each provider will appear in the
    | dropdown list when choosing a translation service.
    |
    | To create new providers create a new class that implements TranslationProvider
    | and add it to the ProviderFactory, see translate/providers.
    |
    | Each provider must include:
    |   - url : API endpoint
    |   - key : API key or authentication token
    |
    */

    'providers' => [

        'google' => [
            'url' => env('GOOGLE_TRANSLATE_URL', 'https://translation.googleapis.com/language/translate/v2'),
            'key' => env('GOOGLE_TRANSLATE_KEY', ''),
        ],

        // Example for adding an additional provider:
        //
        // 'deepl' => [
        //     'url' => env('DEEPL_API_URL', 'https://api.deepl.com/v2/translate'),
        //     'key' => env('DEEPL_API_KEY', ''),
        // ],
    ],
];
