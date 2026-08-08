<?php

namespace Modules\Payroll\Application\Listeners;

use App\Models\User;
use App\Services\WorkforceNotificationDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Modules\Payroll\Domain\Events\PayrollRunChanged;
use Spatie\Permission\Models\Permission;

final readonly class SendPayrollRunNotifications implements ShouldQueue
{
    public function __construct(private ConnectionInterface $db, private WorkforceNotificationDeliveryService $delivery) {}

    public function handle(PayrollRunChanged $event): void
    {
        foreach ($this->recipients($event) as $recipient) {
            $this->delivery->deliver($recipient, 'payroll_run', [
                'company_id' => $event->companyId,
                'entity_type' => 'payroll_run',
                'entity_id' => $event->runId,
                'employee_id' => null,
                'action' => $event->action,
                'safe_title' => 'Cập nhật kỳ tính lương',
                'safe_message' => 'Trạng thái kỳ tính lương đã thay đổi.',
                'version' => $event->version,
                'timestamp' => now()->toIso8601String(),
                'url' => route('admin.payroll.runs.show', ['run' => $event->runId, 'company_id' => $event->companyId]),
            ]);
        }
    }

    /** @return Collection<int, User> */
    private function recipients(PayrollRunChanged $event): Collection
    {
        $allowed = $this->positiveIds($this->db->table('company_user_access')->where('company_id', $event->companyId)->pluck('user_id'));
        if ($event->status === 'submitted') {
            if (! Permission::query()->where('name', 'payroll.run.approve')->where('guard_name', 'web')->exists()) {
                return User::query()->whereRaw('1 = 0')->get();
            }

            return User::permission('payroll.run.approve')->whereIn('id', $allowed)->get();
        }
        $actorIds = $this->positiveIds($this->db->table('payroll_runs')->where('company_id', $event->companyId)->where('id', $event->runId)->pluck('created_by'));
        foreach (['submitted_by', 'approved_by', 'rejected_by'] as $column) {
            $actorIds = $actorIds->merge($this->positiveIds($this->db->table('payroll_runs')->where('company_id', $event->companyId)->where('id', $event->runId)->pluck($column)));
        }

        return User::query()->whereIn('id', $actorIds->intersect($allowed)->unique()->values())->get();
    }

    /**
     * @param  Collection<int|string, mixed>  $values
     * @return Collection<int, int<1, max>>
     */
    private function positiveIds(Collection $values): Collection
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_int($value) && $value > 0) {
                $ids[] = $value;
            } elseif (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        return collect($ids);
    }
}
