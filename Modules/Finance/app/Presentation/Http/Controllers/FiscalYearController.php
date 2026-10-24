<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Company\Domain\Services\BranchAccessService;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Finance\Application\Commands\CarryForwardBalanceCommand;
use Modules\Finance\Application\Commands\CloseAccountingPeriodCommand;
use Modules\Finance\Application\Commands\CloseFiscalYearCommand;
use Modules\Finance\Application\Commands\CreateAccountingPeriodCommand;
use Modules\Finance\Application\Commands\CreateFiscalYearCommand;
use Modules\Finance\Application\Commands\OpenFiscalYearCommand;
use Modules\Finance\Application\Queries\GetFiscalYearWorkspaceQuery;

final readonly class FiscalYearController
{
    public function __construct(private BranchAccessService $access, private QueryBusInterface $queries, private CommandBusInterface $commands) {}

    public function index(Request $request): View
    {
        [$user, $companyId] = $this->scope($request);
        $data = $this->queries->ask(new GetFiscalYearWorkspaceQuery($companyId, $user));
        abort_unless(is_array($data), 500);

        return view('admin.finance.fiscal-years', ['companyId' => $companyId, 'data' => $data]);
    }

    public function mutate(Request $request): RedirectResponse
    {
        [$user, $companyId] = $this->scope($request);
        $data = $request->validate(['action' => ['required', 'in:create_year,open_year,create_period,close_period,close_year,carry_forward'], 'fiscal_year_id' => ['nullable', 'integer', 'min:1'], 'target_fiscal_year_id' => ['nullable', 'integer', 'min:1'], 'close_request_id' => ['nullable', 'integer', 'min:1'], 'period_id' => ['nullable', 'integer', 'min:1'], 'retained_earnings_account_id' => ['nullable', 'integer', 'min:1'], 'name' => ['nullable', 'string', 'max:80'], 'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date'], 'year' => ['nullable', 'integer', 'min:2000', 'max:2200'], 'month' => ['nullable', 'integer', 'min:1', 'max:12'], 'reason' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['nullable', 'uuid']]);
        abort_unless(is_array($data), 422);
        $validated = $this->stringKeyed($data);
        $action = $this->text($validated, 'action');
        $permission = $action === 'carry_forward' ? 'accounting.period.close.approve' : 'accounting.period.close.request';
        abort_unless($user->can($permission), 403);
        $command = match ($action) {
            'create_year' => new CreateFiscalYearCommand($companyId, $user, ['name' => $this->text($validated, 'name'), 'start_date' => $this->text($validated, 'start_date'), 'end_date' => $this->text($validated, 'end_date')]),
            'open_year' => new OpenFiscalYearCommand($companyId, $this->integer($validated, 'fiscal_year_id'), $user),
            'create_period' => new CreateAccountingPeriodCommand($companyId, $this->integer($validated, 'fiscal_year_id'), ['year' => $this->integer($validated, 'year'), 'month' => $this->integer($validated, 'month'), 'start_date' => $this->text($validated, 'start_date'), 'end_date' => $this->text($validated, 'end_date')]),
            'close_period' => new CloseAccountingPeriodCommand($companyId, $this->integer($validated, 'period_id'), $user, $this->text($validated, 'reason')),
            'close_year' => new CloseFiscalYearCommand($companyId, $this->integer($validated, 'fiscal_year_id'), $this->integer($validated, 'retained_earnings_account_id'), $this->text($validated, 'idempotency_key'), $user, $this->nullableText($validated, 'reason')),
            'carry_forward' => new CarryForwardBalanceCommand($companyId, $this->integer($validated, 'close_request_id'), $this->integer($validated, 'target_fiscal_year_id'), $user),
            default => abort(422),
        };
        $this->commands->dispatch($command);

        return back()->with('status', 'Fiscal Year governance đã cập nhật.');
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

    /** @param array<string,mixed> $data */
    private function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : abort(422);
    }

    /** @param array<string,mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : abort(422);
    }

    /** @param array<string,mixed> $data */
    private function nullableText(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : (is_string($value) ? $value : abort(422));
    }

    /**
     * @param  array<array-key,mixed>  $data
     * @return array<string,mixed>
     */
    private function stringKeyed(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            abort_unless(is_string($key), 422);
            $result[$key] = $value;
        }

        return $result;
    }
}
