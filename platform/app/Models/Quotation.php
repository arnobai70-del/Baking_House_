<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request for quotation and, once staff issue it, the binding quote: fixed lines (quantity AND
 * price) valid until a date. All attributes are guarded; state only changes through
 * App\Services\Quotations\QuotationService.
 */
class Quotation extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'total_paisa' => 'integer',
            'valid_until' => 'datetime',
            'quoted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Quoted or accepted, and not past its validity.
     */
    public function isCurrent(): bool
    {
        return in_array($this->status, [QuotationStatus::Quoted, QuotationStatus::Accepted], true)
            && $this->valid_until !== null && $this->valid_until->isFuture();
    }

    /**
     * Only an accepted, still-valid quotation of the buyer's own organization can be ordered.
     */
    public function canBeOrderedBy(?Organization $organization): bool
    {
        return $this->status === QuotationStatus::Accepted
            && $this->isCurrent()
            && $organization !== null
            && $this->organization_id === $organization->id;
    }
}
