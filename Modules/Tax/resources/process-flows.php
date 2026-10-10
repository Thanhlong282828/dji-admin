<?php

$nodes = [
    ['key' => 'sales-receivable', 'label' => 'Customer Invoice', 'icon' => 'I', 'help' => 'Commerce source cung cấp taxable amount và VAT_OUTPUT classification.'],
    ['key' => 'vendor-bills', 'label' => 'Vendor Bill', 'icon' => 'B', 'help' => 'Procurement source cung cấp taxable amount và VAT_INPUT classification.'],
    ['key' => 'vat-policy-workspace', 'label' => 'VAT Policy', 'icon' => '?', 'help' => 'TaxPolicyResolver chọn approved effective policy theo tenant, ngày và currency.'],
    ['key' => 'accounting-policy-workspace', 'label' => 'Account Mapping', 'icon' => 'M', 'help' => 'Accounting Policy xác định AR/AP, revenue/expense và tax account.'],
    ['key' => 'accounting', 'label' => 'Tax Journal', 'icon' => 'J', 'help' => 'SourceAccountingPostingService tạo balanced immutable journal có policy lineage.'],
    ['key' => 'finance-tax-dashboard', 'label' => 'Tax Reporting', 'icon' => 'R', 'help' => 'Read side tổng hợp Output VAT, Input VAT và Net VAT từ immutable evidence.'],
];

return [
    'vat-policy-workspace' => [
        'id' => 'tax.vat-policy-workspace',
        'category' => 'CONTROL',
        'current' => 'vat-policy-workspace',
        'nodes' => $nodes,
    ],
];
