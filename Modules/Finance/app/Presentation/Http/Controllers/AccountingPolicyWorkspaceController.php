<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Finance\Application\Queries\GetAccountingPolicyWorkspaceQuery;

final readonly class AccountingPolicyWorkspaceController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $companyId), 404);
        $request->validate(['policy_id' => ['nullable', 'integer', 'min:1']]);
        $policyId = $request->filled('policy_id') ? $request->integer('policy_id') : null;
        $workspace = $this->queries->ask(new GetAccountingPolicyWorkspaceQuery($companyId, $policyId, $user));
        abort_unless(is_array($workspace), 500);

        return view('admin.finance.accounting-policy-workspace', compact('companyId', 'policyId', 'workspace'));
    }
}
