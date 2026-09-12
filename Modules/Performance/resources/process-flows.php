<?php

$talent = static fn (string $current): array => [
    'id' => 'performance.employee-talent-lifecycle', 'category' => 'BUSINESS_PROCESS', 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['employees', 'performance', 'career', 'employee-lifecycle']),
];
$planningNodes = ['organization-units', 'headcount', 'employees', 'recruitment', 'analytics', 'forecasting', 'executive-planning', 'planning-execution', 'enterprise-reports'];
$planning = static fn (string $current, string $category): array => [
    'id' => 'performance.enterprise-planning', 'category' => $category, 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], $planningNodes),
];

return [
    'performance' => $talent('performance'), 'career' => $talent('career'),
    'analytics' => $planning('analytics', 'READ_MODEL'),
    'forecasting' => $planning('forecasting', 'READ_MODEL'),
    'executive-planning' => $planning('executive-planning', 'CONTROL'),
    'planning-execution' => $planning('planning-execution', 'CONTROL'),
    'enterprise-reports' => $planning('enterprise-reports', 'REPORT'),
];
