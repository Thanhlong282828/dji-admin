<?php

$pos = ['companies', 'branches', 'branch-customers', 'company-services', 'branch-warehouse', 'customer-checkins', 'pos-dashboard', 'branch-sales', 'pos-receipts', 'branch-financial-performance'];
$definition = static fn (string $current, string $category): array => [
    'id' => 'pos.customer-checkout-revenue', 'category' => $category, 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], $pos),
];

return [
    'pos-dashboard' => $definition('pos-dashboard', 'READ_MODEL'),
    'customer-checkins' => $definition('customer-checkins', 'BUSINESS_PROCESS'),
    'branch-customers' => $definition('branch-customers', 'MASTER_DATA'),
    'company-services' => $definition('company-services', 'MASTER_DATA'),
    'branch-warehouse' => $definition('branch-warehouse', 'READ_MODEL'),
    'branch-sales' => $definition('branch-sales', 'READ_MODEL'),
    'pos-receipts' => $definition('pos-receipts', 'READ_MODEL'),
];
