<?php

namespace Modules\Performance\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Performance\Application\DTOs\PlanningExecutionFilter;
use Modules\Performance\Domain\Services\PlanningExecutionService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PlanningExecutionController
{
    public function __construct(private PlanningExecutionService $execution, private TenantAccessService $tenant) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'analytics.view');
        $filter = PlanningExecutionFilter::fromRequest($request, $companyId);

        return view('performance::planning.execution', ['execution' => $this->execution->build($filter, $request->user()?->can('payroll.assignment.view') === true), 'filter' => $filter, 'companyId' => $companyId]);
    }

    public function export(Request $request): StreamedResponse
    {
        $companyId = $this->company($request, 'analytics.export');
        $filter = PlanningExecutionFilter::fromRequest($request, $companyId);
        $execution = $this->execution->build($filter, $request->user()?->can('payroll.assignment.view') === true);

        return response()->streamDownload(static function () use ($execution): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['Planning Execution', $execution->period, 'Request-scoped reforecast']);
            fputcsv($handle, ['Position', 'Unit', 'Target HC', 'Actual HC', 'HC Variance', 'Target Hires', 'Actual Hires', 'Remaining Hires', 'HC Status', 'Hiring Status', 'Payroll Target', 'Payroll Forecast']);
            foreach ($execution->positions as $row) {
                fputcsv($handle, [self::safe((string) $row['position_title']), self::safe((string) $row['unit_name']), $row['target_headcount'], $row['actual_headcount'], $row['headcount_variance'], $row['target_hires'], $row['actual_hires'], $row['remaining_hires'], $row['headcount_status'], $row['hiring_status'], $execution->salaryAllowed ? $row['budget_minor'] : 'MASKED', $execution->salaryAllowed ? $row['forecast_minor'] : 'MASKED']);
            }
            fclose($handle);
        }, 'planning-execution-'.$execution->period.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function company(Request $request, string $permission): int
    {
        $companyId = $request->integer('company_id');
        abort_if($companyId < 1, 422);
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless($this->tenant->canAccess($user, $companyId), 404);
        abort_unless($user->can($permission), 403);

        return $companyId;
    }

    private static function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
