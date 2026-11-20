<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use InvalidArgumentException;
use RuntimeException;

/**
 * Resolves the adapter for an online method. Only TEST mode exists: live bKash / Nagad / card
 * adapters need the providers' verified merchant documentation and credentials, which have not
 * been supplied, so "live" mode fails loudly instead of pretending to work.
 */
class PaymentGatewayFactory
{
    public function for(PaymentMethod $method): PaymentGateway
    {
        if (! $method->isOnline()) {
            throw new InvalidArgumentException("{$method->label()} has no online gateway.");
        }

        if (config('commerce.payments.mode') !== 'test') {
            throw new RuntimeException('Live payment gateways are not configured. They require verified provider documentation and merchant credentials.');
        }

        return new TestPaymentGateway(
            $method,
            (string) config("commerce.payments.test_webhook_secrets.{$method->value}"),
            (int) config('commerce.payments.webhook_tolerance_seconds', 300),
        );
    }
}
