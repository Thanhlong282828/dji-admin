<?php

namespace Modules\Leave\Application\Listeners;

use App\Services\WorkforceNotificationDeliveryService;
use App\Services\WorkforceNotificationRecipientResolver;
use Modules\Leave\Domain\Events\WorkforceLeaveChanged;

final readonly class SendLeaveWorkflowNotifications
{
    public function __construct(private WorkforceNotificationRecipientResolver $resolver, private WorkforceNotificationDeliveryService $delivery) {}

    public function handle(WorkforceLeaveChanged $e): void
    {
        $outcome = in_array($e->status, ['approved', 'rejected'], true);
        $users = $outcome ? $this->resolver->employee($e->companyId, $e->employeeId) : $this->resolver->approvers($e->companyId, $e->employeeId, 'leave.approve');
        foreach ($users as $u) {
            $this->delivery->deliver($u, 'leave', ['company_id' => $e->companyId, 'entity_type' => 'leave_request', 'entity_id' => $e->requestId, 'employee_id' => $e->employeeId, 'action' => $e->action, 'safe_title' => 'Cập nhật nghỉ phép', 'safe_message' => 'Trạng thái yêu cầu nghỉ phép đã thay đổi.', 'version' => $e->version, 'timestamp' => now()->toIso8601String(), 'url' => null]);
        }
    }
}
