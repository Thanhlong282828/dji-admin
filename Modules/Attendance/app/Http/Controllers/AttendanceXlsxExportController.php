<?php

namespace Modules\Attendance\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Excel;
use Modules\Attendance\Application\Exports\AttendanceXlsxExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class AttendanceXlsxExportController
{
    public function __construct(private Excel $excel) {}

    public function __invoke(Request $request): BinaryFileResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::forUser($user)->authorize('workforce.company', ['attendance.export', $companyId]);
        Gate::forUser($user)->authorize('attendance.export-records', [$companyId]);

        return $this->excel->download(new AttendanceXlsxExport($companyId), 'attendance-'.today()->format('Ymd').'.xlsx', Excel::XLSX);
    }
}
