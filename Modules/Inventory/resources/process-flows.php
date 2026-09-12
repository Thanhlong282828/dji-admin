<?php

return [
    'seller-inventory' => ['id' => 'inventory.stock-availability', 'category' => 'READ_MODEL', 'current' => 'seller-inventory', 'nodes' => [
        ['key' => 'products', 'label' => 'Sản phẩm / SKU', 'icon' => 'SKU'],
        ['key' => 'goods-receipts', 'label' => 'Nhập mua / GR', 'icon' => 'GR'],
        ['key' => 'seller-inventory', 'label' => 'Tồn kho', 'icon' => 'KHO'],
        ['key' => 'inventory-movements', 'label' => 'Sổ chuyển động', 'icon' => 'MV'],
        ['key' => 'sales-receivable', 'label' => 'Fulfillment / COGS', 'icon' => 'COGS'],
    ]],
    'inventory-movements' => ['id' => 'inventory.movement-ledger', 'category' => 'REPORT', 'current' => 'inventory-movements', 'nodes' => [
        ['key' => 'seller-inventory', 'label' => 'Tồn kho', 'icon' => 'KHO'],
        ['key' => 'inventory-movements', 'label' => 'Nhập / Xuất / Chuyển', 'icon' => 'MV'],
        ['key' => 'supply-chain-overview', 'label' => 'SCM Overview', 'icon' => 'SCM'],
    ]],
];
