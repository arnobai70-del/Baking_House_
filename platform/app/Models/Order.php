<?php

namespace App\Models;

use App\Enums\CustomerAudience;
use App\Enums\OrderStatus;
use App\Enums\OrganizationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A placed order. Monetary fields and the pricing snapshot are written once at placement by
 * App\Services\Orders\OrderPlacer and never recalculated afterwards. All attributes are guarded;
 * only the order services write them.
 */
#[Hidden(['guest_access_hash', 'checkout_token'])]
class Order extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => CustomerAudience::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'shipping_address' => 'array',
            'promotion_snapshot' => 'array',
            'pricing_snapshot' => 'array',
            'subtotal_paisa' => 'integer',
            'discount_paisa' => 'integer',
            'delivery_paisa' => 'integer',
            'total_paisa' => 'integer',
            'refunded_paisa' => 'integer',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reservation_expires_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->orderBy('id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    /**
     * Stock reservations are keyed by this reference.
     */
    public function reservationReference(): string
    {
        return 'order:'.$this->number;
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true);
    }

    public function customerCanCancel(): bool
    {
        return $this->status->customerCanCancel();
    }

    /**
     * Ownership: the signed-in customer who placed it. Guests use the access key instead.
     */
    public function belongsToUser(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    /**
     * Organization orders are visible to every current member of that approved organization
     * (any role) and to nobody else.
     */
    public function isVisibleToMember(?User $user): bool
    {
        return $user !== null && $this->organization_id !== null
            && $user->organizations()->whereKey($this->organization_id)->where('status', OrganizationStatus::Approved->value)->exists();
    }

    public function matchesGuestKey(?string $key): bool
    {
        return $key !== null && $this->guest_access_hash !== null && hash_equals($this->guest_access_hash, hash('sha256', $key));
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
