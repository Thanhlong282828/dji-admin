<?php

return [
    'employees' => ['current' => 'employees', 'nodes' => [
        ['key' => 'organization-units', 'label' => 'Đơn vị tổ chức', 'icon' => 'DV'],
        ['key' => 'job-positions', 'label' => 'Vị trí', 'icon' => 'VT'],
        ['key' => 'employees', 'label' => 'Hồ sơ nhân viên', 'icon' => 'NV'],
        ['key' => 'employee-lifecycle', 'label' => 'Vòng đời / Assignment', 'icon' => 'AS'],
        ['key' => 'attendance', 'label' => 'Chấm công', 'icon' => 'CC'],
        ['key' => 'payroll-inputs', 'label' => 'Payroll Input', 'icon' => 'PI'],
    ]],
    'attendance' => ['current' => 'attendance', 'nodes' => [
        ['key' => 'employees', 'label' => 'Nhân viên', 'icon' => 'NV'],
        ['key' => 'shifts', 'label' => 'Ca / Lịch làm việc', 'icon' => 'CA'],
        ['key' => 'attendance', 'label' => 'Chấm công', 'icon' => 'CC'],
        ['key' => 'payroll-inputs', 'label' => 'Payroll Input', 'icon' => 'PI'],
        ['key' => 'payroll-runs', 'label' => 'Payroll Run', 'icon' => 'PR'],
    ]],
    'leave' => ['current' => 'leave', 'nodes' => [
        ['key' => 'employees', 'label' => 'Nhân viên', 'icon' => 'NV'],
        ['key' => 'leave-management', 'label' => 'Chính sách / Số dư', 'icon' => 'CS'],
        ['key' => 'leave', 'label' => 'Yêu cầu nghỉ', 'icon' => 'NP'],
        ['key' => 'attendance', 'label' => 'Chấm công', 'icon' => 'CC'],
        ['key' => 'payroll-inputs', 'label' => 'Payroll Input', 'icon' => 'PI'],
    ]],
    'overtime' => ['current' => 'overtime', 'nodes' => [
        ['key' => 'employees', 'label' => 'Nhân viên', 'icon' => 'NV'],
        ['key' => 'attendance', 'label' => 'Thời gian thực tế', 'icon' => 'CC'],
        ['key' => 'overtime', 'label' => 'Tăng ca duyệt', 'icon' => 'OT'],
        ['key' => 'payroll-inputs', 'label' => 'Payroll Input', 'icon' => 'PI'],
    ]],
];
