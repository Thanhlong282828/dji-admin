<?php

return [
    'schema_version' => 1,
    'cache_ttl_seconds' => 30,
    'flags' => [
        'operations.observability' => ['enabled' => (bool) env('FEATURE_OPERATIONS_OBSERVABILITY', true), 'owner' => 'operations', 'risk' => 'low', 'failure' => 'open', 'remove_when' => 'observability becomes mandatory'],
        'operations.release_dashboard' => ['enabled' => (bool) env('FEATURE_RELEASE_DASHBOARD', true), 'owner' => 'operations', 'risk' => 'low', 'failure' => 'open', 'remove_when' => 'release dashboard becomes mandatory'],
        'integration.outbound_delivery' => ['enabled' => (bool) env('FEATURE_INTEGRATION_OUTBOUND_DELIVERY', false), 'owner' => 'integration', 'risk' => 'high', 'failure' => 'closed', 'remove_when' => 'provider delivery is permanently governed elsewhere'],
        'notification.email' => ['enabled' => (bool) env('FEATURE_NOTIFICATION_EMAIL', false), 'owner' => 'notification', 'risk' => 'high', 'failure' => 'closed', 'remove_when' => 'email delivery has a dedicated provider control plane'],
        'notification.broadcast' => ['enabled' => (bool) env('FEATURE_NOTIFICATION_BROADCAST', true), 'owner' => 'notification', 'risk' => 'medium', 'failure' => 'closed', 'remove_when' => 'broadcast is mandatory'],
        'realtime.reverb' => ['enabled' => (bool) env('FEATURE_REALTIME_REVERB', true), 'owner' => 'realtime', 'risk' => 'medium', 'failure' => 'closed', 'remove_when' => 'realtime is mandatory'],
        'audit.export' => ['enabled' => (bool) env('FEATURE_AUDIT_EXPORT', true), 'owner' => 'audit', 'risk' => 'high', 'failure' => 'closed', 'remove_when' => 'export policy moves to a dedicated governance service'],
        'iam.privileged_access' => ['enabled' => (bool) env('FEATURE_IAM_PRIVILEGED_ACCESS', true), 'owner' => 'iam', 'risk' => 'high', 'failure' => 'closed', 'remove_when' => 'privileged access is mandatory'],
    ],
];
