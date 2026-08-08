<?php

namespace Modules\Payroll\Application\Listeners;

use App\Models\User;
use App\Services\WorkforceNotificationDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Modules\Payroll\Domain\Events\PayslipChanged;

final readonly class SendPayslipPublishedNotification implements ShouldQueue
{
    public function __construct(private ConnectionInterface $db, private WorkforceNotificationDeliveryService $delivery) {}

    public function handle(PayslipChanged $event): void
    {
        if ($event->action !== 'published') {
            return;
        }
        $userId = $this->db->table('employees')->where('company_id', $event->companyId)->where('id', $event->employeeId)->value('user_id');
        if (! is_numeric($userId)) {
            return;
        }
        $recipient = User::query()->find((int) $userId);
        if (! $recipient instanceof User) {
            return;
        }
        $this->delivery->deliver($recipient, 'payroll_payslip', [
            'company_id' => $event->companyId,
            'entity_type' => 'payroll_payslip',
            'entity_id' => $event->payslipId,
            'employee_id' => $event->employeeId,
            'action' => 'published',
            'safe_title' => 'Phiếu lương đã sẵn sàng',
            'safe_message' => 'Phiếu lương chính thức của bạn đã được phát hành.',
            'version' => 1,
            'timestamp' => now()->toIso8601String(),
            'url' => null,
        ]);
    }
}
