<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Application\Commands\CompleteOnboarding\CompleteOnboardingCommand;
use Modules\Employee\Application\Commands\CreateEmployee\CreateEmployeeCommand;
use Modules\Employee\Application\Commands\CreateEmployeeLifecycle\CreateEmployeeLifecycleCommand;
use Modules\Employee\Application\Commands\DeleteEmployee\DeleteEmployeeCommand;
use Modules\Employee\Application\Commands\DemoteEmployee\DemoteEmployeeCommand;
use Modules\Employee\Application\Commands\PromoteEmployee\PromoteEmployeeCommand;
use Modules\Employee\Application\Commands\RestoreEmployee\RestoreEmployeeCommand;
use Modules\Employee\Application\Commands\StartOnboarding\StartOnboardingCommand;
use Modules\Employee\Application\Commands\TransferLifecycleEmployee\TransferEmployeeCommand as TransferLifecycleEmployeeCommand;
use Modules\Employee\Application\Commands\UpdateEmployee\UpdateEmployeeCommand;
use Modules\Employee\Application\Commands\UpdateEmployeeAssignment\UpdateEmployeeAssignmentCommand;
use Modules\Employee\Application\DTOs\EmployeeAssignmentData;
use Modules\Employee\Application\DTOs\LifecycleTransitionData;
use Modules\Employee\Application\Queries\GetEmployeeDetail\GetEmployeeDetailQuery;
use Modules\Employee\Application\Queries\GetEmployeeList\GetEmployeeListQuery;
use Modules\Employee\Domain\Enums\AssignmentType;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Domain\Models\EmployeeLifecycleEvent;
use Modules\Employee\Domain\Services\EmployeeAccountService;
use Modules\Employee\Domain\Services\EmployeeAvatarService;
use Modules\Employee\Policies\EmployeePolicy;
use Modules\Employee\Presentation\Http\Requests\StoreEmployeeRequest;
use Modules\Organization\Domain\Models\JobPosition;
use Modules\Organization\Domain\Models\OrganizationUnit;
use Spatie\Permission\Models\Role;
use Throwable;

