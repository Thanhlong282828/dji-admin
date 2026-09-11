<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Finance\Application\Services\AccountingPeriodCloseService;
use Modules\Finance\Application\Services\ApprovalInboxService;
use Modules\Finance\Application\Services\TreasuryService;

final readonly class FinanceControlController
{
    public function __construct(private BranchAccessService $access, private TreasuryService $treasury, private AccountingPeriodCloseService $periods, private ApprovalInboxService $approvals) {}

    public function treasury(Request $request): View
    {
        [$user, $company] = $this->scope($request);

        return view('admin.finance.treasury', ['companyId' => $company, 'data' => $this->treasury->read($company), 'user' => $user]);
    }

    public function treasuryMutate(Request $request): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        Gate::forUser($user)->authorize('treasury.manage');
        $validated = $request->validate(['company_id' => ['required', 'integer'], 'action' => ['required', 'string'], 'accounting_account_id' => ['nullable', 'integer'], 'branch_id' => ['nullable', 'integer'], 'code' => ['nullable', 'string', 'max:60'], 'name' => ['nullable', 'string', 'max:180'], 'type' => ['nullable', 'in:cash,bank,payment_clearing'], 'note' => ['nullable', 'string', 'max:1000'], 'financial_account_id' => ['nullable', 'integer'], 'period_from' => ['nullable', 'date'], 'period_to' => ['nullable', 'date', 'after_or_equal:period_from'], 'session_id' => ['nullable', 'integer'], 'external_reference' => ['nullable', 'string', 'max:120'], 'occurred_at' => ['nullable', 'date'], 'amount_minor' => ['nullable', 'integer'], 'line_id' => ['nullable', 'integer'], 'transaction_id' => ['nullable', 'integer']]);
        if (! is_array($validated) || ! is_string($validated['action'] ?? null)) {
            abort(422);
        }
        $data = $this->stringKeyed($validated);
        $this->treasury->mutate($validated['action'], $company, $user, $data);

        return back()->with('status', 'Treasury control đã cập nhật.');
    }

    public function periodClose(Request $request): View
    {
        [$user, $company] = $this->scope($request);

        return view('admin.finance.period-close', ['companyId' => $company, 'data' => $this->periods->read($company), 'user' => $user]);
    }

    public function periodMutate(Request $request): RedirectResponse
    {
        [$user, $company] = $this->scope($request);
        $validated = $request->validate(['company_id' => ['required', 'integer'], 'operation' => ['required', 'in:request,decide'], 'period_id' => ['nullable', 'integer'], 'request_id' => ['nullable', 'integer'], 'action' => ['nullable', 'in:soft_close,close,reopen'], 'decision' => ['nullable', 'in:approve,reject'], 'reason' => ['nullable', 'string', 'max:2000']]);
        if (! is_array($validated) || ! is_string($validated['operation'] ?? null)) {
            abort(422);
        }
        $data = $this->stringKeyed($validated);
        if ($data['operation'] === 'request') {
            Gate::forUser($user)->authorize('accounting.period.close.request');
            $this->periods->request($company, $this->integer($data, 'period_id'), $this->text($data, 'action'), $user, $this->nullableText($data, 'reason'));
        } else {
            Gate::forUser($user)->authorize('accounting.period.close.approve');
            $this->periods->decide($company, $this->integer($data, 'request_id'), $this->text($data, 'decision') === 'approve', $user, $this->nullableText($data, 'reason'));
        }

        return back()->with('status', 'Accounting Period governance đã cập nhật.');
    }

    public function approvals(Request $request): View
    {
        [$user, $company] = $this->scope($request);

        return view('admin.finance.approvals', ['companyId' => $company, 'data' => $this->approvals->read($company, $user->id)]);
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
    private function stringKeyed(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                abort(422);
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /** @param array<string,mixed> $data */
    private function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : throw new \InvalidArgumentException('Invalid finance control integer.');
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw new \InvalidArgumentException('Invalid finance control text.');
    }

    /** @param array<string,mixed> $data */
    private function nullableText(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : (is_string($value) ? $value : throw new \InvalidArgumentException('Invalid finance control optional text.'));
    }
}
