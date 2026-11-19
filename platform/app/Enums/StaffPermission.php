<?php

namespace App\Enums;

/**
 * Staff permissions. Each case is registered as a Gate ability with the same name.
 * Customer access (retail / business / reseller) is NOT a permission — it comes from
 * approved organization membership and ownership policies.
 */
enum StaffPermission: string
{
    case AccessAdmin = 'admin.access';
    case ManageCatalog = 'catalog.manage';
    case ManagePrices = 'prices.manage';
    case ApprovePriceChanges = 'prices.approve';
    case ManageOrders = 'orders.manage';
    case ManageCustomers = 'customers.manage';
    case ApproveOrganizations = 'organizations.approve';
    case ManageCms = 'cms.manage';
    case ManageMedia = 'media.manage';
    case ApproveMedia = 'media.approve';
    case ViewAuditLog = 'audit.view';
    case ManageRoles = 'roles.manage';
    case ManageIntegrations = 'integrations.manage';

    /**
     * Permissions reserved for the super admin (owner-only secrets and role escalation).
     *
     * @return list<self>
     */
    public static function superAdminOnly(): array
    {
        return [self::ApprovePriceChanges, self::ManageRoles, self::ManageIntegrations];
    }

    public function label(): string
    {
        return ucfirst(str_replace(['.', '_'], ' ', $this->value));
    }
}
