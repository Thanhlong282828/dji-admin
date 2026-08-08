<?php

namespace Modules\Performance\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Performance\Application\Commands\ApproveSuccessionPlan\ApproveSuccessionPlanCommand;
use Modules\Performance\Application\Commands\AssessSuccessor\AssessSuccessorCommand;
use Modules\Performance\Application\Commands\AssignCareerPlan\AssignCareerPlanCommand;
use Modules\Performance\Application\Commands\CreateCareerPath\CreateCareerPathCommand;
use Modules\Performance\Application\Commands\CreateSuccessionPlan\CreateSuccessionPlanCommand;
use Modules\Performance\Application\Commands\NominateSuccessor\NominateSuccessorCommand;
use Modules\Performance\Application\Commands\UpdateCareerProgress\UpdateCareerProgressCommand;
use Modules\Performance\Application\DTOs\CareerActionData;
use Modules\Performance\Application\Queries\EmployeeCareer360\EmployeeCareer360Query;
use Modules\Performance\Application\Queries\SuccessionDashboard\SuccessionDashboardQuery;
use Modules\Performance\Policies\PerformancePolicy;
use Modules\Performance\Presentation\Http\Requests\CareerActionRequest;

final readonly class CareerController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private PerformancePolicy $policy) {}

    public function index(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, $c, 'career.view');

        return view('performance::career.dashboard', ['data' => $this->queries->ask(new SuccessionDashboardQuery($c)), 'companyId' => $c]);
    }

    public function employee(Request $r, int $employee): View
    {
        $c = $this->company($r);
        $this->allow($r, $c, 'career.view');

        return view('performance::career.employee', ['data' => $this->queries->ask(new EmployeeCareer360Query($c, $employee)), 'companyId' => $c]);
    }

    public function action(CareerActionRequest $r, string $action, ?int $subject = null): RedirectResponse
    {
        $c = $this->company($r);
        $map = ['path.create' => ['career.manage', CreateCareerPathCommand::class], 'plan.assign' => ['career.plan.create', AssignCareerPlanCommand::class], 'plan.progress' => ['career.manage', UpdateCareerProgressCommand::class], 'succession.create' => ['succession.manage', CreateSuccessionPlanCommand::class], 'succession.nominate' => ['succession.manage', NominateSuccessorCommand::class], 'succession.assess' => ['succession.manage', AssessSuccessorCommand::class], 'succession.approve' => ['succession.approve', ApproveSuccessionPlanCommand::class]];
        abort_unless(isset($map[$action]), 404);
        [$permission,$class] = $map[$action];
        $this->allow($r, $c, $permission);
        $command = new $class($this->data($r, $c, $action, $subject));
        $this->commands->dispatch($command);

        return back()->with('success', 'Đã cập nhật Career & Succession.');
    }

    private function data(CareerActionRequest $r, int $c, string $action, ?int $subject): CareerActionData
    {
        $key = $r->validated('idempotency_key');
        abort_unless(is_string($key), 422);
        $positions = $r->validated('position_ids');
        $levels = $r->validated('required_levels');
        $steps = [];
        if (is_array($positions)) {
            foreach (array_values($positions) as $i => $p) {
                if (is_int($p)) {
                    $steps[] = ['position_id' => $p, 'required_level' => is_array($levels) && is_numeric($levels[$i] ?? null) ? (int) $levels[$i] : 1, 'expected_duration_months' => null];
                }
            }
        }$name = $r->validated('name');
        $description = $r->validated('description');
        $readiness = $r->validated('readiness_level');
        $score = $r->validated('score');
        $reason = $r->validated('reason');

        return new CareerActionData($c, $this->actor($r), $key, careerPathId: $r->integer('career_path_id') ?: null, careerPlanId: $action === 'plan.progress' ? $subject : null, successionPlanId: in_array($action, ['succession.nominate', 'succession.assess', 'succession.approve'], true) ? $subject : null, candidateId: $action === 'succession.assess' ? $r->integer('candidate_id') ?: null:null, employeeId: $r->integer('employee_id') ?: null, positionId: $r->integer('position_id') ?: null, name: is_string($name) ? $name : null, description: is_string($description) ? $description : null, currentStep: $r->integer('current_step') ?: null, targetStep: $r->integer('target_step') ?: null, readinessLevel: is_string($readiness) ? $readiness : null, score: is_numeric($score) ? (string) $score : null, reason: is_string($reason) ? $reason : null, steps: $steps);
    }

    private function company(Request $r): int
    {
        $c = $r->integer('company_id');
        abort_if($c < 1, 422);

        return $c;
    }

    private function actor(Request $r): int
    {
        $id = $r->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : 0;
    }

    private function allow(Request $r, int $c, string $p): void
    {
        $u = $r->user();
        abort_unless($u !== null && $this->policy->allows($u, $c, $p), 403);
    }
}
