<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's own photo for an edible print. Stored on the private disk, re-encoded without metadata,
 * served only to its owner (account or guest cart) and to order staff. Never public, never shared through
 * a design link. Staff approve or reject each print before production.
 */
#[Fillable(['uuid', 'user_id', 'owner_token_hash', 'cake_design_id', 'path', 'width', 'height', 'bytes', 'sha256', 'rights_confirmed_at'])]
class CakeUpload extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'bytes' => 'integer', 'rights_confirmed_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function ownedBy(?User $user, ?string $cartToken): bool
    {
        if ($user !== null) {
            return $this->user_id === $user->id;
        }

        return $cartToken !== null && $this->owner_token_hash !== null && hash_equals($this->owner_token_hash, hash('sha256', $cartToken));
    }
}
