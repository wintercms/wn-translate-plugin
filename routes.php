<?php

use Illuminate\Foundation\Application as Laravel;
use Winter\Translate\Classes\Translator;
use Winter\Translate\Models\Message;

/*
 * Adds a custom route to check for the locale prefix.
 */
$beforeCallback = function () {
    if (Config::get('winter.translate::disableLocalePrefixRoutes', false)) {
        return;
    }

    if (App::runningInBackend()) {
        return;
    }

    $translator = Translator::instance();

    if (
        !$translator->isConfigured() ||
        !$translator->loadLocaleFromRequest() ||
        (!$locale = $translator->getLocale())
    ) {
        return;
    }

    /*
     * Register routes
     */
    Route::group(['prefix' => $locale, 'middleware' => 'web'], function () {
        Route::any('{slug?}', 'Cms\Classes\CmsController@run')->where('slug', '(.*)?');
    });

    Route::any($locale, 'Cms\Classes\CmsController@run')->middleware('web');

    /*
     * Ensure Url::action() retains the localized URL
     * by re-registering the route after the CMS.
     */
    Event::listen('cms.route', function () use ($locale) {
        Route::group(['prefix' => $locale, 'middleware' => 'web'], function () {
            Route::any('{slug?}', 'Cms\Classes\CmsController@run')->where('slug', '(.*)?');
        });
    });
};

if (version_compare(Laravel::VERSION, '9.0.0', '>=')) {
    Event::listen('system.route', $beforeCallback);
} else {
    App::before($beforeCallback);
}

/*
 * Save any used messages to the contextual cache.
 */
App::after(function () {
    if (class_exists('Winter\Translate\Models\Message')) {
        Message::saveToCache();
    }
});
