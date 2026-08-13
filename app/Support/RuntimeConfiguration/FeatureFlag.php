<?php

namespace App\Support\RuntimeConfiguration;

enum FeatureFlag: string
{
    case OperationsObservability = 'operations.observability';
    case OperationsReleaseDashboard = 'operations.release_dashboard';
    case IntegrationOutboundDelivery = 'integration.outbound_delivery';
    case NotificationEmail = 'notification.email';
    case NotificationBroadcast = 'notification.broadcast';
    case RealtimeReverb = 'realtime.reverb';
    case AuditExport = 'audit.export';
    case IamPrivilegedAccess = 'iam.privileged_access';
    case DataLifecycleDryRun = 'data_lifecycle.dry_run';
    case DataDisposal = 'data_lifecycle.disposal';
}
