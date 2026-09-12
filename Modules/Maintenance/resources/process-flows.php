<?php

$maintenance = [
    ['key' => 'finance-operations', 'label' => 'Tài sản', 'icon' => 'TS'],
    ['key' => 'maintenance-requests', 'label' => 'Yêu cầu bảo trì', 'icon' => 'YC'],
    ['key' => 'maintenance-plans', 'label' => 'Kế hoạch định kỳ', 'icon' => 'KH'],
    ['key' => 'maintenance-work-orders', 'label' => 'Lệnh công việc', 'icon' => 'WO'],
    ['key' => 'technician', 'label' => 'Kỹ thuật viên', 'permission' => 'maintenance.view', 'icon' => 'KT'],
    ['key' => 'parts-expense', 'label' => 'Vật tư / Chi phí', 'permission' => 'maintenance.view', 'icon' => 'VT'],
    ['key' => 'completed', 'label' => 'Hoàn tất / Lịch sử', 'permission' => 'maintenance.view', 'icon' => '✓'],
];

return [
    'maintenance-overview' => ['id' => 'maintenance.overview', 'category' => 'READ_MODEL', 'current' => 'maintenance-overview', 'nodes' => array_merge([['key' => 'maintenance-overview', 'label' => 'Tổng quan bảo trì', 'icon' => 'BT']], array_slice($maintenance, 1))],
    'maintenance-requests' => ['id' => 'maintenance.request', 'category' => 'BUSINESS_PROCESS', 'current' => 'maintenance-requests', 'nodes' => $maintenance],
    'maintenance-plans' => ['id' => 'maintenance.preventive-plan', 'category' => 'CONFIGURATION', 'current' => 'maintenance-plans', 'nodes' => $maintenance],
    'maintenance-work-orders' => ['id' => 'maintenance.work-order', 'category' => 'BUSINESS_PROCESS', 'current' => 'maintenance-work-orders', 'nodes' => $maintenance],
];
