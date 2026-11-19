<?php

namespace App\Enums;

/**
 * Only Active products are visible on the storefront, API and sitemap.
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
