<?php

namespace Modules\Attendance\Application\Listeners;

use App\Services\WorkforceNotificationDeliveryService;
use App\Services\WorkforceNotificationRecipientResolver;
use Modules\Attendance\Domain\Events\WorkforceOvertimeChanged;

final readonly class SendOvertimeWorkflowNotifications
{
    public function __construct(private WorkforceNotificationRecipientResolver $resolver, private WorkforceNotificationDeliveryService $delivery) {}

    public function handle(WorkforceOvertimeChanged $e): void
    {
        if (str_contains($e->action, 'reconciled')) {
            return;
        } $outcome = in_array($e->status, ['approved', 'rejected'], true);
        $users = $outcome ? $this->resolver->employee($e->companyId, $e->employeeId) : $this->resolver->approvers($e->companyId, $e->employeeId, 'overtime.approve');
        foreach ($users as $u) {
            $this->delivery->deliver($u, 'overtime', ['company_id' => $e->companyId, 'entity_type' => 'overtime_request', 'entity_id' => $e->requestId, 'employee_id' => $e->employeeId, 'action' => $e->action, 'safe_title' => 'Cập nhật overtime', 'safe_message' => 'Trạng thái yêu cầu overtime đã thay đổi.', 'version' => $e->version, 'timestamp' => now()->toIso8601String(), 'url' => null]);
        }
    }
}
