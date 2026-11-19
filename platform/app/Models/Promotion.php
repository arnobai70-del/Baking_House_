<?php

namespace App\Models;

use App\Enums\CustomerAudience;
use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Coupon (has a code) or automatic promotion (no code). scope "order" discounts the eligible
 * subtotal; scope "products" discounts only lines of the attached products.
 */
#[Fillable([
    'name', 'code', 'discount_type', 'discount_value', 'scope', 'min_subtotal_paisa', 'max_discount_paisa',
    'audiences', 'stackable', 'usage_limit', 'per_customer_limit', 'is_active', 'starts_at', 'ends_at',
])]
class Promotion extends Model
{
    use HasFactory;

    public const SCOPE_ORDER = 'order';

    public const SCOPE_PRODUCTS = 'products';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'min_subtotal_paisa' => 'integer',
            'max_discount_paisa' => 'integer',
            'audiences' => 'array',
            'stackable' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value === null || trim($value) === '' ? null : strtoupper(trim($value));
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @return HasMany<PromotionRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    /**
     * @param  Builder<Promotion>  $query
     */
    #[Scope]
    protected function effectiveAt(Builder $query, \DateTimeInterface $at): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function isEffectiveAt(\DateTimeInterface $at): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at <= $at)
            && ($this->ends_at === null || $this->ends_at > $at);
    }

    public function allowsAudience(CustomerAudience $audience): bool
    {
        return in_array($audience->value, $this->audiences ?? [], true);
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    public function describe(): string
    {
        return $this->discount_type->describe($this->discount_value);
    }
}
