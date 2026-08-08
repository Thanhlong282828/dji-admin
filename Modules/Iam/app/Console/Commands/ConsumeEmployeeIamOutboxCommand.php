<?php

namespace Modules\Iam\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Iam\Application\Commands\CreateAccessReviewCampaignCommand;
use Modules\Iam\Application\Commands\RevokeAccessAssignmentCommand;
use Modules\Iam\Domain\Services\IamValue;

final class ConsumeEmployeeIamOutboxCommand extends Command
{
    protected $signature = 'iam:consume-employee-lifecycle {--limit=100}';

    protected $description = 'Consume Employee termination and transfer IAM intents';

    public function handle(CommandBusInterface $commands): int
    {
        $ids = DB::table('employee_lifecycle_outbox')->whereNull('iam_consumed_at')->whereIn('event_type', ['employee.terminated', 'employee.transferred'])->orderBy('id')->limit(max(1, (int) $this->option('limit')))->pluck('id');
        foreach ($ids as $rawId) {
            $id = IamValue::int($rawId);
            DB::transaction(function () use ($id, $commands): void {
                $row = DB::table('employee_lifecycle_outbox')->where('id', $id)->whereNull('iam_consumed_at')->lockForUpdate()->first();
                if (! $row instanceof \stdClass) {
                    return;
                }
                $companyId = IamValue::int($row->company_id);
                $employeeId = IamValue::int($row->employee_id);
                $user = DB::table('employees')->where(['id' => $employeeId, 'company_id' => $companyId])->value('user_id');
                if (! is_numeric($user)) {
                    DB::table('employee_lifecycle_outbox')->where('id', $id)->update(['iam_consumed_at' => now()]);

                    return;
                }
                $userId = (int) $user;
                $actor = DB::table('employee_lifecycle_events')->where(['employee_id' => $employeeId, 'event_type' => IamValue::string($row->event_type)])->latest('id')->value('created_by');
                $actorId = is_numeric($actor) ? (int) $actor : $userId;
                if (IamValue::string($row->event_type) === 'employee.terminated') {
                    foreach (DB::table('iam_access_assignments')->where(['company_id' => $companyId, 'user_id' => $userId, 'status' => 'active'])->pluck('id') as $assignment) {
                        $commands->dispatch(new RevokeAccessAssignmentCommand($companyId, IamValue::int($assignment), $actorId));
                    }
                } else {
                    $commands->dispatch(new CreateAccessReviewCampaignCommand($companyId, 'Transfer access review · employee '.$employeeId, 'user', $userId, now()->addDays(7)->toDateString(), $actorId, IamValue::string($row->event_id)));
                }
                DB::table('employee_lifecycle_outbox')->where('id', $id)->update(['iam_consumed_at' => now()]);
            }, 3);
        }

        return self::SUCCESS;
    }
}
