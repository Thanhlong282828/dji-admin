<?php

$definition = static fn (string $current, string $category, array $nodes): array => [
    'id' => 'finance.'.$current, 'category' => $category, 'current' => $current,
    'nodes' => array_map(static fn (mixed $key): array => is_string($key) ? ['key' => $key] : throw new InvalidArgumentException('Finance flow node must be a string.'), $nodes),
];

return [
    'finance-overview' => $definition('finance-overview', 'READ_MODEL', ['sales-receivable', 'vendor-bills', 'payroll-runs', 'treasury', 'accounting', 'finance-overview']),
    'finance-tax-dashboard' => $definition('finance-tax-dashboard', 'READ_MODEL', ['payroll-runs', 'tax-policies', 'insurance-policies', 'finance-tax-dashboard', 'government']),
    'cost-center-cost-management' => $definition('cost-center-cost-management', 'READ_MODEL', ['payroll-runs', 'finance-operations', 'cost-centers', 'cost-center-cost-management', 'finance-overview']),
    'finance-operations' => $definition('finance-operations', 'BUSINESS_PROCESS', ['finance-operations', 'approval-inbox', 'treasury', 'accounting', 'finance-overview']),
    'treasury' => $definition('treasury', 'BUSINESS_PROCESS', ['sales-receivable', 'vendor-bills', 'payments', 'treasury', 'accounting', 'financial-statements']),
    'accounting-period-close' => $definition('accounting-period-close', 'CONTROL', ['accounting', 'payroll-finance-reconciliation', 'accounting-period-close', 'financial-statements']),
    'accounting-adjustments' => $definition('accounting-adjustments', 'BUSINESS_PROCESS', ['accounting', 'accounting-adjustments', 'approval-inbox', 'financial-statements']),
    'branch-financial-performance' => $definition('branch-financial-performance', 'READ_MODEL', ['branches', 'sales-receivable', 'payroll-runs', 'finance-operations', 'branch-financial-performance', 'enterprise-reports']),
    'payroll-finance-reconciliation' => $definition('payroll-finance-reconciliation', 'CONTROL', ['payroll-runs', 'accounting', 'payroll-finance-reconciliation', 'financial-statements']),
    'financial-statements' => $definition('financial-statements', 'REPORT', ['accounting', 'financial-statements', 'executive-dashboard']),
    'fiscal-years' => $definition('fiscal-years', 'CONTROL', ['accounting', 'accounting-period-close', 'fiscal-years', 'financial-statements']),
    'accounting' => $definition('accounting', 'BUSINESS_PROCESS', ['sales-receivable', 'vendor-bills', 'payroll-runs', 'treasury', 'accounting', 'financial-statements']),
    'accounting-policy-workspace' => $definition('accounting-policy-workspace', 'CONTROL', ['accounting-configuration', 'accounting-policy-workspace', 'accounting', 'financial-statements']),
    'fx-rate-governance' => $definition('fx-rate-governance', 'CONTROL', ['accounting-policy-workspace', 'fx-rate-governance', 'fx-exposure', 'fx-revaluation']),
    'fx-exposure' => $definition('fx-exposure', 'READ_MODEL', ['sales-receivable', 'vendor-bills', 'treasury', 'fx-rate-governance', 'fx-exposure', 'fx-revaluation']),
    'fx-revaluation' => $definition('fx-revaluation', 'BUSINESS_PROCESS', ['fx-exposure', 'fx-rate-governance', 'fx-revaluation', 'accounting', 'financial-statements']),
];
