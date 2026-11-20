<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shopping cart: a guest cart (identified by an unguessable token in a cookie) or one per
 * signed-in user. Holds identities and quantities only; prices are always recomputed.
 */
#[Fillable(['token', 'user_id', 'coupon_code', 'last_activity_at'])]
class Cart extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
