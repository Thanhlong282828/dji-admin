<?php

$nodes = [
    ['key' => 'crm-pipeline', 'navigation' => 'crm-pipeline', 'label' => 'Pipeline', 'icon' => '?', 'help' => 'CRM Module · GetCrmPipelineQuery · tổng hợp Lead và Opportunity theo Company/Branch.'],
    ['key' => 'crm-leads', 'navigation' => 'crm-leads', 'label' => 'Leads', 'icon' => 'L', 'help' => 'Lead canonical là khách hàng tiềm năng chưa xác nhận. Bước kế tiếp: Opportunity.'],
    ['key' => 'crm-opportunities', 'navigation' => 'crm-opportunities', 'label' => 'Opportunities', 'icon' => 'O', 'help' => 'Opportunity được đánh giá và nối sang Customer/Quotation; chưa tạo journal.'],
    ['key' => 'sales-receivable', 'navigation' => 'sales-receivable', 'label' => 'Sales · AR', 'icon' => '₫', 'help' => 'Quotation → Sales Order → Invoice → Collection.'],
];

$definition = static fn (string $current, string $id): array => [
    'id' => $id,
    'category' => 'BUSINESS_PROCESS',
    'current' => $current,
    'nodes' => $nodes,
];

return [
    'crm-pipeline' => $definition('crm-pipeline', 'crm.pipeline'),
    'crm-leads' => $definition('crm-leads', 'crm.leads'),
    'crm-opportunities' => $definition('crm-opportunities', 'crm.opportunities'),
];
