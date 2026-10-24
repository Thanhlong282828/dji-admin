<?php

$pipeline = ['requisitions', 'candidates', 'applications', 'interviews', 'candidate-offers', 'onboarding', 'employees'];
$definition = static fn (string $current, ?array $nodes = null): array => [
    'id' => 'recruitment.candidate-to-hire', 'category' => 'BUSINESS_PROCESS', 'current' => $current,
    'nodes' => array_map(static fn (mixed $key): array => is_string($key) ? ['key' => $key] : throw new InvalidArgumentException('Recruitment flow node must be a string.'), $nodes ?? $pipeline),
];

return [
    'recruitment' => $definition('recruitment', array_merge(['recruitment'], $pipeline)),
    'requisitions' => $definition('requisitions'),
    'candidates' => $definition('candidates'),
    'applications' => $definition('applications'),
    'interviews' => $definition('interviews'),
    'candidate-offers' => $definition('candidate-offers'),
];
