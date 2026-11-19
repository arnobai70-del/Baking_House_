<?php

namespace App\Enums;

/**
 * API token scopes. A token's abilities are always clamped to what its owner may hold
 * (see App\Services\ApiTokenIssuer); holding an ability never replaces the server-side
 * permission / membership check on each endpoint.
 */
enum ApiAbility: string
{
    case AccountRead = 'account:read';
    case OrdersRead = 'orders:read';
    case OrdersWrite = 'orders:write';
    case BusinessRead = 'business:read';
    case BusinessWrite = 'business:write';
    case ResellerRead = 'reseller:read';
    case ResellerWrite = 'reseller:write';
    case AdminRead = 'admin:read';
    case AdminWrite = 'admin:write';

    /**
     * @return list<self>
     */
    public static function customerDefaults(): array
    {
        return [self::AccountRead, self::OrdersRead, self::OrdersWrite];
    }

    /**
     * @return list<self>
     */
    public static function forOrganizationType(OrganizationType $type): array
    {
        return match ($type) {
            OrganizationType::Business => [self::BusinessRead, self::BusinessWrite],
            OrganizationType::Reseller => [self::ResellerRead, self::ResellerWrite],
        };
    }

    /**
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::AdminRead, self::AdminWrite];
    }
}
