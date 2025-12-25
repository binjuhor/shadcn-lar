<?php

return [
    'name' => 'Ecommerce',

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for the ecommerce module
    |
    */
    'currency' => env('ECOMMERCE_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Tax Rate
    |--------------------------------------------------------------------------
    |
    | The default tax rate percentage
    |
    */
    'tax_rate' => env('ECOMMERCE_TAX_RATE', 0),

    /*
    |--------------------------------------------------------------------------
    | Default Shipping Cost
    |--------------------------------------------------------------------------
    |
    | The default shipping cost
    |
    */
    'default_shipping_cost' => env('ECOMMERCE_DEFAULT_SHIPPING_COST', 0),

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | The default threshold for low stock alerts
    |
    */
    'low_stock_threshold' => env('ECOMMERCE_LOW_STOCK_THRESHOLD', 5),
];
