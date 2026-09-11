<?php

return [
    'sales-receivable' => [
        'current' => 'sales-receivable',
        'nodes' => [
            ['key' => 'branch-customers', 'label' => 'Khách hàng', 'icon' => 'KH'],
            ['key' => 'quotation', 'label' => 'Báo giá', 'permission' => 'sales.view', 'icon' => 'BG'],
            ['key' => 'sales-order', 'label' => 'Đơn bán', 'permission' => 'sales.view', 'icon' => 'SO'],
            ['key' => 'fulfillment', 'label' => 'Xuất hàng', 'permission' => 'sales.view', 'icon' => 'FH'],
            ['key' => 'sales-receivable', 'label' => 'Hóa đơn / AR', 'icon' => 'AR'],
            ['key' => 'collection', 'label' => 'Thu tiền', 'permission' => 'sales.view', 'icon' => 'TT'],
            ['key' => 'treasury', 'label' => 'Treasury', 'icon' => '₫'],
            ['key' => 'accounting', 'label' => 'Kế toán', 'icon' => 'KT'],
        ],
    ],
];
