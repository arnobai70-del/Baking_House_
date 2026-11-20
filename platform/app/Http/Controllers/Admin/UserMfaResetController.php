<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Mfa\MfaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Super-admin reset of another staff member's MFA (lost device). Requires the super admin to
 * re-authenticate with password + their own TOTP, a written reason, and revokes the target's API tokens.
 */
class UserMfaResetController extends Controller
{
    public function __invoke(Request $request, User $user, MfaManager $mfa, AuditLogger $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->can(StaffPermission::ManageRoles->value) && ! $actor->is($user), 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:20'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if (! Hash::check($validated['current_password'], $actor->password) || ! $mfa->verifyTotp($actor, $validated['code'])) {
            $audit->record('mfa.failed', $actor, after: ['method' => 'reauthentication', 'context' => 'admin_mfa_reset']);

            throw ValidationException::withMessages(['code' => 'Password or authenticator code is incorrect.']);
        }

        $revoked = $mfa->reset($user);
        $audit->record('mfa.reset', $user, after: ['by' => 'super_admin', 'api_tokens_revoked' => $revoked], reason: $validated['reason']);

        return back()->with('status', "MFA reset for {$user->name}. They must enroll again at next sign-in.");
    }
}
