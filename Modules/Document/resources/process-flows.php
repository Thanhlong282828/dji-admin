<?php

return [
    'enterprise-documents' => ['current' => 'enterprise-documents', 'nodes' => [
        ['key' => 'owner-record', 'label' => 'Hồ sơ nghiệp vụ', 'permission' => 'document.view', 'icon' => 'HS'],
        ['key' => 'enterprise-documents', 'label' => 'Tài liệu / Phiên bản', 'icon' => 'TL'],
        ['key' => 'integrity', 'label' => 'SHA / Quyền tải', 'permission' => 'document.view', 'icon' => 'SHA'],
        ['key' => 'approval-inbox', 'label' => 'Phê duyệt khi áp dụng', 'icon' => '✓'],
        ['key' => 'audit', 'label' => 'Audit trail', 'icon' => 'AT'],
    ]],
];
