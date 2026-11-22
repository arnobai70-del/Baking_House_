<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every response.
 *
 * - Content-Security-Policy: scripts only from this site (no inline scripts exist), styles from
 *   this site (inline style attributes are used by the design), fonts self-hosted (Phase 9.5), no
 *   plugins, no framing, forms only to this site. Responses that set their own policy (e.g. the
 *   private photo preview) keep it. Skipped while APP_DEBUG is on so the local error page works.
 * - HSTS only over HTTPS. Plus nosniff, frame denial, referrer and permissions policies.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! config('app.debug') && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', implode('; ', array_filter([
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "font-src 'self'",
                "img-src 'self' data:",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                $request->isSecure() ? 'upgrade-insecure-requests' : null,
            ])));
        }

        return $response;
    }
}
