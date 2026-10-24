<?php

return ['integrations' => [
    'id' => 'integration.configuration-runtime-evidence', 'category' => 'EXTERNAL', 'current' => 'integrations',
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['workflow', 'notifications', 'integrations', 'audit']),
]];
