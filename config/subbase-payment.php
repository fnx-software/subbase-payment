<?php

return [
    'driver' => env('SUBBASE_PAYMENT_DRIVER'),

    'checkout' => [
        'path' => 'checkout',
        'middleware' => ['web'],
        // Resolve the subscription owner; override in a host app to bill a
        // tenant, school, organization or other Eloquent model.
        'billable_resolver' => \Nafiswatsiq\SubbasePayment\Support\AuthenticatedBillableResolver::class,
        'billable_models' => [
            'user' => config('auth.providers.users.model', \\Illuminate\\Foundation\\Auth\\User::class),
        ],
        'subscription_name' => 'default',
        'return_url' => null,
        'cancel_url' => null,
    ],

    'currency_scales' => [],

    'mail' => [
        'send_invoice' => env('SUBBASE_PAYMENT_SEND_INVOICE', false),
    ],

    'tables' => [
        'subscription_payments' => 'subscription_payments',
        'payment_webhook_logs' => 'payment_webhook_logs',
        'processed_payment_events' => 'subbase_processed_payment_events',
    ],

    'permissions' => [
        'subscription_payment' => null,
        'payment_webhook_log' => null,
    ],

    'webhook' => [
        'path' => 'subbase-payment/webhook',
        'middleware' => [],
    ],

    'gateways' => [
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],
        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        ],
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'xendit' => [
            'secret_key' => env('XENDIT_SECRET_KEY'),
            'webhook_verification_token' => env('XENDIT_WEBHOOK_VERIFICATION_TOKEN'),
        ],
        'paddle' => [
            'api_key' => env('PADDLE_API_KEY'),
            'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
            'environment' => env('PADDLE_ENVIRONMENT', 'sandbox'),
        ],
    ],
];
