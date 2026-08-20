<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Support\Compensation\CompensationReadService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Application\Commands\CompleteOnboarding\CompleteOnboardingCommand;
use Modules\Employee\Application\Commands\CreateEmployeeLifecycle\CreateEmployeeLifecycleCommand;
use Modules\Employee\Application\Commands\DemoteEmployee\DemoteEmployeeCommand;
use Modules\Employee\Application\Commands\PromoteEmployee\PromoteEmployeeCommand;
use Modules\Employee\Application\Commands\StartOnboarding\StartOnboardingCommand;
use Modules\Employee\Application\Commands\TerminateEmployee\TerminateEmployeeCommand;
use Modules\Employee\Application\Commands\TransferLifecycleEmployee\TransferEmployeeCommand;
use Modules\Employee\Application\DTOs\LifecycleTransitionData;
use Modules\Employee\Application\Queries\Employee360View\Employee360ViewQuery;
use Modules\Employee\Application\Queries\GetEmployeeLifecycleList\GetEmployeeLifecycleListQuery;
use Modules\Employee\Domain\Models\EmployeeAssignment;
use Modules\Employee\Policies\EmployeeLifecyclePolicy;
use Modules\Employee\Presentation\Http\Requests\EmployeeLifecycleActionRequest;
use Modules\Organization\Domain\Models\JobPosition;

final readonly class EmployeeLifecycleController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private EmployeeLifecyclePolicy $policy, private CompensationReadService $compensation) {}

    public function index(Request $request): View
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.lifecycle.view');

        $items = $this->queries->ask(new GetEmployeeLifecycleListQuery($company, $request->string('search')->toString() ?: null, $request->string('state')->toString() ?: null, $request->integer('per_page', 15)));
        abort_unless($items instanceof LengthAwarePaginator, 500);
        $canViewCompensation = $request->user()?->can('payroll.assignment.view') === true;
        $employeeIds = [];
        foreach ($items->items() as $item) {
            if (is_array($item) && is_numeric($item['id'] ?? null)) {
                $employeeIds[] = (int) $item['id'];
            }
        }
        $compensation = $canViewCompensation ? $this->compensation->currentForEmployees($company, $employeeIds) : [];

        return view('employee::lifecycle.index', ['items' => $items, 'companyId' => $company, 'compensation' => $compensation, 'canViewCompensation' => $canViewCompensation]);
    }

    public function show(Request $request, int $employee): View
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.lifecycle.view');

        $current = EmployeeAssignment::query()->where('employee_id', $employee)->where('assignment_type', 'primary')->whereDate('effective_from', '<=', today())->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))->with('jobPosition:id,title,career_level,organization_unit_id')->latest('effective_from')->first();
        $positions = JobPosition::query()->where('company_id', $company)->whereBetween('career_level', [1, 6])->with('organizationUnit:id,name')->orderBy('career_level')->get(['id', 'title', 'career_level', 'organization_unit_id']);

        $canViewCompensation = $request->user()?->can('payroll.assignment.view') === true;
        $currentCompensation = $canViewCompensation ? ($this->compensation->currentForEmployees($company, [$employee])[$employee] ?? null) : null;
        $positionReferences = $canViewCompensation ? collect($this->compensation->positionReferences($company))->keyBy('position_id') : collect();

        return view('employee::lifecycle.show', ['viewModel' => $this->queries->ask(new Employee360ViewQuery($company, $employee)), 'companyId' => $company, 'currentAssignment' => $current, 'positionOptions' => $positions, 'currentCompensation' => $currentCompensation, 'positionReferences' => $positionReferences, 'canViewCompensation' => $canViewCompensation]);
    }

    public function createLifecycle(EmployeeLifecycleActionRequest $request, int $employee): RedirectResponse
    {
        return $this->run($request, $employee, 'hr.lifecycle.create', fn ($d) => new CreateEmployeeLifecycleCommand($d), 'Đã khởi tạo Employee Lifecycle.');
    }

    public function startOnboarding(EmployeeLifecycleActionRequest $request, int $employee): RedirectResponse
    {
        return $this->run($request, $employee, 'hr.lifecycle.onboarding', fn ($d) => new StartOnboardingCommand($d), 'Đã bắt đầu onboarding.');
    }

    public function completeOnboarding(EmployeeLifecycleActionRequest $request, int $employee): RedirectResponse
    {
        return $this->run($request, $employee, 'hr.lifecycle.onboarding', fn ($d) => new CompleteOnboardingCommand($d), 'Đã hoàn tất onboarding.');
    }

    public function transfer(EmployeeLifecycleActionRequest $request, int $employee): RedirectResponse
    {
        return $this->run($request, $employee, 'hr.lifecycle.transfer', fn ($d) => new TransferEmployeeCommand($d), 'Đã ghi nhận điều chuyển.', false);
    }

    public function promote(EmployeeLifecycleActionRequest $request, int $employee): RedirectResponse
    {
        return $this->run($request, $employee, 'hr.lifecycle.promote', fn ($d) => new PromoteEmployeeCommand($d), 'Đã ghi nhận thăng chức.', true);
    }

    public function demote(EmployeeLifecycleActionRequest $request, int $employee): RedirectResponse
    {
        return $this->run($request, $employee, 'hr.lifecycle.promote', fn ($d) => new DemoteEmployeeCommand($d), 'Đã ghi nhận giáng chức.', false, true);
    }

    public function terminate(EmployeeLifecycleActionRequest $request, int $employee): RedirectResponse
    {
        return $this->run($request, $employee, 'hr.lifecycle.terminate', fn ($d) => new TerminateEmployeeCommand($d), 'Đã chấm dứt vòng đời nhân sự.');
    }

    /** @param callable(LifecycleTransitionData):object $factory */
    private function run(EmployeeLifecycleActionRequest $request, int $employee, string $permission, callable $factory, string $message, bool $promotion = false, bool $demotion = false): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorize($request, $company, $permission);
        $idempotencyKey = $request->validated('idempotency_key');
        $effectiveAt = $request->validated('effective_at');
        abort_unless(is_string($idempotencyKey) && is_string($effectiveAt), 422);
        $data = new LifecycleTransitionData($company, $employee, $this->actor($request), $idempotencyKey, CarbonImmutable::parse($effectiveAt), is_string($request->validated('reason')) ? $request->validated('reason') : null, $request->integer('current_assignment_id') ?: null, $request->integer('organization_unit_id') ?: null, $request->integer('position_id') ?: null, $request->integer('manager_id') ?: null, $promotion, $demotion);
        $this->commands->dispatch($factory($data));

        return back()->with('success', $message);
    }

    private function company(Request $request): int
    {
        $id = $request->integer('company_id');
        abort_if($id < 1, 422, 'Vui lòng chọn công ty.');

        return $id;
    }

    private function actor(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : 0;
    }

    private function authorize(Request $request, int $company, string $permission): void
    {
        $user = $request->user();
        abort_unless($user !== null && $this->policy->allows($user, $company, $permission), 403);
    }
}
