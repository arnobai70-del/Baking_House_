<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One priced custom-cake choice. Prices are admin-managed; `is_test` marks unverified development prices,
 * which block checkout outside local / testing (see CakePricer) and fail the production readiness check.
 */
#[Fillable(['cake_product_id', 'group', 'code', 'name', 'name_bn', 'price_type', 'price_paisa', 'is_test', 'requires_quote', 'complexity', 'meta', 'is_active', 'sort_order'])]
class CakeOption extends Model
{
    use HasTranslations;

    public const GROUPS = [
        'flavour' => 'Flavour (base price per kg)',
        'shape' => 'Shape',
        'tier' => 'Tiers',
        'filling' => 'Filling',
        'cream' => 'Cream',
        'diet' => 'Dietary option',
        'message' => 'Custom message',
        'print' => 'Edible print',
        'extra' => 'Extra / premium ingredient',
        'drip' => 'Drip',
        'colour' => 'Custom cream colour (colour picker)',
    ];

    public const PRICE_TYPES = ['per_kg' => 'Per kg', 'flat' => 'Flat amount', 'per_tier' => 'Per extra tier'];

    public const COMPLEXITIES = ['simple' => 'Simple', 'standard' => 'Standard', 'complex' => 'Complex'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_paisa' => 'integer', 'is_test' => 'boolean', 'requires_quote' => 'boolean',
            'meta' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    /**
     * Active options for a cake product: its own options override global ones with the same group + code.
     */
    protected function scopeForCake(Builder $query, CakeProduct $cake): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $scope) => $scope->whereNull('cake_product_id')->orWhere('cake_product_id', $cake->id))
            ->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<CakeProduct, $this>
     */
    public function cakeProduct(): BelongsTo
    {
        return $this->belongsTo(CakeProduct::class);
    }
}
