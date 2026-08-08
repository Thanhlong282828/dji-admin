<?php

namespace Modules\Payroll\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Payroll\Application\Queries\GetPayrollComponentBreakdownCsvExport\GetPayrollComponentBreakdownCsvExportQuery;
use Modules\Payroll\Application\Queries\GetPayrollRunCsvExport\GetPayrollRunCsvExportQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PayrollExportController
{
    public function __construct(private QueryBusInterface $queries) {}

    public function items(Request $request, int $run): StreamedResponse
    {
        $companyId = $this->authorize($request, $run);
        $response = $this->queries->ask(new GetPayrollRunCsvExportQuery($companyId, $run, $this->filters($request, ['employee_id', 'organization_unit', 'job_position', 'calculation_status', 'validation_status', 'currency_code'])));
        abort_unless($response instanceof StreamedResponse, 500);

        return $response;
    }

    public function components(Request $request, int $run): StreamedResponse
    {
        $companyId = $this->authorize($request, $run);
        $response = $this->queries->ask(new GetPayrollComponentBreakdownCsvExportQuery($companyId, $run, $this->filters($request, ['employee_id', 'salary_component_id', 'category', 'calculation_type', 'validation_status'])));
        abort_unless($response instanceof StreamedResponse, 500);

        return $response;
    }

    private function authorize(Request $request, int $run): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $companyId = $request->integer('company_id');
        Gate::forUser($user)->authorize('workforce.company', ['payroll.export', $companyId]);
        Gate::authorize('payroll-run.export-record', [$companyId, $run]);

        return $companyId;
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, int|string|null>
     */
    private function filters(Request $request, array $allowed): array
    {
        $filters = [];
        foreach ($allowed as $key) {
            $value = $request->query($key);
            if (is_string($value) && $value !== '') {
                $filters[$key] = in_array($key, ['employee_id', 'salary_component_id'], true) && ctype_digit($value) ? (int) $value : $value;
            }
        }

        return $filters;
    }
}
