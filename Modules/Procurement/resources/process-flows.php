<?php

return [
    'procurement' => [
        'current' => 'procurement',
        'nodes' => [
            ['key' => 'vendor', 'label' => 'Nhà cung cấp', 'permission' => 'procurement.view', 'icon' => 'NCC'],
            ['key' => 'purchase-request', 'label' => 'Yêu cầu mua', 'permission' => 'procurement.view', 'icon' => 'PR'],
            ['key' => 'approval-inbox', 'label' => 'Phê duyệt', 'icon' => '✓'],
            ['key' => 'procurement', 'label' => 'PO / Nhận hàng / AP', 'icon' => 'PO'],
            ['key' => 'seller-inventory', 'label' => 'Kho hàng', 'icon' => 'KHO'],
            ['key' => 'treasury', 'label' => 'Thanh toán / Treasury', 'icon' => '₫'],
            ['key' => 'accounting', 'label' => 'Kế toán', 'icon' => 'KT'],
        ],
    ],
];
