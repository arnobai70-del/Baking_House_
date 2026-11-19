<?php

namespace App\Services\Promotions;

final readonly class PromotionResult
{
    /**
     * @param  list<AppliedPromotion>  $applied
     */
    public function __construct(
        public array $applied,
        public int $discountPaisa,
        public ?string $couponCode,
        public ?string $couponMessage,
    ) {}

    public function couponApplied(): bool
    {
        return $this->couponCode !== null && $this->couponMessage === null;
    }
}
