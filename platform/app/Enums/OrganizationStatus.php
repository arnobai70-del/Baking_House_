<?php

namespace App\Enums;

/**
 * Organizations start Pending and need manual staff approval before any
 * business/reseller pricing or portal access is granted.
 */
enum OrganizationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
