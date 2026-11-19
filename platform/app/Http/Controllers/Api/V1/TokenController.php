<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use App\Services\AuditLogger;
use App\Services\Mfa\MfaManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    /**
     * POST /api/v1/auth/tokens — exchange credentials for a scoped, expiring token.
     *
     * Staff accounts additionally need MFA enrolled (via the web) and a fresh TOTP code in
     * `mfa_code` on this very request; enrollment alone is not enough. Throttled ("login").
     */
    public function store(IssueTokenRequest $request, ApiTokenIssuer $issuer, MfaManager $mfa, AuditLogger $audit): JsonResponse
    {
        $credentials = $request->credentials();
        $password = $credentials['password'];
        unset($credentials['password']);

        $user = User::query()->where($credentials)->where('is_active', true)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['identifier' => 'These credentials do not match our records.']);
        }

        if ($user->isStaff()) {
            if (! $user->hasMfaEnrolled()) {
                $audit->record('api.token_denied', $user, after: ['reason' => 'mfa_not_enrolled']);
                abort(403, 'Staff accounts must enroll in MFA on the website before using the API.');
            }

            if (! $mfa->verifyTotp($user, (string) $request->validated('mfa_code', ''))) {
                $audit->record('mfa.failed', $user, after: ['method' => 'totp', 'context' => 'api_token']);
                throw ValidationException::withMessages(['mfa_code' => 'A valid authenticator code is required for staff tokens.']);
            }
        }

        $token = $issuer->issue($user, $request->validated('device_name'), $request->validated('abilities', []));

        $audit->record('api.token_issued', $user, after: [
            'token_id' => $token->accessToken->id,
            'device' => $token->accessToken->name,
            'abilities' => $token->accessToken->abilities,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'mfa' => $user->isStaff() ? 'verified' : 'not_required',
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $token->accessToken->abilities,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 201);
    }

    /**
     * GET /api/v1/auth/tokens — the caller's own tokens (never the secret).
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->latest('id')->get()->map(fn (PersonalAccessToken $token) => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'current' => $token->is($request->user()->currentAccessToken()),
        ]);

        return response()->json(['data' => $tokens]);
    }

    /**
     * DELETE /api/v1/auth/tokens/{id} — revoke one of the caller's own tokens.
     * Another user's token id is indistinguishable from a missing one (404).
     */
    public function destroy(Request $request, int $tokenId): Response
    {
        $deleted = $request->user()->tokens()->whereKey($tokenId)->delete();

        abort_if($deleted === 0, 404);

        return response()->noContent();
    }

    /**
     * DELETE /api/v1/auth/tokens/current — sign out this device.
     */
    public function destroyCurrent(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
