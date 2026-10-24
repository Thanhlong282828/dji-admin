<?php

namespace Modules\Employee\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Application\DTOs\EmploymentContractData;
use Modules\Employee\Domain\Enums\ContractStatus;
use Modules\Employee\Domain\Enums\ContractType;
use Modules\Employee\Domain\Events\HrOperationsChanged;
use Modules\Employee\Domain\Services\EmployeeAccountService;
use Modules\Employee\Domain\Services\EmploymentContractService;

final readonly class HrOperationsService
{
    public function __construct(private ConnectionInterface $db, private EmploymentContractService $contracts, private EmployeeAccountService $accounts) {}

    /** @return array<string,mixed> */
    public function read(int $companyId): array
    {
        $contracts = $this->db->table('employment_contracts as c')->join('employees as e', 'e.id', '=', 'c.employee_id')->where('e.company_id', $companyId);

        return [
            'employees' => $this->db->table('employees')->where('company_id', $companyId)->whereIn('employment_status', ['active', 'probation'])->whereNull('deleted_at')->orderBy('employee_code')->get(['id', 'employee_code', 'full_name']),
            'contracts' => (clone $contracts)->select(['c.*', 'e.full_name', 'e.employee_code'])->orderByDesc('c.id')->limit(100)->get(),
            'expiring_30' => (clone $contracts)->where('c.status', 'active')->whereBetween('c.ends_at', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
            'renewals' => $this->db->table('employment_contract_renewals as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->where('r.company_id', $companyId)->select(['r.*', 'e.full_name', 'e.employee_code'])->orderByDesc('r.id')->limit(100)->get(),
            'resignations' => $this->db->table('employee_resignations as r')->join('employees as e', 'e.id', '=', 'r.employee_id')->where('r.company_id', $companyId)->select(['r.*', 'e.full_name', 'e.employee_code'])->orderByDesc('r.id')->limit(100)->get(),
            'offboardings' => $this->db->table('employee_offboarding_cases as c')->join('employees as e', 'e.id', '=', 'c.employee_id')->where('c.company_id', $companyId)->select(['c.*', 'e.full_name', 'e.employee_code'])->orderByDesc('c.id')->limit(100)->get(),
            'offboarding_items' => $this->db->table('employee_offboarding_items as i')->join('employee_offboarding_cases as c', 'c.id', '=', 'i.offboarding_case_id')->where('c.company_id', $companyId)->orderBy('i.offboarding_case_id')->orderBy('i.id')->get(['i.*']),
        ];
    }

    /** @param array<string,mixed> $data */
    public function mutate(string $action, int $companyId, User $actor, array $data): int
    {
        return match ($action) {
            'renewal.create' => $this->renewalCreate($companyId, $actor, $data),
            'renewal.submit' => $this->simpleTransition('employment_contract_renewals', $companyId, $actor, $data, 'draft', 'submitted', 'renewal'),
            'renewal.approve' => $this->renewalApprove($companyId, $actor, $data),
            'renewal.reject' => $this->simpleTransition('employment_contract_renewals', $companyId, $actor, $data, 'submitted', 'rejected', 'renewal'),
            'resignation.create' => $this->resignationCreate($companyId, $actor, $data),
            'resignation.submit' => $this->simpleTransition('employee_resignations', $companyId, $actor, $data, 'draft', 'submitted', 'resignation'),
            'resignation.approve' => $this->resignationApprove($companyId, $actor, $data),
            'resignation.reject' => $this->simpleTransition('employee_resignations', $companyId, $actor, $data, 'submitted', 'rejected', 'resignation'),
            'offboarding.item' => $this->completeItem($companyId, $actor, $data),
            'offboarding.complete' => $this->completeOffboarding($companyId, $actor, $data),
            default => throw ValidationException::withMessages(['action' => 'HR_OPERATION_UNSUPPORTED']),
        };
    }

    /** @param array<string,mixed> $data */
    private function renewalCreate(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $previous = $this->previousContract($companyId, $this->int($data, 'previous_contract_id'));
            $effective = CarbonImmutable::parse($this->text($data, 'effective_date'));
            $previousEnd = $this->nullableField($previous, 'ends_at');
            if ($previousEnd === null || ! $effective->equalTo(CarbonImmutable::parse($previousEnd)->addDay())) {
                throw ValidationException::withMessages(['effective_date' => 'Ngày gia hạn phải liền sau ngày kết thúc hợp đồng cũ.']);
            }

            return (int) $this->db->table('employment_contract_renewals')->insertGetId([
                'company_id' => $companyId, 'employee_id' => $this->intField($previous, 'employee_id'), 'previous_contract_id' => $this->intField($previous, 'id'),
                'reference' => $this->text($data, 'reference'), 'effective_date' => $effective->toDateString(), 'new_ends_at' => $this->nullableText($data, 'new_ends_at'),
                'new_contract_type' => $this->text($data, 'new_contract_type'), 'new_base_salary' => $this->text($data, 'new_base_salary'), 'currency' => 'VND',
                'reason' => $this->text($data, 'reason'), 'status' => 'draft', 'requested_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function renewalApprove(int $companyId, User $actor, array $data): int
    {
        $event = $this->db->transaction(function () use ($companyId, $actor, $data): array {
            $row = $this->row('employment_contract_renewals', $companyId, $this->int($data, 'id'));
            $this->makerChecker($row, $actor, 'submitted');
            if ($this->nullableIntField($row, 'new_contract_id') !== null) {
                return [$this->intField($row, 'employee_id'), $this->intField($row, 'id')];
            }
            $newEnd = $this->nullableField($row, 'new_ends_at');
            $contract = $this->contracts->create($companyId, $this->intField($row, 'employee_id'), new EmploymentContractData(
                $this->textField($row, 'reference').'-NEW', ContractType::from($this->textField($row, 'new_contract_type')), CarbonImmutable::parse($this->textField($row, 'effective_date')), $newEnd !== null ? CarbonImmutable::parse($newEnd) : null,
                $this->textField($row, 'new_base_salary'), $this->textField($row, 'currency')
            ), $actor->id);
            $this->contracts->transition($companyId, $contract->id, ContractStatus::Approval, $this->intField($row, 'requested_by'));
            $this->contracts->transition($companyId, $contract->id, ContractStatus::Approved, $actor->id);
            $this->db->table('employment_contract_renewals')->where('id', $this->intField($row, 'id'))->update(['status' => 'approved', 'new_contract_id' => $contract->id, 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

            return [$this->intField($row, 'employee_id'), $this->intField($row, 'id')];
        }, 3);
        event(new HrOperationsChanged($companyId, $event[0], 'contract_renewal', $event[1], 'approved', 1, $actor->id));

        return $event[1];
    }

    /** @param array<string,mixed> $data */
    private function resignationCreate(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $employeeId = $this->int($data, 'employee_id');
            $this->db->table('employees')->where(['id' => $employeeId, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
            if ($this->db->table('employee_resignations')->where(['company_id' => $companyId, 'employee_id' => $employeeId])->whereIn('status', ['draft', 'submitted', 'approved'])->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'Nhân viên đã có resignation đang mở.']);
            }

            return (int) $this->db->table('employee_resignations')->insertGetId(['company_id' => $companyId, 'employee_id' => $employeeId, 'reference' => $this->text($data, 'reference'), 'requested_last_working_date' => $this->text($data, 'last_working_date'), 'category' => 'voluntary', 'reason' => $this->text($data, 'reason'), 'status' => 'draft', 'requested_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function resignationApprove(int $companyId, User $actor, array $data): int
    {
        $event = $this->db->transaction(function () use ($companyId, $actor, $data): array {
            $row = $this->row('employee_resignations', $companyId, $this->int($data, 'id'));
            $this->makerChecker($row, $actor, 'submitted');
            $date = $this->nullableText($data, 'approved_last_working_date') ?? $this->textField($row, 'requested_last_working_date');
            $this->db->table('employee_resignations')->where('id', $this->intField($row, 'id'))->update(['status' => 'approved', 'approved_last_working_date' => $date, 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);
            $caseId = (int) $this->db->table('employee_offboarding_cases')->insertGetId(['company_id' => $companyId, 'employee_id' => $this->intField($row, 'employee_id'), 'resignation_id' => $this->intField($row, 'id'), 'reference' => $this->textField($row, 'reference').'-OFF', 'effective_date' => $date, 'status' => 'in_progress', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([['hr', 'employment_evidence', 'Hồ sơ nghỉ việc và hợp đồng'], ['payroll', 'final_payroll', 'Xác nhận sẵn sàng lương cuối'], ['iam', 'access_review', 'Rà soát ngày vô hiệu hóa truy cập'], ['asset', 'asset_return', 'Hoàn trả tài sản được giao']] as [$category,$key,$label]) {
                $this->db->table('employee_offboarding_items')->insert(['offboarding_case_id' => $caseId, 'category' => $category, 'item_key' => $key, 'label' => $label, 'mandatory' => true, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            }

            return [$this->intField($row, 'employee_id'), $this->intField($row, 'id')];
        }, 3);
        event(new HrOperationsChanged($companyId, $event[0], 'resignation', $event[1], 'approved', 1, $actor->id));

        return $event[1];
    }

    /** @param array<string,mixed> $data */
    private function completeItem(int $companyId, User $actor, array $data): int
    {
        return $this->db->transaction(function () use ($companyId, $actor, $data): int {
            $item = $this->offboardingItem($companyId, $this->int($data, 'id'));
            if ($this->textField($item, 'status') === 'completed') {
                return $this->intField($item, 'id');
            } $this->db->table('employee_offboarding_items')->where('id', $this->intField($item, 'id'))->update(['status' => 'completed', 'completed_by' => $actor->id, 'completed_at' => now(), 'evidence_reference' => $this->nullableText($data, 'evidence_reference'), 'updated_at' => now()]);

            return $this->intField($item, 'id');
        }, 3);
    }

    /** @param array<string,mixed> $data */
    private function completeOffboarding(int $companyId, User $actor, array $data): int
    {
        $result = $this->db->transaction(function () use ($companyId, $actor, $data): array {
            $case = $this->row('employee_offboarding_cases', $companyId, $this->int($data, 'id'));
            if ($this->textField($case, 'status') === 'completed') {
                return [$this->intField($case, 'employee_id'), $this->intField($case, 'id')];
            } if (now()->startOfDay()->lt(CarbonImmutable::parse($this->textField($case, 'effective_date')))) {
                throw ValidationException::withMessages(['effective_date' => 'Chưa đến ngày offboarding hiệu lực.']);
            } if ($this->db->table('employee_offboarding_items')->where('offboarding_case_id', $this->intField($case, 'id'))->where('mandatory', true)->where('status', '!=', 'completed')->exists()) {
                throw ValidationException::withMessages(['checklist' => 'Còn checklist bắt buộc chưa hoàn tất.']);
            } $end = CarbonImmutable::parse($this->textField($case, 'effective_date'))->subDay()->toDateString();
            $employeeId = $this->intField($case, 'employee_id');
            $this->db->table('employee_assignments')->where('employee_id', $employeeId)->where('assignment_type', 'primary')->whereNull('effective_to')->update(['effective_to' => $end, 'updated_at' => now()]);
            $this->db->table('employee_branch_assignments')->where('employee_id', $employeeId)->where('assignment_type', 'primary')->whereNull('effective_to')->update(['effective_to' => $end, 'updated_at' => now()]);
            $employee = $this->employee($companyId, $employeeId);
            $this->db->table('employees')->where('id', $employeeId)->update(['employment_status' => 'terminated', 'updated_at' => now()]);
            if ($this->nullableIntField($employee, 'user_id') !== null) {
                $this->accounts->setAccess($companyId, $employeeId, false, $actor->id);
            } $this->db->table('employee_offboarding_cases')->where('id', $this->intField($case, 'id'))->update(['status' => 'completed', 'completed_by' => $actor->id, 'completed_at' => now(), 'updated_at' => now()]);
            $resignationId = $this->nullableIntField($case, 'resignation_id');
            if ($resignationId !== null) {
                $this->db->table('employee_resignations')->where('id', $resignationId)->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
            }

            return [$employeeId, $this->intField($case, 'id')];
        }, 3);
        event(new HrOperationsChanged($companyId, $result[0], 'offboarding', $result[1], 'completed', 1, $actor->id));

        return $result[1];
    }

    /** @param array<string,mixed> $data */
    private function simpleTransition(string $table, int $companyId, User $actor, array $data, string $from, string $to, string $entity): int
    {
        $result = $this->db->transaction(function () use ($table, $companyId, $actor, $data, $from, $to): array {
            $row = $this->row($table, $companyId, $this->int($data, 'id'));
            if ($this->textField($row, 'status') === $to) {
                return [$this->intField($row, 'employee_id'), $this->intField($row, 'id')];
            } if ($this->textField($row, 'status') !== $from) {
                throw ValidationException::withMessages(['status' => 'HR_OPERATION_TRANSITION_INVALID']);
            } $updates = ['status' => $to, 'updated_at' => now()];
            if ($to === 'submitted') {
                $updates['submitted_at'] = now();
            } if ($to === 'rejected') {
                $this->makerChecker($row, $actor, 'submitted');
                $updates['approved_by'] = $actor->id;
                $updates['approved_at'] = now();
            }$this->db->table($table)->where('id', $this->intField($row, 'id'))->update($updates);

            return [$this->intField($row, 'employee_id'), $this->intField($row, 'id')];
        }, 3);
        event(new HrOperationsChanged($companyId, $result[0], $entity, $result[1], $to, 1, $actor->id));

        return $result[1];
    }

    private function row(string $table, int $companyId, int $id): object
    {
        return $this->db->table($table)->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->firstOrFail();
    }

    private function previousContract(int $companyId, int $id): object
    {
        return $this->db->table('employment_contracts as c')->join('employees as e', 'e.id', '=', 'c.employee_id')->where('e.company_id', $companyId)->where('c.id', $id)->lockForUpdate()->select(['c.id', 'c.employee_id', 'c.ends_at'])->firstOrFail();
    }

    private function offboardingItem(int $companyId, int $id): object
    {
        return $this->db->table('employee_offboarding_items as i')->join('employee_offboarding_cases as c', 'c.id', '=', 'i.offboarding_case_id')->where('c.company_id', $companyId)->where('i.id', $id)->lockForUpdate()->select(['i.id', 'i.status'])->firstOrFail();
    }

    private function employee(int $companyId, int $id): object
    {
        return $this->db->table('employees')->where(['id' => $id, 'company_id' => $companyId])->select('user_id')->firstOrFail();
    }

    private function makerChecker(object $row, User $actor, string $status): void
    {
        if ($this->textField($row, 'status') !== $status) {
            throw ValidationException::withMessages(['status' => 'HR_OPERATION_TRANSITION_INVALID']);
        }if ($this->intField($row, 'requested_by') === $actor->id) {
            throw ValidationException::withMessages(['approval' => 'MAKER_CHECKER_REQUIRED']);
        }
    }

    /** @param array<string,mixed> $data */
    private function int(array $data, string $key): int
    {
        $v = $data[$key] ?? null;

        return is_int($v) ? $v : (is_string($v) && ctype_digit($v) ? (int) $v : throw ValidationException::withMessages([$key => 'Giá trị không hợp lệ.']));
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $v = $data[$key] ?? null;

        return is_string($v) && trim($v) !== '' ? trim($v) : throw ValidationException::withMessages([$key => 'Giá trị bắt buộc.']);
    }

    /** @param array<string,mixed> $data */
    private function nullableText(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;

        return $v === null || $v === '' ? null : (is_string($v) ? trim($v) : throw ValidationException::withMessages([$key => 'Giá trị không hợp lệ.']));
    }

    private function intField(object $row, string $key): int
    {
        $value = data_get($row, $key);

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : throw ValidationException::withMessages([$key => 'Giá trị dữ liệu không hợp lệ.']));
    }

    private function nullableIntField(object $row, string $key): ?int
    {
        $value = data_get($row, $key);

        return $value === null ? null : (is_int($value) ? $value : (is_numeric($value) ? (int) $value : throw ValidationException::withMessages([$key => 'Giá trị dữ liệu không hợp lệ.'])));
    }

    private function textField(object $row, string $key): string
    {
        $value = data_get($row, $key);

        return is_string($value) && $value !== '' ? $value : throw ValidationException::withMessages([$key => 'Giá trị dữ liệu không hợp lệ.']);
    }

    private function nullableField(object $row, string $key): ?string
    {
        $value = data_get($row, $key);

        return $value === null ? null : (is_string($value) ? $value : throw ValidationException::withMessages([$key => 'Giá trị dữ liệu không hợp lệ.']));
    }
}
