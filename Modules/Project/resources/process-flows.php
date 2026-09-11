<?php

$project = [
    ['key' => 'projects', 'label' => 'Dự án', 'icon' => 'DA'],
    ['key' => 'members', 'label' => 'Thành viên', 'permission' => 'project.view', 'icon' => 'TV'],
    ['key' => 'milestones', 'label' => 'Milestone', 'permission' => 'project.view', 'icon' => 'MS'],
    ['key' => 'tasks', 'label' => 'Công việc / Phụ thuộc', 'permission' => 'project.view', 'icon' => 'CV'],
    ['key' => 'timesheets', 'label' => 'Timesheet', 'permission' => 'project.view', 'icon' => 'TS'],
    ['key' => 'finance-operations', 'label' => 'Expense / Procurement', 'icon' => 'CP'],
    ['key' => 'accounting', 'label' => 'Kế toán / Cost Center', 'icon' => 'KT'],
];

return [
    'project-overview' => ['current' => 'project-overview', 'nodes' => array_merge([['key' => 'project-overview', 'label' => 'Tổng quan dự án', 'icon' => 'DA']], array_slice($project, 1))],
    'projects' => ['current' => 'projects', 'nodes' => $project],
];
