<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Application\DTOs\EmployeeAssignmentData;
use Modules\Employee\Application\DTOs\LifecycleTransitionData;
use Modules\Employee\Domain\Enums\AssignmentType;
use Modules\Employee\Domain\Enums\EmployeeLifecycleState;
use Modules\Employee\Domain\Events\EmployeeDemoted;
use Modules\Employee\Domain\Events\EmployeeHired;
use Modules\Employee\Domain\Events\EmployeeOnboarded;
use Modules\Employee\Domain\Events\EmployeePromoted;
use Modules\Employee\Domain\Events\EmployeeTerminated;
use Modules\Employee\Domain\Events\EmployeeTransferred;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Models\EmployeeManagerHistory;
use Modules\Employee\Domain\Models\EmployeePositionHistory;
use Modules\Employee\Domain\Models\EmploymentTransition;

final readonly class EmployeeLifecycleService
{
    public function __construct(private EmployeeLifecycleStateMachine $stateMachine, private EmployeeAssignmentService $assignments, private Dispatcher $events) {}

    public function create(LifecycleTransitionData $data): EmployeeLifecycleEvent
    {
        return DB::transaction(function () use ($data): EmployeeLifecycleEvent {
            $latest = EmployeeLifecycleEvent::query()
                ->where('company_id', $data->companyId)
                ->where('employee_id', $data->employeeId)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($latest !== null) {
                $existing = EmployeeLifecycleEvent::query()
                    ->where('company_id', $data->companyId)
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
                if ($data->organizationUnitId !== null && $data->positionId !== null) {
                    $this->initializeAssignment($data);

                    return $latest;
                }

                throw ValidationException::withMessages(['state' => 'Employee Lifecycle đã được khởi tạo.']);
            }
            $event = $this->transition($data, EmployeeLifecycleState::OfferCreated, EmployeeLifecycleState::Hired, 'employee.hired');
            if ($data->organizationUnitId !== null && $data->positionId !== null) {
                $this->initializeAssignment($data);
            }

            return $event;
        }, 3);
    }

    public function startOnboarding(LifecycleTransitionData $data): EmployeeLifecycleEvent
    {
        return $this->transition($data, EmployeeLifecycleState::Hired, EmployeeLifecycleState::Onboarding, 'employee.onboarding_started');
    }

    public function completeOnboarding(LifecycleTransitionData $data): EmployeeLifecycleEvent
    {
        return $this->transition($data, EmployeeLifecycleState::Onboarding, EmployeeLifecycleState::Active, 'employee.onboarded');
    }

    public function terminate(LifecycleTransitionData $data): EmployeeLifecycleEvent
    {
        $current = $this->currentState($data->companyId, $data->employeeId);

        return $this->transition($data, $current, EmployeeLifecycleState::Terminated, 'employee.terminated');
    }

    public function changeAssignment(LifecycleTransitionData $data): EmployeeLifecycleEvent
    {
        return DB::transaction(function () use ($data): EmployeeLifecycleEvent {
            $employee = Employee::query()->where('company_id', $data->companyId)->lockForUpdate()->find($data->employeeId);
            if ($employee === null) {
                throw (new ModelNotFoundException)->setModel(Employee::class, [$data->employeeId]);
            }
            $existing = EmployeeLifecycleEvent::query()->where('company_id', $data->companyId)->where('idempotency_key', $data->idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            $state = $this->currentState($data->companyId, $data->employeeId);
            $this->stateMachine->assertCanChangeAssignment($state);
            if ($data->currentAssignmentId === null || $data->organizationUnitId === null || $data->positionId === null) {
                throw ValidationException::withMessages(['assignment' => 'Thiếu dữ liệu phân công.']);
            }
            $assignmentData = new EmployeeAssignmentData(
                $data->organizationUnitId, $data->positionId, $data->managerId,
                AssignmentType::Primary, '100.00', null, $data->effectiveAt, null,
            );
            $assignment = $this->assignments->transfer($data->companyId, $data->employeeId, $data->currentAssignmentId, $assignmentData, $data->reason ?? '', $data->actorId, $data->promotion, $data->demotion);
            $payload = ['assignment_id' => (int) $assignment->id, 'position_id' => $data->positionId, 'organization_unit_id' => $data->organizationUnitId, 'manager_id' => $data->managerId];
            $eventType = $data->promotion ? 'employee.promoted' : ($data->demotion ? 'employee.demoted' : 'employee.transferred');
            $event = $this->append($data, $state, $state, $eventType, $payload);
            EmployeePositionHistory::query()->create(['company_id' => $data->companyId, 'employee_id' => $data->employeeId, 'position_id' => $data->positionId, 'organization_unit_id' => $data->organizationUnitId, 'effective_from' => $data->effectiveAt->toDateString(), 'checksum' => $this->checksum($payload)]);
            EmployeeManagerHistory::query()->create(['company_id' => $data->companyId, 'employee_id' => $data->employeeId, 'manager_id' => $data->managerId, 'effective_from' => $data->effectiveAt->toDateString(), 'checksum' => $this->checksum(['manager_id' => $data->managerId, 'effective_from' => $data->effectiveAt->toDateString()])]);
            $domainEvent = match (true) {
                $data->promotion => new EmployeePromoted($data->companyId, $data->employeeId, (int) $event->id),
                $data->demotion => new EmployeeDemoted($data->companyId, $data->employeeId, (int) $event->id),
                default => new EmployeeTransferred($data->companyId, $data->employeeId, (int) $event->id),
            };
            $this->events->dispatch($domainEvent);

            return $event;
        }, 3);
    }

    private function transition(LifecycleTransitionData $data, EmployeeLifecycleState $expected, EmployeeLifecycleState $to, string $type): EmployeeLifecycleEvent
    {
        return DB::transaction(function () use ($data, $expected, $to, $type): EmployeeLifecycleEvent {
            $employee = Employee::query()->where('company_id', $data->companyId)->lockForUpdate()->find($data->employeeId);
            if ($employee === null) {
                throw (new ModelNotFoundException)->setModel(Employee::class, [$data->employeeId]);
            }
            $existing = EmployeeLifecycleEvent::query()->where('company_id', $data->companyId)->where('idempotency_key', $data->idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            $latest = EmployeeLifecycleEvent::query()->where('company_id', $data->companyId)->where('employee_id', $data->employeeId)->latest('id')->lockForUpdate()->first();
            $current = $latest === null ? $expected : $latest->to_state;
            if (! $current instanceof EmployeeLifecycleState) {
                $current = EmployeeLifecycleState::from((string) $current);
            }
            $this->stateMachine->assertCanTransition($current, $to);
            $event = $this->append($data, $current, $to, $type, []);
            $domainEvent = match ($to) {
                EmployeeLifecycleState::Hired => new EmployeeHired($data->companyId, $data->employeeId, (int) $event->id),
                EmployeeLifecycleState::Active => new EmployeeOnboarded($data->companyId, $data->employeeId, (int) $event->id),
                EmployeeLifecycleState::Terminated => new EmployeeTerminated($data->companyId, $data->employeeId, (int) $event->id),
                default => null,
            };
            if ($domainEvent !== null) {
                $this->events->dispatch($domainEvent);
            }

            return $event;
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    private function append(LifecycleTransitionData $data, EmployeeLifecycleState $from, EmployeeLifecycleState $to, string $type, array $payload): EmployeeLifecycleEvent
    {
        $correlation = (string) Str::uuid();
        $canonical = ['company_id' => $data->companyId, 'employee_id' => $data->employeeId, 'event_type' => $type, 'from' => $from->value, 'to' => $to->value, 'effective_at' => $data->effectiveAt->toIso8601String(), 'payload' => $payload];
        $checksum = $this->checksum($canonical);
        $event = EmployeeLifecycleEvent::query()->create(['company_id' => $data->companyId, 'employee_id' => $data->employeeId, 'event_type' => $type, 'from_state' => $from, 'to_state' => $to, 'effective_at' => $data->effectiveAt, 'correlation_id' => $correlation, 'idempotency_key' => $data->idempotencyKey, 'payload_checksum' => $checksum, 'created_by' => $data->actorId]);
        EmploymentTransition::query()->create(['company_id' => $data->companyId, 'employee_id' => $data->employeeId, 'transition_type' => $type, 'from_state' => $from->value, 'to_state' => $to->value, 'reason' => $data->reason, 'effective_at' => $data->effectiveAt, 'approved_by' => $data->actorId, 'checksum' => $checksum]);
        DB::table('employee_lifecycle_outbox')->insert(['event_id' => (string) Str::uuid(), 'company_id' => $data->companyId, 'employee_id' => $data->employeeId, 'event_type' => $type, 'payload' => json_encode($canonical, JSON_THROW_ON_ERROR), 'payload_checksum' => $checksum, 'occurred_at' => now(), 'created_at' => now()]);

        return $event;
    }

    private function currentState(int $companyId, int $employeeId): EmployeeLifecycleState
    {
        $value = EmployeeLifecycleEvent::query()->where('company_id', $companyId)->where('employee_id', $employeeId)->latest('id')->value('to_state');
        if ($value instanceof EmployeeLifecycleState) {
            return $value;
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages(['state' => 'Nhân viên chưa có Employee Lifecycle.']);
        }

        return EmployeeLifecycleState::from($value);
    }

    private function initializeAssignment(LifecycleTransitionData $data): void
    {
        DB::transaction(function () use ($data): void {
            $exists = EmployeeAssignment::query()
                ->where('employee_id', $data->employeeId)
                ->where('assignment_type', AssignmentType::Primary->value)
                ->whereDate('effective_from', '<=', $data->effectiveAt->toDateString())
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data->effectiveAt->toDateString()))
                ->exists();
            if ($exists) {
                return;
            }
            $assignment = $this->assignments->create($data->companyId, $data->employeeId, new EmployeeAssignmentData(
                $data->organizationUnitId ?? 0,
                $data->positionId ?? 0,
                $data->managerId,
                AssignmentType::Primary,
                '100.00',
                null,
                $data->effectiveAt->startOfDay(),
                null,
            ), $data->actorId);
            $payload = ['assignment_id' => (int) $assignment->id, 'position_id' => $data->positionId, 'organization_unit_id' => $data->organizationUnitId];
            EmployeePositionHistory::query()->create([
                'company_id' => $data->companyId,
                'employee_id' => $data->employeeId,
                'position_id' => $data->positionId,
                'organization_unit_id' => $data->organizationUnitId,
                'effective_from' => $data->effectiveAt->toDateString(),
                'checksum' => $this->checksum($payload),
            ]);
            if ($data->managerId !== null) {
                EmployeeManagerHistory::query()->create([
                    'company_id' => $data->companyId,
                    'employee_id' => $data->employeeId,
                    'manager_id' => $data->managerId,
                    'effective_from' => $data->effectiveAt->toDateString(),
                    'checksum' => $this->checksum(['manager_id' => $data->managerId, 'effective_from' => $data->effectiveAt->toDateString()]),
                ]);
            }
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    private function checksum(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
