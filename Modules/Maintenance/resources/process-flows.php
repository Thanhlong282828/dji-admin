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
    'maintenance-overview' => ['current' => 'maintenance-overview', 'nodes' => array_merge([['key' => 'maintenance-overview', 'label' => 'Tổng quan bảo trì', 'icon' => 'BT']], array_slice($maintenance, 1))],
    'maintenance-requests' => ['current' => 'maintenance-requests', 'nodes' => $maintenance],
    'maintenance-plans' => ['current' => 'maintenance-plans', 'nodes' => $maintenance],
    'maintenance-work-orders' => ['current' => 'maintenance-work-orders', 'nodes' => $maintenance],
];
