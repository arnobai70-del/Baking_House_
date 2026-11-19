<?php

namespace App\Services\Promotions;

use App\Models\Promotion;

final readonly class AppliedPromotion
{
    public function __construct(public Promotion $promotion, public int $discountPaisa) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'promotion_id' => $this->promotion->id,
            'name' => $this->promotion->name,
            'code' => $this->promotion->code,
            'description' => $this->promotion->describe(),
            'stackable' => $this->promotion->stackable,
            'discount_paisa' => $this->discountPaisa,
        ];
    }
}
