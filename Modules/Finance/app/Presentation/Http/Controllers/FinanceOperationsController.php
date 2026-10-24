<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Finance\Application\Commands\ApproveAssetDisposalCommand;
use Modules\Finance\Application\Commands\ApproveAssetImpairmentCommand;
use Modules\Finance\Application\Commands\CreateAssetDisposalCommand;
use Modules\Finance\Application\Commands\CreateAssetImpairmentCommand;
use Modules\Finance\Application\Commands\PostAssetDisposalCommand;
use Modules\Finance\Application\Commands\PostAssetImpairmentCommand;
use Modules\Finance\Application\Queries\GetAssetLifecycleQuery;
use Modules\Finance\Application\Queries\GetFixedAssetProcessHelpQuery;
use Modules\Finance\Application\Services\FinanceOperationsService;

final readonly class FinanceOperationsController
{
    public function __construct(private BranchAccessService $access, private FinanceOperationsService $service, private QueryBusInterface $queries, private CommandBusInterface $commands) {}

    public function index(Request $request, string $page = 'expenses'): View
    {
        $user = $this->user($request);
        $company = $request->integer('company_id');
        abort_unless($this->access->company($user, $company), 404);
        abort_unless(in_array($page, ['expenses', 'assets', 'budget'], true), 404);

        return view('admin.finance.operations', ['companyId' => $company, 'data' => $this->service->read($company, $page, max(2000, min(2100, $request->integer('year', (int) date('Y'))))), 'processHelp' => $page === 'assets' ? $this->queries->ask(new GetFixedAssetProcessHelpQuery($company, $request->integer('branch_id') ?: null, $user)) : null, 'assetLifecycle' => $page === 'assets' ? $this->queries->ask(new GetAssetLifecycleQuery($company, $request->integer('branch_id') ?: null, $user)) : null]);
    }

    public function mutate(Request $request): RedirectResponse
    {
        $validated = $request->validate(['company_id' => ['required', 'integer'], 'action' => ['required', 'string', 'max:40'], 'id' => ['nullable', 'integer'], 'asset_id' => ['nullable', 'integer'], 'to' => ['nullable', 'string'], 'code' => ['nullable', 'string', 'max:80'], 'name' => ['nullable', 'string', 'max:255'], 'branch_id' => ['nullable', 'integer'], 'cost_center_id' => ['nullable', 'integer'], 'expense_category_id' => ['nullable', 'integer'], 'asset_category_id' => ['nullable', 'integer'], 'vendor_id' => ['nullable', 'integer'], 'reference' => ['nullable', 'string', 'max:120'], 'amount_minor' => ['nullable', 'integer', 'min:1'], 'impairment_amount_minor' => ['nullable', 'integer', 'min:1'], 'proceeds_minor' => ['nullable', 'integer', 'min:0'], 'idempotency_key' => ['nullable', 'string', 'max:191'], 'review_date' => ['nullable', 'date'], 'disposal_date' => ['nullable', 'date'], 'expense_date' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:2000'], 'decision_reason' => ['nullable', 'string', 'max:1000'], 'asset_code' => ['nullable', 'string', 'max:80'], 'acquisition_cost_minor' => ['nullable', 'integer', 'min:1'], 'residual_value_minor' => ['nullable', 'integer', 'min:0'], 'acquisition_date' => ['nullable', 'date'], 'in_service_date' => ['nullable', 'date'], 'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'], 'effective_date' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:500'], 'period_month' => ['nullable', 'date'], 'year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'budget_id' => ['nullable', 'integer'], 'account_id' => ['nullable', 'integer'], 'month' => ['nullable', 'integer', 'min:1', 'max:12']]);
        $actionValue = is_array($validated) ? ($validated['action'] ?? null) : null;
        if (! is_array($validated) || ! is_string($actionValue)) {
            abort(422);
        }
        $data = [];
        foreach ($validated as $key => $value) {
            if (! is_string($key)) {
                abort(422);
            }
            $data[$key] = $value;
        }
        $user = $this->user($request);
        $company = $request->integer('company_id');
        abort_unless($this->access->company($user, $company), 404);
        $action = $actionValue;
        $assetCommand = match ($action) {
            'asset-impairment.create' => new CreateAssetImpairmentCommand($company, $user, $data),
            'asset-impairment.approve' => new ApproveAssetImpairmentCommand($company, $request->integer('id'), $user),
            'asset-impairment.post' => new PostAssetImpairmentCommand($company, $request->integer('id'), $user),
            'asset-disposal.create' => new CreateAssetDisposalCommand($company, $user, $data),
            'asset-disposal.approve' => new ApproveAssetDisposalCommand($company, $request->integer('id'), $user),
            'asset-disposal.post' => new PostAssetDisposalCommand($company, $request->integer('id'), $user),
            default => null,
        };
        if ($assetCommand !== null) {
            Gate::forUser($user)->authorize($action);
            $this->commands->dispatch($assetCommand);

            return back()->with('status', 'Asset lifecycle document đã cập nhật.');
        }
        $approvalAction = $action === 'budget.approve'
            || ($action === 'expense.transition' && in_array($data['to'] ?? null, ['approved', 'rejected', 'paid'], true));
        Gate::forUser($user)->authorize($approvalAction ? 'finance.operations.approve' : 'finance.operations.manage');
        $this->service->mutate($action, $company, $user, $data);

        return back()->with('status', 'Finance operation đã cập nhật.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
