<?php

$leave = static fn (string $current): array => [
    'id' => 'leave.policy-request-payroll', 'category' => $current === 'leave-management' ? 'CONFIGURATION' : 'BUSINESS_PROCESS', 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['employees', 'leave-management', 'leave', 'attendance', 'payroll-inputs']),
];

return ['leave-management' => $leave('leave-management'), 'leave' => $leave('leave')];
