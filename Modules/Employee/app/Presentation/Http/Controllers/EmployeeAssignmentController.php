<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Application\Commands\CreateEmployeeAssignment\CreateEmployeeAssignmentCommand;
use Modules\Employee\Application\Commands\EndEmployeeAssignment\EndEmployeeAssignmentCommand;
use Modules\Employee\Application\Commands\TransferEmployee\TransferEmployeeCommand;
use Modules\Employee\Application\Commands\UpdateEmployeeAssignment\UpdateEmployeeAssignmentCommand;
use Modules\Employee\Application\DTOs\EmployeeAssignmentData;
use Modules\Employee\Application\Queries\GetEmployeeAssignments\GetEmployeeAssignmentsQuery;
use Modules\Employee\Policies\EmployeeAssignmentPolicy;
use Modules\Employee\Presentation\Http\Requests\EndEmployeeAssignmentRequest;
use Modules\Employee\Presentation\Http\Requests\StoreEmployeeAssignmentRequest;

final readonly class EmployeeAssignmentController
{
    public function __construct(
        private CommandBusInterface $commands,
        private QueryBusInterface $queries,
        private EmployeeAssignmentPolicy $policy,
    ) {}

    public function index(Request $request, int $employee): View
    {
        $companyId = $this->company($request);
        $this->authorize($request, 'view', $companyId);

        return view('employee::assignments.index', [
            'employeeId' => $employee,
            'companyId' => $companyId,
            'assignments' => $this->queries->ask(new GetEmployeeAssignmentsQuery($companyId, $employee)),
        ]);
    }

    public function store(StoreEmployeeAssignmentRequest $request, int $employee): RedirectResponse
    {
        $companyId = $this->company($request);
        $this->authorize($request, 'create', $companyId);
        $this->commands->dispatch(new CreateEmployeeAssignmentCommand($companyId, $employee, EmployeeAssignmentData::fromArray($this->payload($request)), $this->actor($request)));

        return back()->with('success', 'Đã tạo phân công nhân sự.');
    }

    public function update(StoreEmployeeAssignmentRequest $request, int $assignment): RedirectResponse
    {
        $companyId = $this->company($request);
        $this->authorize($request, 'update', $companyId);
        $this->commands->dispatch(new UpdateEmployeeAssignmentCommand($companyId, $assignment, EmployeeAssignmentData::fromArray($this->payload($request)), $this->actor($request)));

        return back()->with('success', 'Đã cập nhật phân công.');
    }

    public function end(EndEmployeeAssignmentRequest $request, int $assignment): RedirectResponse
    {
        $companyId = $this->company($request);
        $this->authorize($request, 'end', $companyId);
        $effectiveTo = $request->validated('effective_to');
        $reason = $request->validated('reason');
        abort_unless(is_string($effectiveTo) && is_string($reason), 422);
        $this->commands->dispatch(new EndEmployeeAssignmentCommand($companyId, $assignment, CarbonImmutable::parse($effectiveTo), $reason, $this->actor($request)));

        return back()->with('success', 'Đã kết thúc phân công.');
    }

    public function transfer(StoreEmployeeAssignmentRequest $request, int $employee): RedirectResponse
    {
        return $this->move($request, $employee, false);
    }

    public function promote(StoreEmployeeAssignmentRequest $request, int $employee): RedirectResponse
    {
        return $this->move($request, $employee, true);
    }

    private function move(StoreEmployeeAssignmentRequest $request, int $employee, bool $promotion): RedirectResponse
    {
        $companyId = $this->company($request);
        $this->authorize($request, $promotion ? 'promote' : 'transfer', $companyId);
        $currentId = $request->integer('current_assignment_id');
        abort_if($currentId < 1, 422, 'Thiếu phân công hiện tại.');
        $this->commands->dispatch(new TransferEmployeeCommand(
            $companyId,
            $employee,
            $currentId,
            EmployeeAssignmentData::fromArray($this->payload($request)),
            is_string($request->validated('reason')) ? $request->validated('reason') : '',
            $this->actor($request),
            $promotion,
        ));

        return back()->with('success', $promotion ? 'Đã ghi nhận thăng chức.' : 'Đã chuyển công tác.');
    }

    /** @return array<string, mixed> */
    private function payload(StoreEmployeeAssignmentRequest $request): array
    {
        $payload = $request->safe()->only(['organization_unit_id', 'job_position_id', 'manager_id', 'assignment_type', 'allocation_percent', 'work_location', 'effective_from', 'effective_to']);
        $normalized = [];
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function company(Request $request): int
    {
        $companyId = $request->integer('company_id');
        abort_if($companyId < 1, 422, 'Vui lòng chọn công ty.');

        return $companyId;
    }

    private function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : 0;
    }

    private function authorize(Request $request, string $ability, int $companyId): void
    {
        $user = $request->user();
        abort_unless($user !== null && match ($ability) {
            'view' => $this->policy->view($user, $companyId),
            'create' => $this->policy->create($user, $companyId),
            'update' => $this->policy->update($user, $companyId),
            'end' => $this->policy->end($user, $companyId),
            'transfer' => $this->policy->transfer($user, $companyId),
            'promote' => $this->policy->promote($user, $companyId),
            default => false,
        }, 403);
    }
}
