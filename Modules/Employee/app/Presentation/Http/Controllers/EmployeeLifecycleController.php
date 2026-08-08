<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Application\Commands\CompleteOnboarding\CompleteOnboardingCommand;
use Modules\Employee\Application\Commands\CreateEmployeeLifecycle\CreateEmployeeLifecycleCommand;
use Modules\Employee\Application\Commands\PromoteEmployee\PromoteEmployeeCommand;
use Modules\Employee\Application\Commands\StartOnboarding\StartOnboardingCommand;
use Modules\Employee\Application\Commands\TerminateEmployee\TerminateEmployeeCommand;
use Modules\Employee\Application\Commands\TransferLifecycleEmployee\TransferEmployeeCommand;
use Modules\Employee\Application\DTOs\LifecycleTransitionData;
use Modules\Employee\Application\Queries\Employee360View\Employee360ViewQuery;
use Modules\Employee\Application\Queries\GetEmployeeLifecycleList\GetEmployeeLifecycleListQuery;
use Modules\Employee\Policies\EmployeeLifecyclePolicy;
use Modules\Employee\Presentation\Http\Requests\EmployeeLifecycleActionRequest;

final readonly class EmployeeLifecycleController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private EmployeeLifecyclePolicy $policy) {}

    public function index(Request $request): View
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.lifecycle.view');

        return view('employee::lifecycle.index', ['items' => $this->queries->ask(new GetEmployeeLifecycleListQuery($company, $request->string('search')->toString() ?: null, $request->string('state')->toString() ?: null, $request->integer('per_page', 15))), 'companyId' => $company]);
    }

    public function show(Request $request, int $employee): View
    {
        $company = $this->company($request);
        $this->authorize($request, $company, 'hr.lifecycle.view');

        return view('employee::lifecycle.show', ['viewModel' => $this->queries->ask(new Employee360ViewQuery($company, $employee)), 'companyId' => $company]);
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

    public function terminate(EmployeeLifecycleActionRequest $request, int $employee): RedirectResponse
    {
        return $this->run($request, $employee, 'hr.lifecycle.terminate', fn ($d) => new TerminateEmployeeCommand($d), 'Đã chấm dứt vòng đời nhân sự.');
    }

    /** @param callable(LifecycleTransitionData):object $factory */
    private function run(EmployeeLifecycleActionRequest $request, int $employee, string $permission, callable $factory, string $message, bool $promotion = false): RedirectResponse
    {
        $company = $this->company($request);
        $this->authorize($request, $company, $permission);
        $idempotencyKey = $request->validated('idempotency_key');
        $effectiveAt = $request->validated('effective_at');
        abort_unless(is_string($idempotencyKey) && is_string($effectiveAt), 422);
        $data = new LifecycleTransitionData($company, $employee, $this->actor($request), $idempotencyKey, CarbonImmutable::parse($effectiveAt), is_string($request->validated('reason')) ? $request->validated('reason') : null, $request->integer('current_assignment_id') ?: null, $request->integer('organization_unit_id') ?: null, $request->integer('position_id') ?: null, $request->integer('manager_id') ?: null, $promotion);
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
