<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Finance\Application\Commands\ApproveFxRevaluationRunCommand;
use Modules\Finance\Application\Commands\CreateFxRateCommand;
use Modules\Finance\Application\Commands\CreateFxRevaluationRunCommand;
use Modules\Finance\Application\Commands\PostFxRevaluationRunCommand;
use Modules\Finance\Application\Commands\ReverseFxRevaluationRunCommand;
use Modules\Finance\Application\Commands\SubmitFxRevaluationRunCommand;
use Modules\Finance\Application\Commands\TransitionFxRateCommand;
use Modules\Finance\Application\Queries\GetFxWorkspaceQuery;
use Modules\Finance\Presentation\Http\Requests\FxRateRequest;
use Modules\Finance\Presentation\Http\Requests\FxRevaluationCreateRequest;
use Modules\Finance\Presentation\Http\Requests\FxRevaluationTransitionRequest;

final readonly class FxWorkspaceController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries, private CommandBusInterface $commands) {}

    public function rates(Request $request): View
    {
        return $this->view($request, 'rates', 'admin.finance.fx-rates');
    }

    public function exposure(Request $request): View
    {
        return $this->view($request, 'exposure', 'admin.finance.fx-exposure');
    }

    public function revaluations(Request $request): View
    {
        return $this->view($request, 'revaluations', 'admin.finance.fx-revaluations');
    }

    public function mutate(FxRateRequest $request): RedirectResponse
    {
        [$user, $companyId] = $this->scope($request);
        $validated = $request->validated();
        $action = (string) $validated['action'];
        $permission = in_array($action, ['approve', 'activate'], true) ? 'finance.fx-rate.approve' : 'finance.fx-rate.manage';
        abort_unless($user->can($permission), 403);
        if ($action === 'create') {
            $this->commands->dispatch(new CreateFxRateCommand($companyId, $user, $validated));
        } else {
            $this->commands->dispatch(new TransitionFxRateCommand($companyId, (int) ($validated['rate_id'] ?? 0), $action, $user));
        }

        return back()->with('status', 'FX Rate Governance đã cập nhật.');
    }

    public function transitionRevaluation(FxRevaluationTransitionRequest $request, int $run, string $action): RedirectResponse
    {
        [$user, $companyId] = $this->scope($request);
        $permission = match ($action) {
            'submit' => 'finance.fx-revaluation.manage',
            'approve' => 'finance.fx-revaluation.approve',
            'post', 'reverse' => 'finance.fx-revaluation.post',
            default => abort(404),
        };
        abort_unless($user->can($permission), 403);
        $command = match ($action) {
            'submit' => new SubmitFxRevaluationRunCommand($companyId, $run, $user),
            'approve' => new ApproveFxRevaluationRunCommand($companyId, $run, $user),
            'post' => new PostFxRevaluationRunCommand($companyId, $run, $user),
            'reverse' => new ReverseFxRevaluationRunCommand($companyId, $run, $user),
        };
        $this->commands->dispatch($command);

        return back()->with('status', 'FX Revaluation đã cập nhật.');
    }

    public function createRevaluation(FxRevaluationCreateRequest $request): RedirectResponse
    {
        [$user, $companyId] = $this->scope($request);
        abort_unless($user->can('finance.fx-revaluation.manage'), 403);
        $data = $request->validated();
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($branchId !== null) {
            abort_unless($this->access->branch($user, $companyId, $branchId), 404);
        }
        $runId = $this->commands->dispatch(new CreateFxRevaluationRunCommand(
            $companyId, $branchId, (int) $data['accounting_period_id'], (string) $data['as_of_date'],
            (string) $data['idempotency_key'], $user, [],
        ));

        return back()->with('status', 'Đã tạo FX Revaluation run #'.$runId.' từ AR/AP evidence hợp lệ.');
    }

    private function view(Request $request, string $workspace, string $view): View
    {
        [$user, $companyId] = $this->scope($request);
        $filters = ['pair' => $request->string('pair')->toString(), 'status' => $request->string('status')->toString()];
        $data = $this->queries->ask(new GetFxWorkspaceQuery($companyId, $workspace, $user, $filters));
        abort_unless(is_array($data), 500);

        return view($view, compact('companyId', 'data'));
    }

    /** @return array{User,int} */
    private function scope(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id');
        abort_unless($this->access->company($user, $companyId), 404);

        return [$user, $companyId];
    }
}
