<?php

namespace App\Enums;

/**
 * Stock ledger entry types. Quantity sign: positive adds stock on hand, negative removes it.
 */
enum InventoryMovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case Adjustment = 'adjustment';
    case Reconciliation = 'reconciliation';
    case Damage = 'damage';
    case Sale = 'sale';
    case Return = 'return';
    case Restock = 'restock';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening balance',
            self::Receipt => 'Goods received',
            self::Adjustment => 'Manual adjustment',
            self::Reconciliation => 'Stock count correction',
            self::Damage => 'Damaged / expired',
            self::Sale => 'Sold (order committed)',
            self::Return => 'Customer return',
            self::Restock => 'Order cancelled — restocked',
        };
    }

    /**
     * Types staff may record manually from the admin inventory screen.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Receipt, self::Adjustment, self::Damage, self::Return];
    }
}
