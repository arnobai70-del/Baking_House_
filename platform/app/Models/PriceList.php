<?php

namespace App\Models;

use App\Enums\PriceListAudience;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'audience', 'organization_id', 'is_active', 'starts_at', 'ends_at', 'notes'])]
class PriceList extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => PriceListAudience::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<PriceListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    /**
     * Active and inside its optional schedule window at $at.
     *
     * @param  Builder<PriceList>  $query
     */
    #[Scope]
    protected function effectiveAt(Builder $query, \DateTimeInterface $at): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }
}
