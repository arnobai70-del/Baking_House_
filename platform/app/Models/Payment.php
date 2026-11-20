<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One payment attempt. `reference` is our id sent to the provider; no card or wallet credentials
 * are ever stored. Written only by App\Services\Payments\PaymentService.
 */
class Payment extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentMethod::class,
            'status' => PaymentRecordStatus::class,
            'amount_paisa' => 'integer',
            'refunded_paisa' => 'integer',
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function refundablePaisa(): int
    {
        return in_array($this->status, [PaymentRecordStatus::Paid, PaymentRecordStatus::PartiallyRefunded], true)
            ? max(0, $this->amount_paisa - $this->refunded_paisa)
            : 0;
    }
}
