<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's chosen storefront language (session), limited to the languages the
 * admin has enabled; otherwise the admin-configured default.
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
        $chosen = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        app()->setLocale(in_array($chosen, $enabled, true) ? $chosen : Setting::defaultLanguage());

        return $next($request);
    }
}
