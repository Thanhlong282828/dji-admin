<?php

$definition = static fn (string $key, string $category, array $nodes): array => [
    'id' => 'core.'.$key,
    'category' => $category,
    'current' => $key,
    'nodes' => array_map(static fn (mixed $node): array => is_string($node) ? ['key' => $node] : throw new InvalidArgumentException('Core flow node must be a string.'), $nodes),
];

return [
    'reliability' => $definition('reliability', 'SYSTEM', ['observability', 'performance-engineering', 'reliability', 'releases']),
    'observability' => $definition('observability', 'SYSTEM', ['observability', 'performance-engineering', 'reliability', 'releases']),
    'performance-engineering' => $definition('performance-engineering', 'SYSTEM', ['observability', 'performance-engineering', 'reliability', 'releases']),
    'architecture-governance' => $definition('architecture-governance', 'CONTROL', ['architecture-governance', 'test-quality', 'releases']),
    'test-quality' => $definition('test-quality', 'CONTROL', ['architecture-governance', 'test-quality', 'releases']),
    'api-contracts' => $definition('api-contracts', 'CONTROL', ['api-documentation', 'api-contracts', 'releases']),
    'releases' => $definition('releases', 'CONTROL', ['architecture-governance', 'test-quality', 'security-assurance', 'releases']),
    'configuration' => $definition('configuration', 'CONFIGURATION', ['configuration', 'reliability', 'releases']),
    'secrets' => $definition('secrets', 'CONFIGURATION', ['secrets', 'security-assurance', 'releases']),
    'data-protection' => $definition('data-protection', 'CONTROL', ['data-protection', 'data-lifecycle', 'data-quality']),
    'data-lifecycle' => $definition('data-lifecycle', 'CONTROL', ['data-protection', 'data-lifecycle', 'audit']),
    'data-quality' => $definition('data-quality', 'CONTROL', ['employees', 'attendance', 'payroll-runs', 'data-quality']),
    'continuity' => $definition('continuity', 'CONTROL', ['reliability', 'continuity', 'releases']),
    'security-assurance' => $definition('security-assurance', 'CONTROL', ['secrets', 'data-protection', 'security-assurance', 'releases']),
    'go-live-readiness' => $definition('go-live-readiness', 'CONTROL', ['architecture-governance', 'test-quality', 'security-assurance', 'releases', 'go-live-readiness']),
    'api-documentation' => $definition('api-documentation', 'REPORT', ['api-documentation', 'api-contracts', 'releases']),
];
