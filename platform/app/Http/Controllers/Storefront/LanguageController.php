<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetStorefrontLocale;
use App\Models\Setting;
use App\Support\LocalizedRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Remembers the visitor's language. On a public localized page the visitor is sent to the same
 * page in the chosen language (/shop ↔ /bn/shop); elsewhere the page simply reloads.
 */
class LanguageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(['locale' => ['required', Rule::in(Setting::enabledLanguages())]]);
        $request->session()->put(SetStorefrontLocale::SESSION_KEY, $validated['locale']);

        /* Only same-site pages: a relative referer, or an absolute one on this host. */
        $previous = (string) $request->headers->get('referer', '');
        $host = parse_url($previous, PHP_URL_HOST);
        if ($previous === '' || ($host !== null && $host !== $request->getHost())) {
            return back();
        }

        $path = trim((string) parse_url($previous, PHP_URL_PATH), '/');
        $query = parse_url($previous, PHP_URL_QUERY);
        if ($path === LocalizedRoutes::PREFIX || str_starts_with($path, LocalizedRoutes::PREFIX.'/')) {
            $path = trim(substr($path, strlen(LocalizedRoutes::PREFIX)), '/');
        }
        if (! LocalizedRoutes::matches($path)) {
            return back();
        }

        $target = $validated['locale'] === LocalizedRoutes::PREFIX ? '/'.LocalizedRoutes::PREFIX.($path === '' ? '' : '/'.$path) : '/'.$path;

        return redirect($target.($query ? '?'.$query : ''));
    }
}
