<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * X-Robots-Tag for every response (HTML, CSV, images, JSON): "noindex, nofollow" everywhere while
 * indexing is disabled (development / staging), and on private paths (config seo.disallow) once
 * indexing is enabled. Complements the robots meta tag, which non-HTML responses cannot carry.
 */
class SendRobotsHeader
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $path = '/'.ltrim($request->path(), '/');
        /* "/business/" covers the portal only (not the /business landing page); "/admin" covers /admin and /admin/*. */
        $private = collect((array) config('seo.disallow'))->contains(fn (string $prefix) => str_ends_with($prefix, '/')
            ? Str::startsWith($path, $prefix)
            : $path === $prefix || Str::startsWith($path, $prefix.'/'));

        if (! config('seo.indexing_enabled') || $private) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
