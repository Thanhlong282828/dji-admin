<?php

namespace Modules\Performance\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Organization\Domain\Services\TenantAccessService;
use Modules\Performance\Application\DTOs\ReportingFilter;
use Modules\Performance\Domain\Services\EnterpriseReportExportService;
use Modules\Performance\Domain\Services\EnterpriseReportingService;

final readonly class EnterpriseReportController
{
    public function __construct(
        private EnterpriseReportingService $reports,
        private EnterpriseReportExportService $exports,
        private TenantAccessService $tenant,
    ) {}

    public function index(Request $request): View
    {
        $companyId = $this->company($request, 'analytics.view');
        $filter = ReportingFilter::fromRequest($request, $companyId);

        return view('performance::reports.index', ['report' => $this->reports->build($filter, $request->user()?->can('payroll.assignment.view') === true), 'filter' => $filter, 'companyId' => $companyId]);
    }

    public function export(Request $request, string $format): Response
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        $companyId = $this->company($request, 'analytics.export');
        $filter = ReportingFilter::fromRequest($request, $companyId);
        $artifact = $this->exports->generate($this->reports->build($filter, $request->user()?->can('payroll.assignment.view') === true), $format);

        return response($artifact['bytes'], 200, [
            'Content-Type' => $artifact['mime'],
            'Content-Disposition' => 'attachment; filename="enterprise-board-pack-'.$filter->period->format('Y-m').'.'.$artifact['extension'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Report-Checksum-SHA256' => $artifact['checksum'],
        ]);
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
}
