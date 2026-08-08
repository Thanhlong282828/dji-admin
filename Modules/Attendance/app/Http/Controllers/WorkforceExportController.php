<?php

namespace Modules\Attendance\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Attendance\Application\Queries\GetWorkforceCsvExportQuery;
use Modules\Attendance\Presentation\Http\Requests\WorkforceExportRequest;
use Modules\Core\Application\Buses\QueryBusInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class WorkforceExportController
{
    public function __construct(private QueryBusInterface $queries) {}

    public function __invoke(WorkforceExportRequest $request, string $type): StreamedResponse
    {
        $permission = match ($type) {
            'attendance' => 'attendance.export', 'leave','leave-balances' => 'leave.export', 'overtime' => 'overtime.export', 'payroll-input' => 'payroll-input.export', 'workforce' => 'work-calendar.view', default => abort(404)
        };
        $company = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::forUser($user)->authorize('workforce.company', [$permission, $company]);
        $ability = match ($type) {
            'attendance' => 'attendance.export-records',
            'leave' => 'leave-request.export-records',
            'leave-balances' => 'leave-balance.export-records',
            'overtime' => 'overtime-request.export-records',
            'payroll-input' => 'payroll-input.export-records',
            default => 'work-calendar.export-records',
        };
        Gate::forUser($user)->authorize($ability, [$company]);
        $response = $this->queries->ask(new GetWorkforceCsvExportQuery(
            $type,
            $company,
            $user->id,
            $request->filled('employee_id') ? $request->integer('employee_id') : null,
            $request->filled('shift_id') ? $request->integer('shift_id') : null,
            $request->filled('policy_id') ? $request->integer('policy_id') : null,
            $request->filled('snapshot_id') ? $request->integer('snapshot_id') : null,
            $request->filled('start_date') ? $request->string('start_date')->toString() : null,
            $request->filled('end_date') ? $request->string('end_date')->toString() : null,
            $request->filled('status') ? $request->string('status')->toString() : null,
            $request->filled('approval_status') ? $request->string('approval_status')->toString() : null,
            $request->filled('calculation_status') ? $request->string('calculation_status')->toString() : null,
            $request->filled('locked') ? $request->boolean('locked') : null,
        ));
        abort_unless($response instanceof StreamedResponse, 500);

        return $response;
    }
}
