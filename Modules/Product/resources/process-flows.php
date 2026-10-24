<?php

$catalog = static fn (string $current): array => [
    'id' => 'product.catalog-to-sales', 'category' => $current === 'product-categories' ? 'MASTER_DATA' : 'BUSINESS_PROCESS', 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['product-categories', 'products', 'seller-inventory', 'seller-orders', 'commerce-sales-history']),
];

return ['products' => $catalog('products'), 'product-categories' => $catalog('product-categories')];
