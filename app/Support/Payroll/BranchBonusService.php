<?php

namespace App\Support\Payroll;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class BranchBonusService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array{company_id:int,branch_id:int,employee_id:int,bonus_reference:string,amount_minor:int,reason_category:string,reason:string,effective_period:string} $data */
    public function create(User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($actor, $data): int {
            $branch = $this->db->table('branches')->where('id', $data['branch_id'])->where('company_id', $data['company_id'])->whereNull('deleted_at')->lockForUpdate()->first();
            abort_if($branch === null, 404);
            $leaderUser = $this->db->table('employees')->where('id', $this->integer($branch, 'leader_employee_id'))->value('user_id');
            if ($this->numericValue($leaderUser) !== $actor->id && ! $actor->can('branch.bonus.approve')) {
                abort(403);
            }
            $period = CarbonImmutable::parse($data['effective_period'])->startOfMonth();
            $member = $this->db->table('employees as e')->join('employee_branch_assignments as a', 'a.employee_id', '=', 'e.id')
                ->where('e.id', $data['employee_id'])->where('e.company_id', $data['company_id'])->where('a.company_id', $data['company_id'])
                ->where('a.branch_id', $data['branch_id'])->where('a.assignment_type', 'primary')->whereDate('a.effective_from', '<=', $period)
                ->where(static fn ($query) => $query->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $period))->exists();
            if (! $member) {
                throw ValidationException::withMessages(['employee_id' => 'Nhân viên phải có phân công vận hành hiệu lực tại chi nhánh trong kỳ bonus.']);
            }
            $id = (int) $this->db->table('branch_bonus_proposals')->insertGetId([
                'uuid' => (string) Str::uuid(), ...$data, 'effective_period' => $period->toDateString(), 'currency_code' => 'VND',
                'status' => 'draft', 'version' => 1, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->history($data['company_id'], $id, 'created', null, 'draft', 1, $actor->id, null);

            return $id;
        }, 3);
    }

    public function transition(User $actor, int $companyId, int $id, string $action, int $expectedVersion, ?string $comment): void
    {
        $this->db->transaction(function () use ($actor, $companyId, $id, $action, $expectedVersion, $comment): void {
            $bonus = $this->db->table('branch_bonus_proposals')->where('company_id', $companyId)->where('id', $id)->lockForUpdate()->first();
            abort_if($bonus === null, 404);
            if ($this->integer($bonus, 'version') !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'Bonus đã thay đổi; hãy tải lại.']);
            }
            $from = $this->text($bonus, 'status');
            $target = match ($action) {
                'submit' => 'submitted', 'approve' => 'approved', 'reject' => 'rejected', 'cancel' => 'cancelled', default => ''
            };
            $allowed = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'rejected']];
            if (! in_array($target, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Transition bonus không hợp lệ.']);
            }
            if (in_array($target, ['approved', 'rejected'], true)) {
                abort_unless($actor->can('branch.bonus.approve'), 403);
                if ($this->integer($bonus, 'created_by') === $actor->id) {
                    throw ValidationException::withMessages(['approval' => 'Maker không được duyệt bonus do chính mình tạo.']);
                }
            } else {
                abort_unless($this->integer($bonus, 'created_by') === $actor->id || $actor->can('branch.bonus.approve'), 403);
            }
            $version = $expectedVersion + 1;
            $changes = ['status' => $target, 'version' => $version, 'review_comment' => $comment, 'updated_at' => now()];
            if ($target === 'submitted') {
                $changes += ['submitted_by' => $actor->id, 'submitted_at' => now()];
            }
            if ($target === 'approved') {
                $assignmentId = $this->createSalaryAssignment($bonus, $actor->id);
                $changes += ['approved_by' => $actor->id, 'approved_at' => now(), 'salary_component_assignment_id' => $assignmentId];
            }
            if ($target === 'rejected') {
                $changes += ['rejected_by' => $actor->id, 'rejected_at' => now()];
            }
            $this->db->table('branch_bonus_proposals')->where('id', $id)->update($changes);
            $this->history($companyId, $id, $action, $from, $target, $version, $actor->id, $comment);
        }, 3);
    }

    private function createSalaryAssignment(object $bonus, int $actorId): int
    {
        $companyId = $this->integer($bonus, 'company_id');
        $effectivePeriod = $this->text($bonus, 'effective_period');
        $component = $this->db->table('salary_components')->where('company_id', $companyId)->where('code', 'BRANCH_BONUS')->first();
        if ($component === null) {
            $componentId = (int) $this->db->table('salary_components')->insertGetId(['company_id' => $companyId, 'code' => 'BRANCH_BONUS', 'name' => 'Thưởng chi nhánh', 'description' => 'Bonus maker-checker theo kỳ', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'taxable_status' => 'taxable', 'insurance_status' => 'excluded', 'affects_gross' => true, 'affects_net' => true, 'display_order' => 500, 'effective_from' => $effectivePeriod, 'status' => 'active', 'is_system' => true, 'system_key' => 'branch_bonus', 'version' => 1, 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $componentId = $this->integer($component, 'id');
        }
        $from = CarbonImmutable::parse($effectivePeriod)->startOfMonth();

        return (int) $this->db->table('employee_salary_component_assignments')->insertGetId(['company_id' => $companyId, 'employee_id' => $this->integer($bonus, 'employee_id'), 'salary_component_id' => $componentId, 'amount_minor' => $this->integer($bonus, 'amount_minor'), 'currency_code' => 'VND', 'effective_from' => $from->toDateString(), 'effective_to' => $from->endOfMonth()->toDateString(), 'source' => 'branch_bonus', 'status' => 'active', 'version' => 1, 'idempotency_key' => $this->text($bonus, 'uuid'), 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function history(int $companyId, int $id, string $action, ?string $from, string $to, int $version, int $actor, ?string $comment): void
    {
        $this->db->table('branch_bonus_histories')->insert(['company_id' => $companyId, 'branch_bonus_proposal_id' => $id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'version' => $version, 'actor_id' => $actor, 'safe_comment' => $comment, 'created_at' => now()]);
    }

    private function integer(object $row, string $key): int
    {
        return $this->numericValue($row->{$key} ?? null);
    }

    private function numericValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_string($value) ? $value : '';
    }
}
