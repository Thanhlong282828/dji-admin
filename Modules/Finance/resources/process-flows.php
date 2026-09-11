<?php

return [
    'cost-center-cost-management' => [
        'current' => 'cost-center-cost-management',
        'nodes' => [
            ['key' => 'source-events', 'label' => 'Nguồn nghiệp vụ', 'permission' => 'finance.operations.view', 'icon' => 'NV'],
            ['key' => 'historical-dimension', 'label' => 'Cost Center snapshot', 'permission' => 'finance.operations.view', 'icon' => 'CC'],
            ['key' => 'finance-operations', 'label' => 'Budget / Actual', 'icon' => 'BA'],
            ['key' => 'cost-center-cost-management', 'label' => 'Chi phí Cost Center', 'icon' => 'BC'],
            ['key' => 'finance-overview', 'label' => 'Tổng quan tài chính', 'icon' => 'TC'],
        ],
    ],
    'treasury' => [
        'current' => 'treasury',
        'nodes' => [
            ['key' => 'sales-receivable', 'label' => 'AR / Thu khách hàng', 'icon' => 'AR'],
            ['key' => 'procurement', 'label' => 'AP / Trả NCC', 'icon' => 'AP'],
            ['key' => 'treasury', 'label' => 'Treasury', 'icon' => '₫'],
            ['key' => 'accounting', 'label' => 'Sổ kế toán', 'icon' => 'KT'],
            ['key' => 'financial-statements', 'label' => 'Báo cáo tài chính', 'icon' => 'BC'],
        ],
    ],
    'accounting' => [
        'current' => 'accounting',
        'nodes' => [
            ['key' => 'source-transactions', 'label' => 'Nghiệp vụ nguồn', 'permission' => 'payroll.accounting.view', 'icon' => 'NV'],
            ['key' => 'accounting', 'label' => 'Bút toán', 'icon' => 'KT'],
            ['key' => 'financial-statements', 'label' => 'GL / Trial Balance / BCTC', 'icon' => 'GL'],
        ],
    ],
    'financial-statements' => [
        'current' => 'financial-statements',
        'nodes' => [
            ['key' => 'accounting', 'label' => 'Bút toán đã đăng', 'icon' => 'KT'],
            ['key' => 'general-ledger', 'label' => 'General Ledger', 'permission' => 'payroll.accounting.view', 'icon' => 'GL'],
            ['key' => 'trial-balance', 'label' => 'Trial Balance', 'permission' => 'payroll.accounting.view', 'icon' => 'TB'],
            ['key' => 'financial-statements', 'label' => 'Báo cáo tài chính', 'icon' => 'BC'],
            ['key' => 'executive-dashboard', 'label' => 'Executive Dashboard', 'icon' => 'DB'],
        ],
    ],
];
