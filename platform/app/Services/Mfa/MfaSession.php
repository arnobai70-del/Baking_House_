<?php

namespace App\Services\Mfa;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Session state for staff MFA.
 *
 * "pending": password was correct but MFA is not yet passed — the user is NOT logged in.
 * "verified": a TOTP / recovery code was verified in THIS session. Enrollment alone never sets it.
 */
class MfaSession
{
    public const PENDING_KEY = 'mfa.pending';

    public const VERIFIED_KEY = 'mfa.verified_at';

    public const PENDING_TTL_SECONDS = 600;

    public function __construct(private Session $session) {}

    public function startPending(User $user, bool $remember): void
    {
        $this->session->put(self::PENDING_KEY, ['user_id' => $user->id, 'remember' => $remember, 'at' => now()->getTimestamp()]);
    }

    /**
     * @return array{user: User, remember: bool}|null
     */
    public function pending(): ?array
    {
        $pending = $this->session->get(self::PENDING_KEY);

        if (! is_array($pending) || (now()->getTimestamp() - (int) ($pending['at'] ?? 0)) > self::PENDING_TTL_SECONDS) {
            $this->session->forget(self::PENDING_KEY);

            return null;
        }

        $user = User::query()->where('is_active', true)->find($pending['user_id'] ?? 0);

        return $user ? ['user' => $user, 'remember' => (bool) ($pending['remember'] ?? false)] : null;
    }

    public function clearPending(): void
    {
        $this->session->forget(self::PENDING_KEY);
    }

    public function markVerified(): void
    {
        $this->session->put(self::VERIFIED_KEY, now()->getTimestamp());
    }

    /**
     * Verified in this session AFTER the user's current enrollment was confirmed — a reset or
     * re-enrollment elsewhere invalidates older sessions.
     */
    public function isVerifiedFor(User $user): bool
    {
        $verifiedAt = $this->session->get(self::VERIFIED_KEY);

        return is_int($verifiedAt)
            && $user->hasMfaEnrolled()
            && $verifiedAt >= $user->mfa_confirmed_at->getTimestamp();
    }
}
