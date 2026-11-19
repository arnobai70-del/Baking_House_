<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Mfa\MfaManager;
use App\Services\Mfa\MfaSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff security settings. Regenerating recovery codes or resetting MFA requires re-authentication
 * (current password + current TOTP code). MFA cannot be switched off for staff, only re-enrolled.
 */
class SecurityController extends Controller
{
    public function __construct(private MfaManager $mfa, private AuditLogger $audit) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.security', [
            'user' => $user,
            'remainingCodes' => $user->hasMfaEnrolled() ? $this->mfa->remainingRecoveryCodes($user) : 0,
            'tokenCount' => $user->tokens()->count(),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): Response
    {
        $user = $request->user();
        $this->reauthenticate($request, 'recovery_codes_regenerated');

        $codes = $this->mfa->regenerateRecoveryCodes($user);
        $this->audit->record('mfa.recovery_regenerated', $user, after: ['recovery_codes_issued' => count($codes)]);

        return response()->view('auth.mfa-recovery-codes', ['codes' => $codes])->header('Cache-Control', 'no-store, private');
    }

    public function reset(Request $request, MfaSession $session): RedirectResponse
    {
        $user = $request->user();
        $this->reauthenticate($request, 'mfa_reset');

        $revoked = $this->mfa->reset($user);
        $this->audit->record('mfa.reset', $user, after: ['by' => 'self', 'api_tokens_revoked' => $revoked]);

        Auth::guard('web')->logout();
        $request->session()->regenerate();
        $session->startPending($user, false);

        return redirect()->route('mfa.setup')->with('status', 'MFA was reset. Set up your authenticator app again.');
    }

    /**
     * @throws ValidationException
     */
    private function reauthenticate(Request $request, string $purpose): void
    {
        $user = $request->user();
        abort_unless($user->hasMfaEnrolled(), 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:20'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password) || ! $this->mfa->verifyTotp($user, $validated['code'])) {
            $this->audit->record('mfa.failed', $user, after: ['method' => 'reauthentication', 'context' => $purpose]);

            throw ValidationException::withMessages(['code' => 'Password or authenticator code is incorrect.']);
        }
    }
}
