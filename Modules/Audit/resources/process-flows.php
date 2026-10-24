<?php

return ['audit' => [
    'id' => 'audit.domain-events-evidence', 'category' => 'CONTROL', 'current' => 'audit',
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['employees', 'workflow', 'iam', 'integrations', 'audit']),
]];
