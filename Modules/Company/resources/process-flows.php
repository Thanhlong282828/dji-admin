<?php

$company = static fn (string $current): array => [
    'id' => 'company.organization-runtime', 'category' => 'MASTER_DATA', 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['companies', 'branches', 'organization-units', 'employees', 'finance-overview', 'seller-dashboard']),
];

return ['companies' => $company('companies'), 'branches' => $company('branches')];
