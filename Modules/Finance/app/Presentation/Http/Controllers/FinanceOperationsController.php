<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Finance\Application\Services\FinanceOperationsService;

final readonly class FinanceOperationsController
{
    public function __construct(private BranchAccessService $access, private FinanceOperationsService $service) {}

    public function index(Request $request, string $page = 'expenses'): View
    {
        $user = $this->user($request);
        $company = $request->integer('company_id');
        abort_unless($this->access->company($user, $company), 404);
        abort_unless(in_array($page, ['expenses', 'assets', 'budget'], true), 404);

        return view('admin.finance.operations', ['companyId' => $company, 'data' => $this->service->read($company, $page, max(2000, min(2100, $request->integer('year', (int) date('Y')))))]);
    }

    public function mutate(Request $request): RedirectResponse
    {
        $validated = $request->validate(['company_id' => ['required', 'integer'], 'action' => ['required', 'string', 'max:40'], 'id' => ['nullable', 'integer'], 'to' => ['nullable', 'string'], 'code' => ['nullable', 'string', 'max:80'], 'name' => ['nullable', 'string', 'max:255'], 'branch_id' => ['nullable', 'integer'], 'cost_center_id' => ['nullable', 'integer'], 'expense_category_id' => ['nullable', 'integer'], 'asset_category_id' => ['nullable', 'integer'], 'vendor_id' => ['nullable', 'integer'], 'reference' => ['nullable', 'string', 'max:80'], 'amount_minor' => ['nullable', 'integer', 'min:1'], 'expense_date' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:2000'], 'decision_reason' => ['nullable', 'string', 'max:1000'], 'asset_code' => ['nullable', 'string', 'max:80'], 'acquisition_cost_minor' => ['nullable', 'integer', 'min:1'], 'residual_value_minor' => ['nullable', 'integer', 'min:0'], 'acquisition_date' => ['nullable', 'date'], 'in_service_date' => ['nullable', 'date'], 'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'], 'effective_date' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:500'], 'period_month' => ['nullable', 'date'], 'disposed_on' => ['nullable', 'date'], 'year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'budget_id' => ['nullable', 'integer'], 'account_id' => ['nullable', 'integer'], 'month' => ['nullable', 'integer', 'min:1', 'max:12']]);
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
        $approvalAction = $action === 'budget.approve'
            || $action === 'asset.dispose'
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
