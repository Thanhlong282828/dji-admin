<?php

$commercialNodes = [
    ['key' => 'procurement-vendors', 'label' => 'Nhà cung cấp', 'icon' => 'NCC'],
    ['key' => 'purchase-requests', 'label' => 'Yêu cầu mua', 'icon' => 'PR'],
    ['key' => 'purchase-orders', 'label' => 'Đơn mua hàng', 'icon' => 'PO'],
];
$goodsNodes = [
    ...$commercialNodes,
    ['key' => 'goods-receipts', 'label' => 'Phiếu nhận hàng', 'icon' => 'GR'],
    ['key' => 'seller-inventory', 'label' => 'Kho hàng', 'icon' => 'KHO'],
    ['key' => 'vendor-bills', 'label' => 'Hóa đơn NCC · AP', 'icon' => 'AP'],
    ['key' => 'treasury', 'label' => 'Thanh toán / Treasury', 'icon' => '₫'],
    ['key' => 'accounting', 'label' => 'Kế toán', 'icon' => 'KT'],
];
$serviceNodes = [
    ...$commercialNodes,
    ['key' => 'service-acceptances', 'label' => 'Nghiệm thu dịch vụ', 'icon' => 'SA'],
    ['key' => 'vendor-bills', 'label' => 'Hóa đơn NCC · AP', 'icon' => 'AP'],
    ['key' => 'treasury', 'label' => 'Thanh toán / Treasury', 'icon' => '₫'],
    ['key' => 'accounting', 'label' => 'Kế toán', 'icon' => 'KT'],
];
$reportNodes = [
    ...$commercialNodes,
    ['key' => 'goods-receipts', 'label' => 'Phiếu nhận hàng', 'icon' => 'GR'],
    ['key' => 'service-acceptances', 'label' => 'Nghiệm thu dịch vụ', 'icon' => 'SA'],
    ['key' => 'vendor-bills', 'label' => 'Hóa đơn NCC · AP', 'icon' => 'AP'],
    ['key' => 'procurement-reports', 'label' => 'Báo cáo mua hàng', 'icon' => 'BC'],
    ['key' => 'treasury', 'label' => 'Thanh toán / Treasury', 'icon' => '₫'],
    ['key' => 'accounting', 'label' => 'Kế toán', 'icon' => 'KT'],
];

$definition = static fn (string $key, array $nodes): array => [
    'id' => 'procurement.vendor-to-ap.'.$key,
    'category' => 'BUSINESS_PROCESS',
    'current' => $key,
    'nodes' => $nodes,
];

return [
    'procurement-vendors' => $definition('procurement-vendors', $commercialNodes),
    'purchase-requests' => $definition('purchase-requests', $commercialNodes),
    'purchase-orders' => $definition('purchase-orders', $commercialNodes),
    'goods-receipts' => $definition('goods-receipts', $goodsNodes),
    'service-acceptances' => $definition('service-acceptances', $serviceNodes),
    'vendor-bills' => $definition('vendor-bills', $serviceNodes),
    'procurement-reports' => $definition('procurement-reports', $reportNodes),
];
