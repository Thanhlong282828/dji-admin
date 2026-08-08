<?php

namespace Modules\Employee\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Application\DTOs\EmploymentContractData;
use Modules\Employee\Domain\Enums\ContractStatus;
use Modules\Employee\Domain\Enums\ContractType;
use Modules\Employee\Domain\Enums\EmployeeHistoryEventType;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeHistory;
use Modules\Employee\Domain\Models\EmploymentContract;

final readonly class EmploymentContractService
{
    public function __construct(private EmploymentContractStateMachine $states) {}

    public function create(int $companyId, int $employeeId, EmploymentContractData $data, int $actorId): EmploymentContract
    {
        Employee::query()->where('company_id', $companyId)->findOrFail($employeeId);
        $this->validate($data);
        $contract = EmploymentContract::query()->create(['employee_id' => $employeeId, ...$data->toArray()]);
        $this->log($employeeId, $actorId, 'Tạo hợp đồng nháp', ['contract_id' => $contract->id, 'status' => 'draft']);

        return $contract;
    }

    public function transition(int $companyId, int $contractId, ContractStatus $target, int $actorId, ?string $reason = null): EmploymentContract
    {
        return DB::transaction(function () use ($companyId, $contractId, $target, $actorId, $reason): EmploymentContract {
            $contract = EmploymentContract::query()->whereHas('employee', fn ($query) => $query->where('company_id', $companyId))->lockForUpdate()->findOrFail($contractId);
            $rawStatus = $contract->getRawOriginal('status');
            if (! is_string($rawStatus)) {
                throw ValidationException::withMessages(['status' => 'Trạng thái hợp đồng hiện tại không hợp lệ.']);
            }
            $current = ContractStatus::from($rawStatus);
            $this->states->assertCanTransition($current, $target);
            if ($target === ContractStatus::Active) {
                $overlap = EmploymentContract::query()->where('employee_id', $contract->employee_id)->whereKeyNot($contractId)->where('status', 'active')
                    ->whereDate('starts_at', '<=', $contract->ends_at ?? '9999-12-31')
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $contract->starts_at))
                    ->lockForUpdate()->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['status' => 'Nhân viên đã có hợp đồng active chồng lấn.']);
                }
            }
            if ($target === ContractStatus::Terminated && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages(['reason' => 'Cần nhập lý do chấm dứt hợp đồng.']);
            }
            $contract->update([
                'status' => $target->value,
                'termination_reason' => $target === ContractStatus::Terminated ? $reason : $contract->termination_reason,
                'terminated_at' => $target === ContractStatus::Terminated ? now() : $contract->terminated_at,
            ]);
            $this->log((int) $contract->employee_id, $actorId, 'Chuyển trạng thái hợp đồng', ['contract_id' => $contract->id, 'from' => $current->value, 'to' => $target->value]);

            return $contract->refresh();
        }, 3);
    }

    private function validate(EmploymentContractData $data): void
    {
        if ($data->endsAt !== null && $data->endsAt->isBefore($data->startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'Ngày kết thúc không được trước ngày bắt đầu.']);
        }
        if (in_array($data->type, [ContractType::Probation, ContractType::FixedTerm], true) && $data->endsAt === null) {
            throw ValidationException::withMessages(['ends_at' => 'Hợp đồng thử việc/có thời hạn bắt buộc có ngày kết thúc.']);
        }
        if ((float) $data->baseSalary < 0) {
            throw ValidationException::withMessages(['base_salary' => 'Lương cơ bản không được âm.']);
        }
        if (preg_match('/^[A-Z]{3}$/', $data->currency) !== 1) {
            throw ValidationException::withMessages(['currency' => 'Mã tiền tệ không hợp lệ.']);
        }
    }

    /** @param array<string, int|string> $after */
    private function log(int $employeeId, int $actorId, string $reason, array $after): void
    {
        EmployeeHistory::query()->create(['employee_id' => $employeeId, 'event_type' => EmployeeHistoryEventType::StatusChange, 'after' => $after, 'reason' => $reason, 'approved_by' => $actorId, 'effective_at' => CarbonImmutable::now()]);
    }
}
