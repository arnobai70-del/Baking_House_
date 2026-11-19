<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Mfa\MfaManager;
use App\Services\Mfa\MfaSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Second step of staff login: a TOTP code, or a single-use recovery code. Throttled ("mfa" limiter).
 */
class MfaChallengeController extends Controller
{
    public function __construct(private MfaSession $session, private MfaManager $mfa, private AuditLogger $audit) {}

    public function show(): View|RedirectResponse
    {
        $pending = $this->session->pending();
        if ($pending === null) {
            return redirect()->route('login')->withErrors(['identifier' => 'Your sign-in expired. Please log in again.']);
        }
        if (! $pending['user']->hasMfaEnrolled()) {
            return redirect()->route('mfa.setup');
        }

        return view('auth.mfa-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $this->session->pending();
        if ($pending === null || ! $pending['user']->hasMfaEnrolled()) {
            return redirect()->route('login')->withErrors(['identifier' => 'Your sign-in expired. Please log in again.']);
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:20', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);
        $user = $pending['user'];

        $viaRecovery = filled($validated['recovery_code'] ?? null);
        $passed = $viaRecovery
            ? $this->mfa->useRecoveryCode($user, (string) $validated['recovery_code'])
            : $this->mfa->verifyTotp($user, (string) $validated['code']);

        if (! $passed) {
            $this->audit->record('mfa.failed', $user, after: ['method' => $viaRecovery ? 'recovery_code' : 'totp', 'context' => 'web_login']);

            throw ValidationException::withMessages([$viaRecovery ? 'recovery_code' : 'code' => 'That code is not valid. Check your authenticator app and try again.']);
        }

        $this->completeLogin($request, $user, $pending['remember']);

        if ($viaRecovery) {
            $this->audit->record('mfa.recovery_used', $user, after: ['remaining' => $this->mfa->remainingRecoveryCodes($user)]);

            return redirect()->route('account.security')->with('status', 'Signed in with a recovery code. Generate new recovery codes if you are running low.');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    private function completeLogin(Request $request, User $user, bool $remember): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->session->clearPending();
        $this->session->markVerified();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->record('auth.staff_login', $user, after: ['mfa' => 'verified']);
    }
}
