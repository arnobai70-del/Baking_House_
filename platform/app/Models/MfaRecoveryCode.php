<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single-use staff MFA recovery code, stored only as an HMAC hash.
 */
#[Fillable(['code_hash'])]
#[Hidden(['code_hash'])]
class MfaRecoveryCode extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
