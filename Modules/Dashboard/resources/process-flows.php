<?php

$definition = static fn (string $current, array $nodes): array => [
    'id' => 'dashboard.'.$current, 'category' => 'READ_MODEL', 'current' => $current,
    'nodes' => array_map(static fn (mixed $key): array => is_string($key) ? ['key' => $key] : throw new InvalidArgumentException('Dashboard flow node must be a string.'), $nodes),
];

return [
    'dashboard' => $definition('dashboard', ['companies', 'employees', 'attendance', 'payroll-runs', 'dashboard']),
    'executive-dashboard' => $definition('executive-dashboard', ['enterprise-analytics', 'finance-overview', 'supply-chain-overview', 'executive-dashboard']),
    'supply-chain-overview' => $definition('supply-chain-overview', ['purchase-orders', 'goods-receipts', 'seller-inventory', 'sales-receivable', 'seller-orders', 'supply-chain-overview']),
    'enterprise-analytics' => $definition('enterprise-analytics', ['dashboard', 'finance-overview', 'sales-receivable', 'supply-chain-overview', 'enterprise-analytics']),
];
