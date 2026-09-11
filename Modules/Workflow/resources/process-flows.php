<?php

$workflow = [
    ['key' => 'owner-transaction', 'label' => 'Nghiệp vụ sở hữu', 'permission' => 'workflow.view', 'icon' => 'NV'],
    ['key' => 'submit', 'label' => 'Submit', 'permission' => 'workflow.view', 'icon' => '→'],
    ['key' => 'workflow-tasks', 'label' => 'Approval Task', 'icon' => 'TK'],
    ['key' => 'approval-inbox', 'label' => 'Approval Inbox', 'icon' => 'IN'],
    ['key' => 'workflow', 'label' => 'Quyết định', 'icon' => '✓'],
    ['key' => 'owner-transition', 'label' => 'Owner transition', 'permission' => 'workflow.view', 'icon' => 'ST'],
    ['key' => 'notifications', 'label' => 'Thông báo', 'icon' => 'TB'],
];

return [
    'workflow' => ['current' => 'workflow', 'nodes' => $workflow],
    'workflow-tasks' => ['current' => 'workflow-tasks', 'nodes' => $workflow],
    'approval-inbox' => ['current' => 'approval-inbox', 'nodes' => $workflow],
    'workflow-definitions' => ['current' => 'workflow-definitions', 'nodes' => [
        ['key' => 'workflow-definitions', 'label' => 'Định nghĩa quy trình', 'icon' => 'ĐN'],
        ['key' => 'workflow', 'label' => 'Workflow instance', 'icon' => 'WF'],
        ['key' => 'workflow-tasks', 'label' => 'Approval Task', 'icon' => 'TK'],
        ['key' => 'approval-inbox', 'label' => 'Approval Inbox', 'icon' => 'IN'],
    ]],
];
