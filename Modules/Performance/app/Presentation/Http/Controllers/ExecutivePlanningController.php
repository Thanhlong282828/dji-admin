<?php

namespace Modules\Performance\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Performance\Application\DTOs\PlanningFilter;
use Modules\Performance\Domain\Services\ExecutivePlanningService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ExecutivePlanningController
{
    public function __construct(private ExecutivePlanningService $planning, private TenantAccessService $tenant) {}

    public function index(Request $request): View
    {
        $company = $this->company($request, 'analytics.view');
        $filter = PlanningFilter::fromRequest($request, $company);

        return view('performance::planning.executive', [
            'planning' => $this->planning->build($filter, $request->user()?->can('payroll.assignment.view') === true),
            'filter' => $filter,
            'companyId' => $company,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $company = $this->company($request, 'analytics.export');
        $filter = PlanningFilter::fromRequest($request, $company);
        $planning = $this->planning->build($filter, $request->user()?->can('payroll.assignment.view') === true);

        return response()->streamDownload(static function () use ($planning): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['Executive Planning', $planning->period, 'Computed read-only baseline']);
            fputcsv($handle, ['Position', 'Unit', 'Level', 'Approved HC', 'Current HC', 'Forecast HC', 'Vacancy', 'Budget minor', 'Forecast minor', 'Variance minor']);
            foreach ($planning->positions as $row) {
                fputcsv($handle, [self::safe((string) $row['position_title']), self::safe((string) $row['unit_name']), $row['career_level'], $row['approved_headcount'], $row['current_headcount'], $row['forecast_headcount'], $row['vacancy'], $planning->salaryAllowed ? $row['budget_minor'] : 'MASKED', $planning->salaryAllowed ? $row['forecast_minor'] : 'MASKED', $planning->salaryAllowed ? $row['variance_minor'] : 'MASKED']);
            }
            fclose($handle);
        }, 'executive-planning-'.$planning->period.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function company(Request $request, string $permission): int
    {
        $company = $request->integer('company_id');
        abort_if($company < 1, 422);
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless($this->tenant->canAccess($user, $company), 404);
        abort_unless($user->can($permission), 403);

        return $company;
    }

    private static function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
