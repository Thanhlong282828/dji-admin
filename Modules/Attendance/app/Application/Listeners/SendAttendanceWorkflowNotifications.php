<?php

namespace Modules\Attendance\Application\Listeners;

use App\Services\WorkforceNotificationDeliveryService;
use App\Services\WorkforceNotificationRecipientResolver;
use Modules\Attendance\Domain\Events\WorkforceAttendanceChanged;

final readonly class SendAttendanceWorkflowNotifications
{
    public function __construct(private WorkforceNotificationRecipientResolver $resolver, private WorkforceNotificationDeliveryService $delivery) {}

    public function handle(WorkforceAttendanceChanged $e): void
    {
        if ($e->employeeId <= 0) {
            return;
        } $outcome = str_contains($e->action, 'approved') || str_contains($e->action, 'rejected');
        $users = $outcome ? $this->resolver->employee($e->companyId, $e->employeeId) : $this->resolver->approvers($e->companyId, $e->employeeId, 'attendance.approve');
        foreach ($users as $u) {
            $this->delivery->deliver($u, 'attendance', ['company_id' => $e->companyId, 'entity_type' => 'attendance', 'entity_id' => $e->eventId, 'employee_id' => $e->employeeId, 'action' => $e->action, 'safe_title' => 'Cập nhật chấm công', 'safe_message' => 'Trạng thái chấm công đã thay đổi.', 'version' => 1, 'timestamp' => now()->toIso8601String(), 'url' => null]);
        }
    }
}
