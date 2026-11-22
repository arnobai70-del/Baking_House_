<?php

namespace App\Services\Mfa;

use App\Models\MfaRecoveryCode;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Staff TOTP MFA (RFC 6238 via pragmarx/google2fa).
 *
 * - Secrets are encrypted at rest by the User model's "encrypted" cast.
 * - A TOTP code is accepted only if its time-step is newer than the last accepted one (no replay).
 * - Recovery codes are 8 random codes, stored as HMAC-SHA256 hashes, consumed atomically once.
 */
class MfaManager
{
    public const RECOVERY_CODE_COUNT = 8;

    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Start (or restart) enrollment: store a fresh, unconfirmed secret.
     */
    public function beginEnrollment(User $user): void
    {
        $user->forceFill([
            'mfa_secret' => $this->google2fa->generateSecretKey(32),
            'mfa_confirmed_at' => null,
            'mfa_last_timestep' => null,
        ])->save();
    }

    public function provisioningUri(User $user): string
    {
        return $this->google2fa->getQRCodeUrl((string) config('brand.name'), $user->email ?? $user->phone ?? (string) $user->id, (string) $user->mfa_secret);
    }

    public function qrCodeSvg(User $user): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return $writer->writeString($this->provisioningUri($user));
    }

    /**
     * Verify a 6-digit TOTP code, rejecting reuse of the same or an older time-step.
     */
    public function verifyTotp(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($user->mfa_secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        /* Read the last accepted step fresh from the DB; always pass an int so google2fa returns the
           matched time-step (with null it returns bare `true`, which would break replay protection). */
        $lastTimestep = (int) User::query()->whereKey($user->id)->value('mfa_last_timestep');
        $timestep = $this->google2fa->verifyKeyNewer($user->mfa_secret, $code, $lastTimestep, 1);

        if (! is_int($timestep) || $timestep <= $lastTimestep) {
            return false;
        }

        $updated = User::query()
            ->whereKey($user->id)
            ->where(fn ($query) => $query->whereNull('mfa_last_timestep')->orWhere('mfa_last_timestep', '<', $timestep))
            ->update(['mfa_last_timestep' => $timestep]);

        $user->mfa_last_timestep = $timestep;

        return $updated === 1;
    }

    /**
     * Confirm enrollment with a valid code and issue fresh recovery codes (shown once).
     *
     * @return list<string>|null plain recovery codes, or null when the code is wrong
     */
    public function confirmEnrollment(User $user, string $code): ?array
    {
        if (! $this->verifyTotp($user, $code)) {
            return null;
        }

        $user->forceFill(['mfa_confirmed_at' => now()])->save();

        return $this->regenerateRecoveryCodes($user);
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = [];

        DB::transaction(function () use ($user, &$codes): void {
            MfaRecoveryCode::query()->where('user_id', $user->id)->delete();

            for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
                $code = Str::upper(Str::random(5).'-'.Str::random(5));
                $codes[] = $code;
                MfaRecoveryCode::query()->forceCreate(['user_id' => $user->id, 'code_hash' => $this->hashRecoveryCode($code)]);
            }
        });

        return $codes;
    }

    /**
     * Consume a recovery code atomically. A code can succeed only once, even under concurrency.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $normalised = Str::upper(trim($code));
        if (preg_match('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $normalised) !== 1) {
            return false;
        }

        return MfaRecoveryCode::query()
            ->where('user_id', $user->id)
            ->where('code_hash', $this->hashRecoveryCode($normalised))
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return MfaRecoveryCode::query()->where('user_id', $user->id)->whereNull('used_at')->count();
    }

    /**
     * Remove MFA (forces re-enrollment) and revoke every API token the user holds.
     */
    public function reset(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $user->forceFill(['mfa_secret' => null, 'mfa_confirmed_at' => null, 'mfa_last_timestep' => null])->save();
            MfaRecoveryCode::query()->where('user_id', $user->id)->delete();

            return $user->tokens()->delete();
        });
    }

    private function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
