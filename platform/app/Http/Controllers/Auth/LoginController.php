<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Mfa\MfaSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Same generic error for unknown account, wrong password and deactivated account
     * (no account enumeration). Throttled by the "login" rate limiter.
     *
     * Staff are NOT logged in after the password step: they must pass TOTP MFA first
     * (or enroll, if they have not yet). Customers are logged in directly.
     */
    public function store(LoginRequest $request, MfaSession $mfa): RedirectResponse
    {
        $credentials = $request->credentials() + ['is_active' => true];
        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
            throw ValidationException::withMessages([
                'identifier' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        if ($user->isStaff()) {
            $mfa->startPending($user, $request->boolean('remember'));

            return redirect()->route($user->hasMfaEnrolled() ? 'mfa.challenge' : 'mfa.setup');
        }

        Auth::login($user, $request->boolean('remember'));
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('account'));
    }
}
