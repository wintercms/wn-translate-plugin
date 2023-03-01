<?php

namespace Winter\Translate\Components;

use Cms\Classes\ComponentBase;
use Event;
use Winter\Storm\Router\Router as RainRouter;
use Winter\Translate\Classes\Translator;
use Winter\Translate\Models\Locale as LocaleModel;

class AlternateHrefLangElements extends ComponentBase
{
    public function componentDetails(): array
    {
        return [
            'name'        => 'winter.translate::lang.alternate_hreflang.component_name',
            'description' => 'winter.translate::lang.alternate_hreflang.component_description'
        ];
    }

    public function locales()
    {
        // Available locales
        $locales = collect(LocaleModel::listEnabled());

        // Transform it to contain the new urls
        $locales->transform(function ($item, $key) {
            return $this->retrieveLocalizedUrl($key);
        });

        return $locales->toArray();
    }

    private function retrieveLocalizedUrl($locale)
    {
        $translator = Translator::instance();
        $page = $this->getPage();

        /*
         * Static Page
         */
        if (isset($page->apiBag['staticPage'])) {
            $staticPage = $page->apiBag['staticPage'];
            $staticPage->rewriteTranslatablePageUrl($locale);
            $localeUrl = array_get($staticPage->attributes, 'viewBag.url');
        }
        /*
         * CMS Page
         */
        else {
            $page->rewriteTranslatablePageUrl($locale);
            $router = new RainRouter;
            $params = $this->getRouter()->getParameters();

            $translatedParams = Event::fire('translate.localePicker.translateParams', [
                $page,
                $params,
                $this->oldLocale,
                $locale
            ], true);

            if ($translatedParams) {
                $params = $translatedParams;
            }

            $localeUrl = $router->urlFromPattern($page->url, $params);
        }

        return $translator->getPathInLocale($localeUrl, $locale);
    }

}
