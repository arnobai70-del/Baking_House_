<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\BangladeshPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Profile and password for every signed-in account (retail, business, reseller, staff).
 * Changing the password needs the current one, signs out other sessions and revokes API tokens.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['phone' => BangladeshPhone::normalize($request->input('phone')) ?? $request->input('phone')]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], ['phone.regex' => __('Enter a valid Bangladesh mobile number (01XXXXXXXXX).')]);

        $before = $user->only(['name', 'phone', 'email']);
        $user->fill($validated);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        $audit->record('user.profile_updated', $user, $before, $user->only(['name', 'phone', 'email']));

        return back()->with('status', __('Profile saved.'));
    }

    public function password(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $validated['password']])->save();

        /* Other browsers and API clients must sign in again with the new password. */
        Auth::logoutOtherDevices($validated['password']);
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->regenerate();

        $audit->record('user.password_changed', $user, after: ['other_sessions_ended' => true, 'api_tokens_revoked' => true]);

        return back()->with('status', __('Password changed. Other devices have been signed out.'));
    }
}
