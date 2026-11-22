<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand (rebrand 2026-11-22: Baking House → NadimShop.com)
    |--------------------------------------------------------------------------
    |
    | name: the public shop name shown in the header, titles, structured data, notifications and admin.
    |   It follows APP_NAME so one setting renames everything. It is a trading name only: the registered
    |   legal name for invoices is SELLER_LEGAL_NAME (config/commerce.php) and must come from the owner.
    | slug: used in download file names.
    | The web address is NOT part of the brand: links always come from APP_URL, so the domain stays
    | configurable until ownership and DNS of nadimshop.com are confirmed (see docs/staging-runbook.md).
    |
    */

    'name' => env('APP_NAME', 'NadimShop.com'),

    'tagline' => 'Bakery · Restaurant · Café · Food Supply',

    'slug' => env('BRAND_SLUG', 'nadimshop'),

];
