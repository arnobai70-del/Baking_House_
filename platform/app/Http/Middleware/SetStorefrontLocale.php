<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\LocalizedRoutes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the page language.
 *
 * - Public localized pages (see LocalizedRoutes): the URL decides — /bn/… is Bangla, the
 *   unprefixed URL is English — and the choice is remembered for session pages.
 * - Everything else (account, cart, checkout, portals, admin): the visitor's remembered language,
 *   limited to the languages the admin enabled, otherwise the admin default.
 * - With Bangla as the admin default, a visitor arriving on the English home page without a chosen
 *   language goes to /bn (the same for every new visitor and crawler).
 */
class SetStorefrontLocale
{
    public const SESSION_KEY = 'locale';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = Setting::enabledLanguages();
        $urlLocale = $request->attributes->get(LocalizeUrlPrefix::LOCALE_ATTRIBUTE);
        $chosen = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if ($urlLocale === 'en' && $chosen === null && $request->isMethod('GET')
            && $request->attributes->get(LocalizeUrlPrefix::PATH_ATTRIBUTE) === ''
            && Setting::defaultLanguage() === LocalizedRoutes::PREFIX && LocalizedRoutes::banglaEnabled()) {
            return redirect('/'.LocalizedRoutes::PREFIX);
        }

        if ($urlLocale !== null && in_array($urlLocale, $enabled, true)) {
            app()->setLocale($urlLocale);
            if ($request->hasSession() && $request->isMethod('GET') && count($enabled) > 1) {
                $request->session()->put(self::SESSION_KEY, $urlLocale);
            }

            return $next($request);
        }

        app()->setLocale(in_array($chosen, $enabled, true) ? $chosen : Setting::defaultLanguage());

        return $next($request);
    }
}
