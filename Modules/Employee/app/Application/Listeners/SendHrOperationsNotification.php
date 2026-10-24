<?php

namespace Modules\Employee\Application\Listeners;

use App\Services\WorkforceNotificationDeliveryService;
use App\Services\WorkforceNotificationRecipientResolver;
use Modules\Employee\Domain\Events\HrOperationsChanged;

final readonly class SendHrOperationsNotification
{
    public function __construct(private WorkforceNotificationRecipientResolver $recipients, private WorkforceNotificationDeliveryService $delivery) {}

    public function handle(HrOperationsChanged $event): void
    {
        $permission = $event->entityType === 'accounting_adjustment' ? 'accounting.adjustment.approve' : 'employment-contract.approve';
        $recipients = $this->recipients->approvers($event->companyId, $event->employeeId, $permission)
            ->merge($this->recipients->employee($event->companyId, $event->employeeId))->unique('id');
        $payload = [
            'company_id' => $event->companyId, 'entity_type' => $event->entityType, 'entity_id' => $event->entityId,
            'employee_id' => $event->employeeId > 0 ? $event->employeeId : null, 'action' => $event->action,
            'safe_title' => 'Cập nhật '.str_replace('_', ' ', $event->entityType),
            'safe_message' => 'Một nghiệp vụ cần được theo dõi trong hệ thống.', 'version' => $event->version,
            'timestamp' => now()->toIso8601String(), 'url' => '/admin/hr/operations?company_id='.$event->companyId,
        ];
        foreach ($recipients as $recipient) {
            $this->delivery->deliver($recipient, 'hr_operations', $payload);
        }
    }
}
