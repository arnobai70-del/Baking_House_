<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Prices, stock, discounts or delivery changed between review and placement. The customer must
 * see and explicitly accept the new summary before an order is created.
 */
class CheckoutChangedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(public readonly array $summary, string $message = 'Your order total changed. Please review the updated summary and confirm again.')
    {
        parent::__construct($message);
    }
}
