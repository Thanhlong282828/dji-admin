<?php

$runtime = ['employees', 'attendance', 'leave', 'overtime', 'payroll-periods', 'payroll-inputs', 'payroll-runs', 'payslips', 'payments', 'accounting'];
$definition = static fn (string $current, string $category, ?array $nodes = null): array => [
    'id' => 'payroll.'.$current, 'category' => $category, 'current' => $current,
    'nodes' => array_map(static fn (mixed $key): array => is_string($key) ? ['key' => $key] : throw new InvalidArgumentException('Payroll flow node must be a string.'), $nodes ?? $runtime),
];

return [
    'payroll-dashboard' => $definition('payroll-dashboard', 'CONFIGURATION', ['employees', 'payroll-dashboard', 'payroll-inputs', 'payroll-runs']),
    'payroll-periods' => $definition('payroll-periods', 'MASTER_DATA', ['payroll-dashboard', 'payroll-periods', 'payroll-inputs', 'payroll-runs']),
    'payroll-inputs' => $definition('payroll-inputs', 'BUSINESS_PROCESS', ['employees', 'attendance', 'leave', 'overtime', 'payroll-periods', 'payroll-inputs', 'payroll-runs']),
    'payroll-runs' => $definition('payroll-runs', 'BUSINESS_PROCESS'),
    'payroll-adjustments' => $definition('payroll-adjustments', 'BUSINESS_PROCESS', ['payroll-runs', 'payroll-adjustments', 'payslips']),
    'branch-bonuses' => $definition('branch-bonuses', 'BUSINESS_PROCESS', ['branches', 'employees', 'branch-bonuses', 'payroll-inputs', 'payroll-runs']),
    'payslips' => $definition('payslips', 'READ_MODEL'),
    'payments' => $definition('payments', 'BUSINESS_PROCESS', ['payroll-runs', 'payments', 'accounting']),
    'tax-administration' => $definition('tax-administration', 'READ_MODEL', ['employees', 'tax-policies', 'tax-administration', 'payroll-runs']),
    'tax-policies' => $definition('tax-policies', 'CONFIGURATION', ['employees', 'tax-policies', 'tax-administration', 'payroll-runs']),
    'insurance-administration' => $definition('insurance-administration', 'READ_MODEL', ['employees', 'insurance-policies', 'insurance-administration', 'payroll-runs']),
    'insurance-policies' => $definition('insurance-policies', 'CONFIGURATION', ['employees', 'insurance-policies', 'insurance-administration', 'payroll-runs']),
    'annual-tax' => $definition('annual-tax', 'REPORT', ['payroll-runs', 'annual-tax', 'accounting']),
    'government' => $definition('government', 'EXTERNAL', ['payroll-runs', 'government']),
    'accounting-configuration' => $definition('accounting-configuration', 'CONFIGURATION', ['payroll-dashboard', 'accounting-configuration', 'accounting']),
    'erp' => $definition('erp', 'EXTERNAL', ['accounting', 'erp-configuration', 'erp', 'integrations']),
    'erp-configuration' => $definition('erp-configuration', 'CONFIGURATION', ['accounting-configuration', 'erp-configuration', 'erp']),
];
