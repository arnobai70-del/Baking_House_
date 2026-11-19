<?php

namespace App\Enums;

/**
 * A user's role inside one organization (team seats).
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
}
