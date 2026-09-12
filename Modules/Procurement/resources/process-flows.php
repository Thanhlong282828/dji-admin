<?php

return [
    'procurement' => [
        'id' => 'procurement.vendor-to-ap',
        'category' => 'BUSINESS_PROCESS',
        'current' => 'procurement',
        'nodes' => [
            ['key' => 'vendor', 'label' => 'Nhà cung cấp', 'permission' => 'procurement.view', 'icon' => 'NCC'],
            ['key' => 'purchase-request', 'label' => 'Yêu cầu mua', 'permission' => 'procurement.view', 'icon' => 'PR'],
            ['key' => 'approval-inbox', 'label' => 'Phê duyệt', 'icon' => '✓'],
            ['key' => 'procurement', 'label' => 'PO', 'icon' => 'PO'],
            ['key' => 'goods-or-service', 'label' => 'Goods Receipt / Service Acceptance', 'icon' => '✓'],
            ['key' => 'seller-inventory', 'label' => 'Kho hàng', 'icon' => 'KHO'],
            ['key' => 'treasury', 'label' => 'Thanh toán / Treasury', 'icon' => '₫'],
            ['key' => 'accounting', 'label' => 'Kế toán', 'icon' => 'KT'],
        ],
    ],
];
