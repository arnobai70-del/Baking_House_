<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Records sensitive actions: actor, timestamp, subject, before/after and reason.
 * Secrets must never be passed in $before/$after — keys in REDACTED_KEYS are masked defensively.
 */
class AuditLogger
{
    private const REDACTED_KEYS = [
        'password', 'current_password', 'remember_token', 'api_key', 'secret', 'token', 'plain_text_token',
        'mfa_secret', 'mfa_code', 'code', 'recovery_code', 'recovery_codes',
    ];

    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(string $action, ?Model $subject = null, array $before = [], array $after = [], ?string $reason = null): AuditLog
    {
        return AuditLog::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before === [] ? null : $this->redact($before),
            'after' => $after === [] ? null : $this->redact($after),
            'reason' => $reason,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 255) ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
