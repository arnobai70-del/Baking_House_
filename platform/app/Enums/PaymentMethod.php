<?php

namespace App\Enums;

/**
 * Online methods hold stock with a 15-minute reservation until a VERIFIED provider callback;
 * cash on delivery and bank transfer allocate stock at placement and are settled by staff.
 */
enum PaymentMethod: string
{
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case CashOnDelivery = 'cod';

    public function isOnline(): bool
    {
        return in_array($this, [self::Bkash, self::Nagad, self::Card], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Card => 'Card (Visa / Mastercard)',
            self::BankTransfer => 'Bank transfer',
            self::CashOnDelivery => 'Cash on delivery',
        };
    }
}
