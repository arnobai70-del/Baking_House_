<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Models\User;
use App\Services\ApiTokenIssuer;
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
     */
    public function store(IssueTokenRequest $request, ApiTokenIssuer $issuer): JsonResponse
    {
        $credentials = $request->credentials();
        $password = $credentials['password'];
        unset($credentials['password']);

        $user = User::query()->where($credentials)->where('is_active', true)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['identifier' => 'These credentials do not match our records.']);
        }

        $token = $issuer->issue($user, $request->validated('device_name'), $request->validated('abilities', []));

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
