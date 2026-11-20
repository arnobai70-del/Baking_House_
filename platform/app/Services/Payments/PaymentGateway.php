<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;

/**
 * Provider-neutral online payment adapter (bKash / Nagad / card). Implementations must verify
 * callbacks server-side; a browser redirect alone never marks a payment as paid.
 */
interface PaymentGateway
{
    public function method(): PaymentMethod;

    public function mode(): string;

    /**
     * Where to send the customer to pay.
     */
    public function checkoutUrl(Payment $payment): string;

    /**
     * Verify a callback's authenticity and freshness. Returns the decoded payload.
     *
     * @param  array<string, string|null>  $headers
     * @return array<string, mixed>
     *
     * @throws InvalidCallbackException
     */
    public function verifyCallback(string $rawBody, array $headers): array;

    /**
     * Ask the provider to refund; returns the provider's refund reference.
     */
    public function refund(Payment $payment, int $amountPaisa): string;
}
