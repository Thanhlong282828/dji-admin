<?php

return ['onboarding' => [
    'id' => 'onboarding.offer-to-employee', 'category' => 'BUSINESS_PROCESS', 'current' => 'onboarding',
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['requisitions', 'candidates', 'applications', 'interviews', 'candidate-offers', 'onboarding', 'employees']),
]];
