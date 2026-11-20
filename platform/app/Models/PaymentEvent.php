<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Every provider callback received (verified or not). (provider, event_id) is unique, which makes
 * callback processing idempotent: a replayed event is recorded once and never applied twice.
 */
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean', 'created_at' => 'datetime'];
    }
}
