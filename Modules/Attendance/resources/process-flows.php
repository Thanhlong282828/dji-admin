<?php

$attendance = static fn (string $current): array => [
    'id' => 'attendance.workforce-to-payroll', 'category' => $current === 'shifts' ? 'CONFIGURATION' : 'BUSINESS_PROCESS', 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['employees', 'shifts', 'attendance', 'overtime', 'payroll-inputs']),
];

return ['shifts' => $attendance('shifts'), 'attendance' => $attendance('attendance'), 'overtime' => $attendance('overtime')];
