<?php

namespace App\Enums;

/**
 * A user's role inside one organization (team seats).
 *
 * - Owner: everything, including team, addresses and quotations.
 * - Buyer: orders, bulk orders, quotations and addresses.
 * - Viewer: read-only (orders, prices, invoices, statements) — e.g. an accounts person.
 */
enum OrganizationRole: string
{
    case Owner = 'owner';
    case Buyer = 'buyer';
    case Viewer = 'viewer';

    public function canPlaceOrders(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManageTeam(): bool
    {
        return $this === self::Owner;
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Buyer => 'Buyer',
            self::Viewer => 'Viewer (read only)',
        };
    }
}
