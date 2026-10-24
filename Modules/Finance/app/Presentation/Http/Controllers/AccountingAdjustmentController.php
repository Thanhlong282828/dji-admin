<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Finance\Application\Commands\MutateAccountingAdjustmentCommand;
use Modules\Finance\Application\Queries\GetAccountingAdjustmentsQuery;

final readonly class AccountingAdjustmentController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries, private CommandBusInterface $commands) {}

    public function index(Request $request): View
    {
        [$user,$company] = $this->scope($request);

        return view('admin.finance.accounting-adjustments', ['companyId' => $company, 'data' => $this->queries->ask(new GetAccountingAdjustmentsQuery($company)), 'user' => $user]);
    }

    public function mutate(Request $request): RedirectResponse
    {
        [$user,$company] = $this->scope($request);
        $validated = $request->validate(['company_id' => ['required', 'integer'], 'action' => ['required', 'in:create,submit,approve,reject,post'], 'id' => ['nullable', 'integer'], 'original_journal_id' => ['nullable', 'integer'], 'target_accounting_period_id' => ['nullable', 'integer'], 'reference' => ['nullable', 'string', 'max:120'], 'reason' => ['nullable', 'string', 'max:1000']]);
        abort_unless(is_array($validated), 422);
        $action = $request->string('action')->toString();
        $permission = match ($action) {
            'create' => 'accounting.adjustment.create','submit' => 'accounting.adjustment.submit','approve' => 'accounting.adjustment.approve','reject' => 'accounting.adjustment.reject','post' => 'accounting.adjustment.post', default => abort(422)
        };
        abort_unless($user->can($permission), 403);
        $this->commands->dispatch(new MutateAccountingAdjustmentCommand($action, $company, $user, $this->stringKeys($validated)));

        return back()->with('status', 'Accounting Adjustment đã cập nhật.');
    }

    /** @return array{User,int} */
    private function scope(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $company = $request->integer('company_id');
        abort_unless($this->access->company($user, $company), 404);

        return [$user, $company];
    }

    /**
     * @param  array<array-key,mixed>  $data
     * @return array<string,mixed>
     */
    private function stringKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
