<?php

$employee = static fn (string $current, string $category, array $nodes): array => [
    'id' => 'employee.'.$current, 'category' => $category, 'current' => $current,
    'nodes' => array_map(static fn (mixed $key): array => is_string($key) ? ['key' => $key] : throw new InvalidArgumentException('Employee flow node must be a string.'), $nodes),
];

return [
    'employees' => $employee('employees', 'MASTER_DATA', ['organization-units', 'job-positions', 'employees', 'employee-lifecycle', 'attendance', 'payroll-inputs']),
    'employee-portal' => $employee('employee-portal', 'READ_MODEL', ['employee-portal', 'attendance', 'leave', 'overtime', 'payslips']),
    'employee-lifecycle' => $employee('employee-lifecycle', 'BUSINESS_PROCESS', ['employees', 'employee-lifecycle', 'payroll-dashboard', 'payroll-inputs']),
    'hr-operations' => $employee('hr-operations', 'BUSINESS_PROCESS', ['employees', 'hr-operations', 'employee-lifecycle', 'payroll-inputs', 'notifications']),
    'hr-governance' => $employee('hr-governance', 'CONTROL', ['employees', 'hr-governance', 'audit']),
];
