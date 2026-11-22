<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    */

    // Online payments (bKash / Nagad / card) hold stock this long before payment.
    'reservation_minutes' => (int) env('CHECKOUT_RESERVATION_MINUTES', 15),

    // Unpaid online orders are cancelled this long after their reservation expired (late-callback window).
    'online_unpaid_grace_minutes' => (int) env('CHECKOUT_ONLINE_GRACE_MINUTES', 60),

    // Unpaid bank-transfer orders are cancelled (and restocked) after this many hours.
    'bank_transfer_hours' => (int) env('CHECKOUT_BANK_TRANSFER_HOURS', 48),

    // Cash on delivery is refused above this order total (paisa). Owner to confirm.
    'cod_max_paisa' => (int) env('CHECKOUT_COD_MAX_PAISA', 5000000),

    // Methods offered at checkout.
    'payment_methods' => array_filter(explode(',', (string) env('CHECKOUT_PAYMENT_METHODS', 'bkash,nagad,card,bank_transfer,cod'))),

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Only "test" mode exists. Live bKash / Nagad / card gateways are NOT implemented: they need the
    | providers' verified merchant documentation and credentials. Selecting "live" fails loudly.
    |
    */

    'payments' => [
        'mode' => env('PAYMENTS_MODE', 'test'),
        'test_webhook_secrets' => [
            'bkash' => env('PAYMENTS_TEST_SECRET_BKASH', 'test-only-bkash-secret-change-me'),
            'nagad' => env('PAYMENTS_TEST_SECRET_NAGAD', 'test-only-nagad-secret-change-me'),
            'card' => env('PAYMENTS_TEST_SECRET_CARD', 'test-only-card-secret-change-me'),
        ],
        'webhook_tolerance_seconds' => 300,
    ],

    // Seller block on portal invoices. Placeholders until the owner confirms legal / VAT details.
    'seller' => [
        'name' => env('SELLER_LEGAL_NAME') ?: env('APP_NAME', 'NadimShop.com'), // trading name until the owner supplies the registered legal name
        'address' => env('SELLER_ADDRESS', 'Address pending owner confirmation'),
        'phone' => env('SELLER_PHONE', 'Pending owner confirmation'),
        'vat_registration' => env('SELLER_VAT_BIN'),
    ],

    // Team seats per business / reseller organization (members + pending invitations).
    'organization_max_members' => (int) env('ORGANIZATION_MAX_MEMBERS', 20),

    // Shown to customers paying by bank transfer. Placeholders until the owner provides real details.
    'bank_transfer' => [
        'account_name' => env('BANK_ACCOUNT_NAME', 'Pending owner confirmation'),
        'account_number' => env('BANK_ACCOUNT_NUMBER', 'Pending owner confirmation'),
        'bank' => env('BANK_NAME', 'Pending owner confirmation'),
        'branch' => env('BANK_BRANCH', 'Pending owner confirmation'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | SMS uses the "log" driver until an SMS provider is contracted. Email uses Laravel's mailer
    | (the "log" mailer locally).
    |
    */

    'notifications' => [
        'sms_driver' => env('SMS_DRIVER', 'log'),
        'email_enabled' => (bool) env('ORDER_EMAILS_ENABLED', true),
        'sms_enabled' => (bool) env('ORDER_SMS_ENABLED', true),
    ],

];
