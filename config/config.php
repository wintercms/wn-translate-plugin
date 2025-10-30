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
    | Auto Translation Providers
    |--------------------------------------------------------------------------
    |
    | Configure the translation API services used by your application.
    | You may define multiple providers and pick one as the default.
    |
    | Supported Examples: "google"
    |
    */

    // NOTE: if you have multiple fields named the same thing they will be like name, name2, name3, etc
    // this will only translate the ones explicitly defined, in this case only name will be translated
    'autoTranslateWhiteList' => [
        'does_not_work',
        'does_not_work3',
        'name',
        'content',
        'works',
        'value',
    ],

    'defaultProvider' => env('TRANSLATE_PROVIDER', 'google'),

    'providers' => [

        'google' => [
            'url' => env('GOOGLE_TRANSLATE_URL', 'https://translation.googleapis.com/language/translate/v2'),
            'key' => env('GOOGLE_TRANSLATE_KEY', ''),
        ],

        'deepl' => [
            'url' => env('DEEPL_API_URL', 'https://api.deepl.com/v2/translate'),
            'key' => env('DEEPL_API_KEY', ''),
        ],
    ],
];
