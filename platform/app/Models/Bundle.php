<?php

namespace App\Models;

use App\Enums\CustomerAudience;
use App\Enums\DiscountType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Combo / bundle of real variants. Children are priced at their STANDARD price, then the bundle
 * discount applies once — never stacked with trade tiers or coupons (blueprint §06).
 */
#[Fillable(['name', 'name_bn', 'slug', 'description', 'discount_type', 'discount_value', 'audiences', 'is_active', 'starts_at', 'ends_at'])]
class Bundle extends Model
{
    use HasFactory, HasTranslations;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'audiences' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<BundleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }

    /**
     * @param  Builder<Bundle>  $query
     */
    #[Scope]
    protected function effectiveAt(Builder $query, \DateTimeInterface $at): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function allowsAudience(CustomerAudience $audience): bool
    {
        return in_array($audience->value, $this->audiences ?? [], true);
    }

    public function url(): string
    {
        return route('bundles.show', $this->slug);
    }
}
