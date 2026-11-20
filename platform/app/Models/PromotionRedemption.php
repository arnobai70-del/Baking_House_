<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One use of a promotion by an order (reference is unique per promotion — no double redemption).
 */
#[Fillable(['promotion_id', 'user_id', 'reference', 'discount_paisa'])]
class PromotionRedemption extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['discount_paisa' => 'integer', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
