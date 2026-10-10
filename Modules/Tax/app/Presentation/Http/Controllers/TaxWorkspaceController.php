<?php

namespace Modules\Tax\Presentation\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Tax\Application\Commands\ActivateVatPolicyCommand;
use Modules\Tax\Application\Commands\ApproveVatPolicyCommand;
use Modules\Tax\Application\Commands\CreateVatPolicyCommand;
use Modules\Tax\Application\Commands\ExpireVatPolicyCommand;
use Modules\Tax\Application\Commands\UpdateVatPolicyDraftCommand;
use Modules\Tax\Application\Queries\GetTaxReportQuery;
use Modules\Tax\Presentation\Http\Requests\VatPolicyRequest;

final readonly class TaxWorkspaceController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries, private CommandBusInterface $commands) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id', 1);
        abort_unless($this->access->company($user, $companyId), 404);
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
        ]);
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        if ($branchId !== null) {
            abort_unless($this->access->branch($user, $companyId, $branchId), 404);
        }
        $month = CarbonImmutable::now()->startOfMonth();
        $from = is_string($validated['from'] ?? null) ? $validated['from'] : $month->toDateString();
        $to = is_string($validated['to'] ?? null) ? $validated['to'] : $month->endOfMonth()->toDateString();
        abort_if($from > $to, 422, 'Tax report period invalid.');
        $currency = is_string($validated['currency'] ?? null) ? $validated['currency'] : 'VND';
        $report = $this->queries->ask(new GetTaxReportQuery($companyId, $branchId, $from, $to, $currency, $user));
        abort_unless(is_array($report), 500);

        return view('admin.tax.workspace', compact('companyId', 'report'));
    }

    public function mutate(VatPolicyRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id');
        abort_unless($this->access->company($user, $companyId), 404);
        $action = $request->string('action')->toString();
        $permission = $action === 'tax-policy.expire' ? 'tax-policy.activate' : $action;
        abort_unless($user->can($permission), 403);
        $data = $request->validated();
        $policyId = $request->integer('policy_id');
        $command = match ($action) {
            'tax-policy.create' => new CreateVatPolicyCommand($companyId, $user, $data),
            'tax-policy.update' => new UpdateVatPolicyDraftCommand($companyId, $policyId, $user, $data),
            'tax-policy.approve' => new ApproveVatPolicyCommand($companyId, $policyId, $user),
            'tax-policy.activate' => new ActivateVatPolicyCommand($companyId, $policyId, $user),
            'tax-policy.expire' => new ExpireVatPolicyCommand($companyId, $policyId, $user),
            default => abort(422, 'VAT policy action invalid.'),
        };
        $this->commands->dispatch($command);

        return back()->with('success', 'VAT policy lifecycle đã cập nhật.');
    }
}
