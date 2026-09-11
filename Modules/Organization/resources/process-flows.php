<?php

return [
    'companies' => ['current' => 'companies', 'nodes' => [
        ['key' => 'companies', 'label' => 'Công ty', 'icon' => 'CT'],
        ['key' => 'branches', 'label' => 'Chi nhánh', 'icon' => 'CN'],
        ['key' => 'organization-units', 'label' => 'Đơn vị tổ chức', 'icon' => 'DV'],
        ['key' => 'job-positions', 'label' => 'Vị trí', 'icon' => 'VT'],
        ['key' => 'employees', 'label' => 'Nhân viên', 'icon' => 'NV'],
    ]],
    'branches' => ['current' => 'branches', 'nodes' => [
        ['key' => 'companies', 'label' => 'Công ty', 'icon' => 'CT'],
        ['key' => 'branches', 'label' => 'Chi nhánh', 'icon' => 'CN'],
        ['key' => 'organization-units', 'label' => 'Đơn vị tổ chức', 'icon' => 'DV'],
        ['key' => 'employees', 'label' => 'Nhân viên', 'icon' => 'NV'],
    ]],
    'cost-centers' => ['current' => 'cost-centers', 'nodes' => [
        ['key' => 'companies', 'label' => 'Công ty', 'icon' => 'CT'],
        ['key' => 'cost-centers', 'label' => 'Cost Center', 'icon' => 'CC'],
        ['key' => 'organization-units', 'label' => 'Đơn vị tổ chức', 'icon' => 'DV'],
        ['key' => 'employees', 'label' => 'Assignment', 'icon' => 'AS'],
        ['key' => 'cost-center-cost-management', 'label' => 'Chi phí Cost Center', 'icon' => 'CP'],
    ]],
];
