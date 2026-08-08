<?php

namespace Modules\Performance\Presentation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Performance\Application\Commands\ApprovePerformanceReview\ApprovePerformanceReviewCommand;
use Modules\Performance\Application\Commands\AssignEmployeeGoal\AssignEmployeeGoalCommand;
use Modules\Performance\Application\Commands\ClosePerformanceCycle\ClosePerformanceCycleCommand;
use Modules\Performance\Application\Commands\CreatePerformanceCycle\CreatePerformanceCycleCommand;
use Modules\Performance\Application\Commands\StartPerformanceReview\StartPerformanceReviewCommand;
use Modules\Performance\Application\Commands\SubmitManagerReview\SubmitManagerReviewCommand;
use Modules\Performance\Application\Commands\SubmitSelfReview\SubmitSelfReviewCommand;
use Modules\Performance\Application\Commands\UpdateGoalProgress\UpdateGoalProgressCommand;
use Modules\Performance\Application\DTOs\PerformanceActionData;
use Modules\Performance\Application\Queries\EmployeePerformance360\EmployeePerformance360Query;
use Modules\Performance\Application\Queries\GetPerformanceDashboard\GetPerformanceDashboardQuery;
use Modules\Performance\Policies\PerformancePolicy;
use Modules\Performance\Presentation\Http\Requests\PerformanceActionRequest;

final readonly class PerformanceController
{
    public function __construct(private CommandBusInterface $commands, private QueryBusInterface $queries, private PerformancePolicy $policy) {}

    public function index(Request $r): View
    {
        $c = $this->company($r);
        $this->allow($r, $c, 'talent.performance.view');

        return view('performance::dashboard', ['data' => $this->queries->ask(new GetPerformanceDashboardQuery($c)), 'companyId' => $c]);
    }

    public function show(Request $r, int $employee): View
    {
        $c = $this->company($r);
        $this->allow($r, $c, 'talent.performance.view');

        return view('performance::employee', ['viewModel' => $this->queries->ask(new EmployeePerformance360Query($c, $employee)), 'companyId' => $c]);
    }

    public function action(PerformanceActionRequest $r, string $action, ?int $subject = null): RedirectResponse
    {
        $c = $this->company($r);
        $map = ['cycle.create' => ['talent.performance.manage', CreatePerformanceCycleCommand::class], 'goal.assign' => ['talent.goal.create', AssignEmployeeGoalCommand::class], 'goal.progress' => ['talent.goal.update', UpdateGoalProgressCommand::class], 'review.start' => ['talent.performance.manage', StartPerformanceReviewCommand::class], 'review.self' => ['talent.review.submit', SubmitSelfReviewCommand::class], 'review.manager' => ['talent.review.submit', SubmitManagerReviewCommand::class], 'review.approve' => ['talent.review.approve', ApprovePerformanceReviewCommand::class], 'cycle.close' => ['talent.review.close', ClosePerformanceCycleCommand::class]];
        abort_unless(isset($map[$action]), 404);
        [$permission,$class] = $map[$action];
        $this->allow($r, $c, $permission);
        $d = $this->data($r, $c, $action, $subject);
        $this->commands->dispatch(new $class($d));

        return back()->with('success', 'Đã cập nhật Talent & Performance.');
    }

    private function data(PerformanceActionRequest $r, int $c, string $action, ?int $subject): PerformanceActionData
    {
        $start = $r->validated('period_start');
        $end = $r->validated('period_end');
        $name = $r->validated('name');
        $title = $r->validated('title');
        $desc = $r->validated('description');
        $reason = $r->validated('reason');
        $weight = $r->validated('weight');
        $progress = $r->validated('progress');
        $rating = $r->validated('rating');
        $key = $r->validated('idempotency_key');
        abort_unless(is_string($key), 422);

        $value = $action === 'goal.assign' ? $weight : ($action === 'goal.progress' ? $progress : null);

        return new PerformanceActionData($c, $this->actor($r), $key, $action === 'cycle.close' ? $subject : ($r->integer('cycle_id') ?: null), $r->integer('employee_id') ?: null, in_array($action, ['goal.progress'], true) ? $subject : null, in_array($action, ['review.self', 'review.manager', 'review.approve'], true) ? $subject : null, $r->integer('reviewer_id') ?: null, is_string($name) ? $name : null, is_string($title) ? $title : null, is_string($desc) ? $desc : null, is_string($reason) ? $reason : null, is_numeric($value) ? (string) $value : null, is_numeric($rating) ? (string) $rating : null, is_string($start) ? CarbonImmutable::parse($start) : null, is_string($end) ? CarbonImmutable::parse($end) : null);
    }

    private function company(Request $r): int
    {
        $c = $r->integer('company_id');
        abort_if($c < 1, 422, 'Vui lòng chọn công ty.');

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
