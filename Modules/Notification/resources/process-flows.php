<?php

$definition = static fn (string $current, string $category): array => [
    'id' => 'notification.delivery-lifecycle', 'category' => $category, 'current' => $current,
    'nodes' => array_map(static fn (string $key): array => ['key' => $key], ['notification-templates', 'notifications', 'notification-deliveries', 'integrations']),
];

return [
    'notification-templates' => $definition('notification-templates', 'CONFIGURATION'),
    'notifications' => $definition('notifications', 'READ_MODEL'),
    'notification-deliveries' => $definition('notification-deliveries', 'READ_MODEL'),
];
