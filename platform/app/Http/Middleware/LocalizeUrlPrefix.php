<?php

namespace App\Http\Middleware;

use App\Support\LocalizedRoutes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware (runs before routing): "/bn/shop" is served by the "/shop" route with the
 * page language fixed to Bangla. Unprefixed localized pages are marked English. A /bn URL for a
 * page that is not localized, or while Bangla is disabled, is not found.
 */
class LocalizeUrlPrefix
{
    public const LOCALE_ATTRIBUTE = 'url_locale';

    public const PATH_ATTRIBUTE = 'localized_path';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        $prefix = LocalizedRoutes::PREFIX;

        if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
            $rest = $path === $prefix ? '' : substr($path, strlen($prefix) + 1);
            abort_unless(LocalizedRoutes::banglaEnabled() && LocalizedRoutes::matches($rest), 404);

            $server = $request->server->all();
            $query = $request->getQueryString();
            $server['REQUEST_URI'] = '/'.$rest.($query ? '?'.$query : '');
            $request = $request->duplicate(server: $server);
            $request->attributes->set(self::LOCALE_ATTRIBUTE, $prefix);
            $request->attributes->set(self::PATH_ATTRIBUTE, $rest);
        } elseif (LocalizedRoutes::matches($path)) {
            $request->attributes->set(self::LOCALE_ATTRIBUTE, 'en');
            $request->attributes->set(self::PATH_ATTRIBUTE, $path);
        }

        return $next($request);
    }
}
