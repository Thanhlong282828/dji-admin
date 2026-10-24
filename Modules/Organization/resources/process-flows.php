<?php

$organization = static fn (string $current, string $category = 'MASTER_DATA'): array => [
    'id' => 'organization.company-structure-workforce', 'category' => $category, 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['companies', 'branches', 'cost-centers', 'organization-units', 'organization-tree', 'job-positions', 'headcount', 'employees']),
];

return [
    'cost-centers' => $organization('cost-centers'),
    'organization-units' => $organization('organization-units'),
    'organization-tree' => $organization('organization-tree', 'READ_MODEL'),
    'job-positions' => $organization('job-positions'),
    'headcount' => $organization('headcount', 'REPORT'),
];
