<?php

namespace App\Http\Middleware;

use App\Services\Mfa\MfaSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Any signed-in staff member without an MFA verification in the CURRENT session is signed out
 * and sent through the MFA challenge (or enrollment). Covers "remember me" re-logins and users
 * who were granted a staff role mid-session. Enrollment alone never satisfies this check.
 */
class RequireStaffMfa
{
    public function __construct(private MfaSession $mfa) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isStaff() || $this->mfa->isVerifiedFor($user)) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->regenerate();
        $this->mfa->startPending($user, false);

        return redirect()->route($user->hasMfaEnrolled() ? 'mfa.challenge' : 'mfa.setup');
    }
}
