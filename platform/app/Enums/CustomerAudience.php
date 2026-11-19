<?php

namespace App\Enums;

/**
 * Who is buying, as resolved on the SERVER from the authenticated user and their approved
 * organization context. Never taken from client input.
 */
enum CustomerAudience: string
{
    case Retail = 'retail';
    case Business = 'business';
    case Reseller = 'reseller';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Retail',
            self::Business => 'Business',
            self::Reseller => 'Reseller',
        };
    }

    public static function fromOrganizationType(OrganizationType $type): self
    {
        return match ($type) {
            OrganizationType::Business => self::Business,
            OrganizationType::Reseller => self::Reseller,
        };
    }
}
