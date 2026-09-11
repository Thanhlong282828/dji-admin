<?php

$payroll = [
    ['key' => 'employees', 'label' => 'Nhân viên / Assignment', 'icon' => 'NV'],
    ['key' => 'attendance', 'label' => 'Chấm công', 'icon' => 'CC'],
    ['key' => 'leave', 'label' => 'Nghỉ phép', 'icon' => 'NP'],
    ['key' => 'overtime', 'label' => 'Tăng ca', 'icon' => 'OT'],
    ['key' => 'payroll-inputs', 'label' => 'Input snapshot', 'icon' => 'IN'],
    ['key' => 'payroll-runs', 'label' => 'Payroll Run', 'icon' => 'RUN'],
    ['key' => 'payslips', 'label' => 'Phiếu lương', 'icon' => 'PL'],
    ['key' => 'accounting', 'label' => 'Payroll Accounting', 'icon' => 'KT'],
];

return [
    'payroll-periods' => ['current' => 'payroll-periods', 'nodes' => array_merge([['key' => 'payroll-periods', 'label' => 'Kỳ lương', 'icon' => 'KỲ']], array_slice($payroll, 4))],
    'payroll-inputs' => ['current' => 'payroll-inputs', 'nodes' => [
        ['key' => 'employees', 'label' => 'Nhân viên / Assignment', 'icon' => 'NV'],
        ['key' => 'attendance', 'label' => 'Chấm công', 'icon' => 'CC'],
        ['key' => 'leave', 'label' => 'Nghỉ phép', 'icon' => 'NP'],
        ['key' => 'overtime', 'label' => 'Tăng ca', 'icon' => 'OT'],
        ['key' => 'payroll-periods', 'label' => 'Kỳ lương', 'icon' => 'KỲ'],
        ['key' => 'payroll-inputs', 'label' => 'Input snapshot', 'icon' => 'IN'],
        ['key' => 'payroll-runs', 'label' => 'Payroll Run', 'icon' => 'RUN'],
    ]],
    'payroll-runs' => ['current' => 'payroll-runs', 'nodes' => $payroll],
    'payslips' => ['current' => 'payslips', 'nodes' => $payroll],
];
