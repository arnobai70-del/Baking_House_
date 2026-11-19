<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a reservation or stock decrease would make available stock negative.
 * Carries the quantity that WAS available so callers can suggest a valid amount.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly int $variantId, public readonly int $available, string $message = 'Not enough stock available.')
    {
        parent::__construct($message);
    }
}
