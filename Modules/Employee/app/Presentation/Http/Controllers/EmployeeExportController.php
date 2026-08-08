<?php

namespace Modules\Employee\Presentation\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Core\Application\Buses\QueryBusInterface;
use Modules\Employee\Application\DTOs\EmployeeExportData;
use Modules\Employee\Application\Queries\GetEmployeeExport\GetEmployeeExportQuery;
use Modules\Organization\Domain\Services\SpreadsheetFormulaSanitizer;
use Modules\Organization\Domain\Services\TenantAccessService;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class EmployeeExportController
{
    public function __construct(private QueryBusInterface $queries, private TenantAccessService $tenant, private SpreadsheetFormulaSanitizer $sanitizer) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $companyId = $request->integer('company_id');
        $user = $request->user();
        abort_unless($companyId > 0 && $user instanceof User && $user->can('employee.export') && $this->tenant->canAccess($user, $companyId), 403);
        $filters = $this->normalize($request->only(['search', 'employment_status', 'employment_type', 'has_user']));
        $result = $this->queries->ask(new GetEmployeeExportQuery($companyId, $filters));
        abort_unless($result instanceof EmployeeExportData, 500);

        return response()->streamDownload(function () use ($result, $user, $companyId, $filters): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new RuntimeException('Cannot open employee export stream.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_map($this->sanitizer->sanitize(...), $result->headings));
            $count = 0;
            foreach ($result->rows as $row) {
                fputcsv($stream, array_map($this->sanitizer->sanitize(...), $row));
                $count++;
            }
            fclose($stream);
            activity('employee-export')->causedBy($user)->event('employee.exported')->withProperties(['company_id' => $companyId, 'filters' => $filters, 'format' => 'csv', 'row_count' => $count])->log('Employees exported');
        }, $result->filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