final class EmployeeController extends Controller
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private EmployeePolicy $policy, private EmployeeAccountService $accounts, private EmployeeAvatarService $avatars) {}

    public function index(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('employee::employees.index', ['items' => $this->queries->ask(new GetEmployeeListQuery($c, $this->stringKeyed($r->only(['search', 'employment_status', 'employment_type', 'has_user', 'sort', 'direction', 'per_page'])))), 'companyId' => $c]);
    }

    public function create(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);

        return view('employee::employees.create', [...$this->formOptions($c), 'companyId' => $c, 'managerOptions' => $this->managerOptions($c), 'roleOptions' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')]);
    }

    public function store(StoreEmployeeRequest $r): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'create', $c);
        $avatar = $r->file('avatar');
        $stagedAvatar = $avatar === null ? null : $this->avatars->stage($avatar);
        try {
            $e = DB::transaction(function () use ($r, $c, $stagedAvatar): array {
                $employeeData = $this->stringKeyed($r->safe()->except(['company_id', 'organization_unit_id', 'job_position_id', 'avatar', 'create_account', 'account_email', 'account_password', 'account_password_confirmation', 'account_role', 'account_access_enabled']));
                $employee = $this->stringKeyed($this->commands->dispatch(new CreateEmployeeCommand($c, $employeeData, $this->actor($r))));
                $employeeIdValue = $employee['id'] ?? null;
                abort_unless(is_numeric($employeeIdValue), 422);
                $employeeId = (int) $employeeIdValue;
                $joinDate = $r->validated('join_date');
                abort_unless(is_string($joinDate), 422);
                $effectiveAt = CarbonImmutable::parse($joinDate)->startOfDay();
                $organizationUnitValue = $r->validated('organization_unit_id');
                $jobPositionValue = $r->validated('job_position_id');
                $organizationUnitId = is_numeric($organizationUnitValue) ? (int) $organizationUnitValue : null;
                $jobPositionId = is_numeric($jobPositionValue) ? (int) $jobPositionValue : null;
                $lifecycle = new LifecycleTransitionData($c, $employeeId, $this->actor($r), 'employee-create-hire-'.Str::uuid(), $effectiveAt, 'Khởi tạo từ hồ sơ nhân viên', null, $organizationUnitId, $jobPositionId, $r->integer('manager_id') ?: null);
                $this->commands->dispatch(new CreateEmployeeLifecycleCommand($lifecycle));
                if (in_array($r->validated('employment_status'), ['active', 'probation'], true)) {
                    $this->commands->dispatch(new StartOnboardingCommand(new LifecycleTransitionData($c, $employeeId, $this->actor($r), 'employee-create-onboarding-'.Str::uuid(), $effectiveAt, 'Khởi tạo từ hồ sơ nhân viên')));
                    $this->commands->dispatch(new CompleteOnboardingCommand(new LifecycleTransitionData($c, $employeeId, $this->actor($r), 'employee-create-active-'.Str::uuid(), $effectiveAt, 'Khởi tạo từ hồ sơ nhân viên')));
                }
                if ($r->boolean('create_account')) {
                    $email = $r->validated('account_email');
                    $password = $r->validated('account_password');
                    $role = $r->validated('account_role');
                    abort_unless(is_string($email) && is_string($password) && is_string($role), 422);
                    $this->accounts->save($c, $employeeId, ['email' => $email, 'password' => $password, 'role' => $role, 'access_enabled' => $r->boolean('account_access_enabled', true), 'verified' => true]);
                }
                if ($stagedAvatar !== null) {
                    $this->avatars->persist($c, $employeeId, $stagedAvatar);
                }

                return $employee;
            }, 3);
        } catch (Throwable $exception) {
            if ($stagedAvatar !== null) {
                $this->avatars->compensate($stagedAvatar);
            }
            throw $exception;
        }

        return to_route('admin.employees.show', ['employee' => $e['id'], 'company_id' => $c])->with('success', 'Đã tạo hồ sơ nhân viên.');
    }

    public function show(Request $r, int $employee): View
    {
        $c = $this->company($r);
        $this->allow($r, 'view', $c);

        return view('employee::employees.show', ['item' => $this->queries->ask(new GetEmployeeDetailQuery($c, $employee, (bool) $r->user()?->can('employee.view-sensitive'))), 'companyId' => $c]);
    }

    public function edit(Request $r, int $employee): View
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);

        $item = $this->stringKeyed($this->queries->ask(new GetEmployeeDetailQuery($c, $employee, false)));
        $current = EmployeeAssignment::query()->where('employee_id', $employee)->where('assignment_type', 'primary')->whereDate('effective_from', '<=', today())->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->latest('effective_from')->first();
        $item['organization_unit_id'] = $current?->organization_unit_id;
        $item['job_position_id'] = $current?->job_position_id;

        return view('employee::employees.edit', [...$this->formOptions($c), 'item' => $item, 'companyId' => $c, 'managerOptions' => $this->managerOptions($c, $employee), 'roleOptions' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')]);
    }

    public function update(StoreEmployeeRequest $r, int $employee): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'update', $c);
        $avatar = $r->file('avatar');
        $stagedAvatar = $avatar === null ? null : $this->avatars->stage($avatar);
        try {
            DB::transaction(function () use ($r, $c, $employee, $stagedAvatar): void {
                $current = EmployeeAssignment::query()
                    ->where('employee_id', $employee)
                    ->whereHas('employee', static fn (Builder $query): Builder => $query->where('company_id', $c))
                    ->where('assignment_type', AssignmentType::Primary->value)
                    ->whereDate('effective_from', '<=', today())
                    ->where(static fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))
                    ->latest('effective_from')
                    ->first();
                $employeeData = $this->stringKeyed($r->safe()->except(['company_id', 'organization_unit_id', 'job_position_id', 'avatar', 'create_account', 'account_email', 'account_password', 'account_password_confirmation', 'account_role', 'account_access_enabled']));
                $this->commands->dispatch(new UpdateEmployeeCommand($c, $employee, $employeeData, $this->actor($r)));
                if ($current === null) {
                    $joinDate = $r->validated('join_date');
                    abort_unless(is_string($joinDate), 422);
                    $effectiveAt = CarbonImmutable::parse($joinDate)->startOfDay();
                    $hadLifecycle = EmployeeLifecycleEvent::query()->where('company_id', $c)->where('employee_id', $employee)->exists();
                    $lifecycle = new LifecycleTransitionData($c, $employee, $this->actor($r), 'employee-edit-initialize-'.Str::uuid(), $effectiveAt, 'Khởi tạo phân công từ chỉnh sửa hồ sơ', null, $r->integer('organization_unit_id'), $r->integer('job_position_id'), $r->integer('manager_id') ?: null);
                    $this->commands->dispatch(new CreateEmployeeLifecycleCommand($lifecycle));
                    if (! $hadLifecycle && in_array($r->validated('employment_status'), ['active', 'probation'], true)) {
                        $this->commands->dispatch(new StartOnboardingCommand(new LifecycleTransitionData($c, $employee, $this->actor($r), 'employee-edit-onboarding-'.Str::uuid(), $effectiveAt, 'Khởi tạo từ chỉnh sửa hồ sơ')));
                        $this->commands->dispatch(new CompleteOnboardingCommand(new LifecycleTransitionData($c, $employee, $this->actor($r), 'employee-edit-active-'.Str::uuid(), $effectiveAt, 'Khởi tạo từ chỉnh sửa hồ sơ')));
                    }
                } elseif ($r->integer('organization_unit_id') !== (int) $current->organization_unit_id || $r->integer('job_position_id') !== (int) $current->job_position_id || ($r->integer('manager_id') ?: null) !== ($current->manager_id === null ? null : (int) $current->manager_id)) {
                    $this->changeCurrentAssignment($r, $c, $employee, $current);
                }
                if ($r->boolean('create_account')) {
                    $email = $r->validated('account_email');
                    $role = $r->validated('account_role');
                    abort_unless(is_string($email) && is_string($role), 422);
                    $account = ['email' => $email, 'role' => $role, 'access_enabled' => $r->boolean('account_access_enabled'), 'verified' => true];
                    $password = $r->validated('account_password');
                    if (is_string($password) && $password !== '') {
                        $account['password'] = $password;
                    }
                    $this->accounts->save($c, $employee, $account);
                }
                if ($stagedAvatar !== null) {
                    $this->avatars->persist($c, $employee, $stagedAvatar);
                }
            }, 3);
        } catch (Throwable $exception) {
            if ($stagedAvatar !== null) {
                $this->avatars->compensate($stagedAvatar);
            }
            throw $exception;
        }

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    private function changeCurrentAssignment(StoreEmployeeRequest $request, int $companyId, int $employeeId, EmployeeAssignment $current): void
    {
        $actorId = $this->actor($request);
        $managerId = $request->integer('manager_id') ?: null;
        $effectiveFrom = CarbonImmutable::parse((string) $current->effective_from)->startOfDay();
        $assignment = new EmployeeAssignmentData(
            $request->integer('organization_unit_id'),
            $request->integer('job_position_id'),
            $managerId,
            AssignmentType::Primary,
            number_format((float) $current->allocation_percent, 2, '.', ''),
            is_string($current->work_location) ? $current->work_location : null,
            $effectiveFrom->isBefore(today()) ? CarbonImmutable::today() : $effectiveFrom,
            null,
        );
        if (! $effectiveFrom->isBefore(today())) {
            $this->commands->dispatch(new UpdateEmployeeAssignmentCommand($companyId, (int) $current->id, $assignment, $actorId));

            return;
        }
        $sourceLevel = JobPosition::query()->where('company_id', $companyId)->whereKey($current->job_position_id)->value('career_level');
        $targetLevel = JobPosition::query()->where('company_id', $companyId)->whereKey($assignment->jobPositionId)->value('career_level');
        $promotion = is_numeric($sourceLevel) && is_numeric($targetLevel) && (int) $targetLevel > (int) $sourceLevel;
        $demotion = is_numeric($sourceLevel) && is_numeric($targetLevel) && (int) $targetLevel < (int) $sourceLevel;
        $transition = new LifecycleTransitionData($companyId, $employeeId, $actorId, 'employee-profile-assignment-'.Str::uuid(), $assignment->effectiveFrom, 'Cập nhật phân công từ hồ sơ nhân viên', (int) $current->id, $assignment->organizationUnitId, $assignment->jobPositionId, $managerId, $promotion, $demotion);
        $command = $promotion
            ? new PromoteEmployeeCommand($transition)
            : ($demotion ? new DemoteEmployeeCommand($transition) : new TransferLifecycleEmployeeCommand($transition));
        $this->commands->dispatch($command);
    }

    public function destroy(Request $r, int $employee): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'delete', $c);
        $this->commands->dispatch(new DeleteEmployeeCommand($c, $employee, $this->actor($r)));

        return to_route('admin.employees.index', ['company_id' => $c])->with('success', 'Đã lưu trữ hồ sơ nhân viên.');
    }

    public function restore(Request $r, int $employee): RedirectResponse
    {
        $c = $this->company($r);
        $this->allow($r, 'restore', $c);
        $this->commands->dispatch(new RestoreEmployeeCommand($c, $employee, $this->actor($r)));

        return back()->with('success', 'Đã khôi phục hồ sơ.');
    }

    private function company(Request $r): int
    {
        $c = $r->integer('company_id');
        abort_if($c < 1, 422, 'Vui lòng chọn công ty.');

        return $c;
    }

    private function actor(Request $r): int
    {
        $identifier = $r->user()?->getAuthIdentifier();

        return is_int($identifier) || is_numeric($identifier) ? (int) $identifier : 0;
    }

    private function allow(Request $r, string $action, int $company): void
    {
        $user = $r->user();
        abort_unless($user && $this->policy->{$action}($user, $company), 403);
    }

    /** @return list<array{id:int,label:string}> */
    private function managerOptions(int $companyId, ?int $excludeEmployeeId = null): array
    {
        $options = Employee::query()
            ->select(['id', 'employee_code', 'full_name'])
            ->where('company_id', $companyId)
            ->whereIn('employment_status', ['active', 'probation'])
            ->when($excludeEmployeeId !== null, static fn (Builder $query): Builder => $query->whereKeyNot($excludeEmployeeId))
            ->with(['assignments' => static function (Relation $relation): void {
                $relation->where('assignment_type', 'primary')->whereDate('effective_from', '<=', today())
                    ->where(static fn (Builder $active): Builder => $active->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))
                    ->with(['organizationUnit:id,name', 'jobPosition:id,title']);
            }])->orderBy('full_name')->get()->map(static function (Employee $employee): array {
                $assignment = $employee->assignments->first();
                $position = $assignment?->jobPosition;
                $unit = $assignment?->organizationUnit;
                $parts = [(string) $employee->full_name, $position === null ? 'Chưa có vị trí' : (string) $position->title, $unit === null ? 'Chưa có đơn vị' : (string) $unit->name];

                return ['id' => $employee->id, 'label' => implode(' — ', $parts)];
            })->all();

        return array_values($options);
    }

    /** @return array{organizationOptions:mixed,positionOptions:mixed} */
    private function formOptions(int $companyId): array
    {
        return [
            'organizationOptions' => OrganizationUnit::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'positionOptions' => JobPosition::query()->where('company_id', $companyId)->whereBetween('career_level', [1, 6])->with('organizationUnit:id,name')->orderBy('title')->get(['id', 'title', 'organization_unit_id', 'career_level']),
        ];
    }

    /** @return array<string, mixed> */
    private function stringKeyed(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }
}
