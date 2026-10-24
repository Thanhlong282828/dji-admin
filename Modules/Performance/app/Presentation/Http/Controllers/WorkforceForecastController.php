<?php

namespace Modules\Performance\Presentation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Performance\Application\DTOs\WorkforceScenarioLine;
use Modules\Performance\Domain\Services\WorkforceForecastService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class WorkforceForecastController
{
    public function __construct(private WorkforceForecastService $forecasts, private TenantAccessService $tenant) {}

    public function index(Request $request): View
    {
        $this->allow($request, $request->integer('company_id'), 'analytics.view');
        [$companyId, $month, $lines, $unitId] = $this->input($request);
        $salaryAllowed = $request->user()?->can('payroll.assignment.view') === true;

        return view('performance::forecasting.dashboard', [
            'forecast' => $this->forecasts->build($companyId, $month, $lines, $salaryAllowed, $unitId),
            'companyId' => $companyId,
            'scenario' => $request->input('scenario', []),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->allow($request, $request->integer('company_id'), 'analytics.export');
        [$companyId, $month, $lines, $unitId] = $this->input($request);
        $salaryAllowed = $request->user()?->can('payroll.assignment.view') === true;
        $forecast = $this->forecasts->build($companyId, $month, $lines, $salaryAllowed, $unitId);

        return response()->streamDownload(static function () use ($forecast): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['Enterprise Workforce Forecast', $forecast->forecastMonth]);
            fputcsv($handle, ['Type', 'Metric', 'Value']);
            foreach ($forecast->headcount as $metric => $value) {
                fputcsv($handle, ['forecast', self::safe((string) $metric), (string) $value]);
            }
            foreach ($forecast->scenarioComparison as $scenarioName => $scenario) {
                foreach ($scenario as $metric => $value) {
                    if ($forecast->salaryAllowed || $metric === 'headcount') {
                        fputcsv($handle, ['scenario_'.$scenarioName.'_delta', self::safe((string) $metric), (string) $value]);
                    }
                }
            }
            foreach ($forecast->assumptions as $assumption) {
                fputcsv($handle, ['assumption', self::safe($assumption), '']);
            }
            fclose($handle);
        }, 'workforce-forecast-'.$forecast->forecastMonth.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{int,CarbonImmutable,list<WorkforceScenarioLine>,int|null} */
    private function input(Request $request): array
    {
        $request->validate([
            'company_id' => ['required', 'integer', 'min:1'],
            'forecast_month' => ['nullable', 'date_format:Y-m'],
            'organization_unit_id' => ['nullable', 'integer', 'min:1'],
            'scenario' => ['nullable', 'array', 'max:50'],
            'scenario.*.scenario_name' => ['nullable', Rule::in(['A', 'B'])],
            'scenario.*.action' => ['required', Rule::in(['add', 'remove', 'promote'])],
            'scenario.*.position_id' => ['required', 'integer', 'min:1'],
            'scenario.*.from_position_id' => ['nullable', 'integer', 'min:1'],
            'scenario.*.quantity' => ['required', 'integer', 'between:1,100'],
            'scenario.*.effective_month' => ['required', 'date_format:Y-m'],
        ]);
        $companyId = $request->integer('company_id');
        $monthValue = $request->string('forecast_month')->toString();
        $month = CarbonImmutable::parse(($monthValue !== '' ? $monthValue : now()->format('Y-m')).'-01')->startOfMonth();
        $unitId = $request->filled('organization_unit_id') ? $request->integer('organization_unit_id') : null;
        if ($unitId !== null && ! DB::table('organization_units')->where('company_id', $companyId)->where('id', $unitId)->exists()) {
            abort(404);
        }
        $scenarioInput = $request->input('scenario', []);
        $rawLines = is_array($scenarioInput) ? $scenarioInput : [];
        $lines = [];
        foreach ($rawLines as $rawLine) {
            if (! is_array($rawLine)) {
                continue;
            }
            $action = $rawLine['action'] ?? null;
            $scenarioName = $rawLine['scenario_name'] ?? 'A';
            $positionId = $rawLine['position_id'] ?? null;
            $fromPositionId = $rawLine['from_position_id'] ?? null;
            $quantity = $rawLine['quantity'] ?? null;
            $effectiveMonth = $rawLine['effective_month'] ?? null;
            if (! is_string($scenarioName) || ! is_string($action) || ! is_numeric($positionId) || ! is_numeric($quantity) || ! is_string($effectiveMonth)) {
                continue;
            }
            $lines[] = WorkforceScenarioLine::fromArray(['scenario_name' => $scenarioName, 'action' => $action, 'position_id' => (int) $positionId, 'from_position_id' => is_numeric($fromPositionId) ? (int) $fromPositionId : null, 'quantity' => (int) $quantity, 'effective_month' => $effectiveMonth]);
        }
        $positionIds = [];
        foreach ($lines as $line) {
            $positionIds[] = $line->positionId;
            if ($line->fromPositionId !== null) {
                $positionIds[] = $line->fromPositionId;
            }
        }
        $positionIds = array_values(array_unique($positionIds));
        if ($positionIds !== [] && DB::table('job_positions')->where('company_id', $companyId)->whereIn('id', $positionIds)->count() !== count($positionIds)) {
            abort(404);
        }

        return [$companyId, $month, $lines, $unitId];
    }

    private function allow(Request $request, int $companyId, string $permission): void
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless($this->tenant->canAccess($user, $companyId), 404);
        abort_unless($user->can($permission), 403);
    }

    private static function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
