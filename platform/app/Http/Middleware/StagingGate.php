<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a staging site private: HTTP Basic credentials are required for every page except the
 * health check and payment-provider webhooks. Off unless STAGING_GATE_ENABLED=true. If the gate is
 * on but no credentials are configured, nothing is served (fail closed).
 */
class StagingGate
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('security.staging_gate');
        if (! ($gate['enabled'] ?? false) || $request->is(...($gate['open_paths'] ?? []))) {
            return $next($request);
        }

        $user = (string) ($gate['user'] ?? '');
        $hash = (string) ($gate['password_hash'] ?? '');
        $given = (string) $request->getUser();
        $password = (string) $request->getPassword();

        $valid = $user !== '' && $hash !== '' && hash_equals($user, $given) && Hash::check($password, $hash);
        if (! $valid) {
            return response('Staging site: authorised testers only.', 401, [
                'WWW-Authenticate' => 'Basic realm="'.config('brand.name').' staging", charset="UTF-8"',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return $next($request);
    }
}
