<?php

namespace App\Services\Checkout;

use App\Enums\PaymentMethod;

/**
 * Validated customer input for placing an order. Contains no prices — those are always
 * recomputed on the server.
 */
final readonly class CheckoutDetails
{
    public function __construct(
        public string $name,
        public string $phone,
        public ?string $email,
        public string $addressLine,
        public string $area,
        public string $district,
        public ?string $note,
        public PaymentMethod $paymentMethod,
        public string $checkoutToken,
        public string $expectedFingerprint,
    ) {}

    /**
     * @return array<string, string>
     */
    public function address(): array
    {
        return ['line' => $this->addressLine, 'area' => $this->area, 'district' => $this->district];
    }
}
