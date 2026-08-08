<?php

namespace Modules\Organization\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Organization\Application\DTOs\OrganizationExportData;
use Modules\Organization\Application\Queries\GetOrganizationExport\GetOrganizationExportQuery;
use Modules\Organization\Domain\Enums\OrganizationExportType;
use Modules\Organization\Domain\Services\SpreadsheetFormulaSanitizer;
use Modules\Organization\Domain\Services\TenantAccessService;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OrganizationExportController extends Controller
{
    public function __construct(
        private QueryBusInterface $queries,
        private TenantAccessService $tenants,
        private SpreadsheetFormulaSanitizer $sanitizer,
    ) {}

    public function costCenters(Request $request): StreamedResponse
    {
        return $this->download($request, OrganizationExportType::COST_CENTERS, 'cost-center.export');
    }

    public function organizationUnits(Request $request): StreamedResponse
    {
        return $this->download($request, OrganizationExportType::ORGANIZATION_UNITS, 'organization-unit.export');
    }

    public function organizationTree(Request $request): StreamedResponse
    {
        return $this->download($request, OrganizationExportType::ORGANIZATION_TREE, 'organization-unit.export');
    }

    public function jobPositions(Request $request): StreamedResponse
    {
        return $this->download($request, OrganizationExportType::JOB_POSITIONS, 'job-position.export');
    }

    public function headcount(Request $request): StreamedResponse
    {
        return $this->download($request, OrganizationExportType::HEADCOUNT, 'organization-unit.export');
    }

    private function download(Request $request, OrganizationExportType $type, string $permission): StreamedResponse
    {
        $companyId = $request->integer('company_id');
        abort_if($companyId < 1, 422, 'Vui lòng chọn công ty.');
        $user = $request->user();
        abort_unless($user instanceof User && $user->can($permission) && $this->tenants->canAccess($user, $companyId), 403);
        $filters = $this->normalize($request->only(['search', 'currency', 'has_budget', 'type', 'organization_unit_id', 'job_position_id', 'job_grade', 'effective_date', 'critical_only']));
        $result = $this->queries->ask(new GetOrganizationExportQuery($companyId, $type, $filters));
        abort_unless($result instanceof OrganizationExportData, 500);

        return response()->streamDownload(function () use ($result, $user, $companyId, $filters, $type): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Cannot open CSV output stream.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map($this->sanitizer->sanitize(...), $result->headings));
            $count = 0;
            foreach ($result->rows as $row) {
                fputcsv($output, array_map($this->sanitizer->sanitize(...), $row));
                $count++;
            }
            fclose($output);
            activity('organization-export')->causedBy($user)->withProperties([
                'company_id' => $companyId,
                'filters' => $filters,
                'format' => 'csv',
                'row_count' => $count,
            ])->event($type->value.'.exported')->log(str_replace('-', ' ', ucfirst($type->value)).' exported');
        }, $result->filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
