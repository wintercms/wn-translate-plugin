<?php

use Winter\Translate\Models\Message;
use Winter\Translate\Classes\Translator;

/*
 * Adds a custom route to check for the locale prefix.
 */
App::before(function ($request) {
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
});

/*
 * Save any used messages to the contextual cache.
 */
App::after(function ($request) {
    if (class_exists('Winter\Translate\Models\Message')) {
        Message::saveToCache();
    }
});
