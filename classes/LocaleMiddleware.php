<?php

namespace Winter\Translate\Classes;

use Closure;
use Config;
use Winter\Translate\Classes\Translator;

class LocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $translator = Translator::instance();
        $translator->isConfigured();

        if (!$translator->loadLocaleFromRequest()) {
            if (Config::get('winter.translate::prefixDefaultLocale')) {
                $translator->loadLocaleFromSession();
            } else {
                $translator->setLocale($translator->getDefaultLocale());
            }
        }

        return $next($request);
    }
}
