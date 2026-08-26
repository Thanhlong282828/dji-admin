<?php

return [
    'inventory' => [
        'low_stock_threshold' => (int) env('COMMERCE_LOW_STOCK_THRESHOLD', 5),
        'cart_quantity_limit' => (int) env('COMMERCE_CART_QUANTITY_LIMIT', 99),
    ],
];
