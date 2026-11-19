<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Mfa\MfaManager;
use App\Services\Mfa\MfaSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Mandatory first-time TOTP enrollment for staff, reached only from a valid password step.
 */
class MfaSetupController extends Controller
{
    public function __construct(private MfaSession $session, private MfaManager $mfa, private AuditLogger $audit) {}

    public function show(): Response|RedirectResponse
    {
        $pending = $this->session->pending();
        if ($pending === null) {
            return redirect()->route('login')->withErrors(['identifier' => 'Your sign-in expired. Please log in again.']);
        }

        $user = $pending['user'];
        if ($user->hasMfaEnrolled()) {
            return redirect()->route('mfa.challenge');
        }
        if ($user->mfa_secret === null) {
            $this->mfa->beginEnrollment($user);
        }

        return response()->view('auth.mfa-setup', [
            'qrSvg' => $this->mfa->qrCodeSvg($user),
            'secret' => trim(chunk_split((string) $user->mfa_secret, 4, ' ')),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $pending = $this->session->pending();
        if ($pending === null || $pending['user']->hasMfaEnrolled()) {
            return redirect()->route('login');
        }

        $validated = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $pending['user'];

        $recoveryCodes = $this->mfa->confirmEnrollment($user, $validated['code']);
        if ($recoveryCodes === null) {
            $this->audit->record('mfa.failed', $user, after: ['method' => 'totp', 'context' => 'enrollment']);

            throw ValidationException::withMessages(['code' => 'That code is not valid. Scan the QR code again and enter the current 6-digit code.']);
        }

        Auth::login($user, $pending['remember']);
        $request->session()->regenerate();
        $this->session->clearPending();
        $this->session->markVerified();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->record('mfa.enrolled', $user, after: ['method' => 'totp', 'recovery_codes_issued' => count($recoveryCodes)]);

        return response()->view('auth.mfa-recovery-codes', ['codes' => $recoveryCodes])->header('Cache-Control', 'no-store, private');
    }
}
