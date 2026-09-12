<?php

$commerce = ['seller-governance', 'seller-dashboard', 'store-profile', 'product-categories', 'products', 'seller-inventory', 'inventory-movements', 'seller-orders', 'commerce-sales-history', 'commerce-payments'];
$definition = static fn (string $current, string $category, ?array $nodes = null): array => [
    'id' => 'commerce.'.$current, 'category' => $category, 'current' => $current,
    'nodes' => array_map(static fn (mixed $node): array => is_array($node) ? $node : (is_string($node) ? ['key' => $node] : throw new InvalidArgumentException('Commerce flow node must be a string or array.')), $nodes ?? $commerce),
];

return [
    'sales-receivable' => $definition('sales-receivable', 'BUSINESS_PROCESS', [
        'branch-customers',
        ['key' => 'sales-order', 'label' => 'Đơn bán hàng', 'permission' => 'sales.view'],
        ['key' => 'fulfillment', 'label' => 'Fulfillment', 'permission' => 'sales.view'],
        'sales-receivable',
        ['key' => 'collection', 'label' => 'Thu tiền khách hàng', 'permission' => 'collection.view'],
        'treasury',
        'accounting',
    ]),
    'seller-dashboard' => $definition('seller-dashboard', 'READ_MODEL'),
    'store-profile' => $definition('store-profile', 'MASTER_DATA'),
    'seller-governance' => $definition('seller-governance', 'CONTROL'),
    'seller-orders' => $definition('seller-orders', 'BUSINESS_PROCESS'),
    'commerce-sales-history' => $definition('commerce-sales-history', 'READ_MODEL'),
    'commerce-payments' => $definition('commerce-payments', 'BUSINESS_PROCESS'),
];
