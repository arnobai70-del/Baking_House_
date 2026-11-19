<?php

namespace App\Enums;

/**
 * Commercial account types. Business and reseller are separate contexts:
 * membership of one never grants access to the other (docs/roles-permissions.md).
 */
enum OrganizationType: string
{
    case Business = 'business';
    case Reseller = 'reseller';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Business account',
            self::Reseller => 'Reseller',
        };
    }
}
