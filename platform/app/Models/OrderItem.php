<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable order line snapshot (name, SKU, quantity and price as charged).
 */
class OrderItem extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_paisa' => 'integer',
            'per_kg_paisa' => 'integer',
            'line_paisa' => 'integer',
            'standard_line_paisa' => 'integer',
            'components' => 'array',
            'scheduled_for' => 'date:Y-m-d',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Order lines are immutable.'));
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<CakeDesign, $this>
     */
    public function cakeDesign(): BelongsTo
    {
        return $this->belongsTo(CakeDesign::class);
    }

    public function isCake(): bool
    {
        return $this->type === 'cake';
    }
}
