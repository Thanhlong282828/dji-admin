<?php

return [
    'inventory' => [
        'low_stock_threshold' => (int) env('COMMERCE_LOW_STOCK_THRESHOLD', 5),
        'cart_quantity_limit' => (int) env('COMMERCE_CART_QUANTITY_LIMIT', 99),
    ],
    'crm' => [
        // Deterministic, explainable recency thresholds. These are operating
        // classifications, not AI scores and never alter customer records.
        'new_days' => (int) env('COMMERCE_CRM_NEW_DAYS', 30),
        'repeat_days' => (int) env('COMMERCE_CRM_REPEAT_DAYS', 90),
        'at_risk_days' => (int) env('COMMERCE_CRM_AT_RISK_DAYS', 180),
    ],
];
