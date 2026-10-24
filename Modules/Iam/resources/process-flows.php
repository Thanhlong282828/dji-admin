<?php

$nodes = ['roles-permissions', 'access-requests', 'access-reviews', 'privileged', 'segregation-of-duties', 'iam', 'audit'];
$definition = static fn (string $current, string $category): array => [
    'id' => 'iam.access-governance', 'category' => $category, 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], $nodes),
];

return [
    'iam' => $definition('iam', 'CONTROL'),
    'access-requests' => $definition('access-requests', 'BUSINESS_PROCESS'),
    'roles-permissions' => $definition('roles-permissions', 'CONFIGURATION'),
    'privileged' => $definition('privileged', 'CONTROL'),
    'segregation-of-duties' => $definition('segregation-of-duties', 'CONTROL'),
    'access-reviews' => $definition('access-reviews', 'CONTROL'),
];
