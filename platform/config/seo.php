<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search engine indexing
    |--------------------------------------------------------------------------
    |
    | Off by default. While off, every page sends "noindex" and robots.txt
    | disallows everything, so development / mock catalog data can never be
    | indexed. Turn on only for the approved production launch.
    |
    */

    'indexing_enabled' => (bool) env('SEO_INDEXING_ENABLED', false),

    'site_name' => env('APP_NAME', 'Baking House'),

    'default_description' => 'Complete supply for bakery, restaurant, café, Chinese kitchen and food businesses in Bangladesh: ingredients, tools, commercial kitchen equipment, packaging, bulk and wholesale.',

    /*
    | Paths that must never be indexed (robots.txt Disallow when indexing is on).
    */
    'disallow' => ['/account', '/admin', '/business/', '/reseller/', '/apply', '/login', '/register', '/cart', '/checkout', '/search', '/orders/', '/order/', '/invitations/', '/payments/', '/mfa/', '/api/'],

];
