<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class EmployeeDocumentGovernanceService
{
    public function __construct(private HRDataGovernanceService $governance) {}

    public function archive(int $companyId, int $documentId, int $actorId, string $key, ?string $retentionDate): int
    {
        return $this->governance->archiveDocument($companyId, $documentId, $actorId, $key, $retentionDate);
    }

    /** @return array{id:int,employee_id:int,type:string,name:string,version:int,expires_at:string|null,archived_at:string|null,scan_status:string} */
    public function metadata(int $companyId, int $documentId): array
    {
        $row = DB::table('employee_documents as d')->join('employees as e', 'e.id', '=', 'd.employee_id')
            ->where('e.company_id', $companyId)->where('d.id', $documentId)
            ->select('d.id', 'd.employee_id', 'd.type', 'd.name', 'd.version', 'd.expires_at', 'd.archived_at', 'd.scan_status')->first();
        abort_if(! $row instanceof stdClass, 404);

        $id = $row->id ?? null;
        $employeeId = $row->employee_id ?? null;
        $type = $row->type ?? null;
        $name = $row->name ?? null;
        $version = $row->version ?? null;
        $scanStatus = $row->scan_status ?? null;
        abort_unless(is_numeric($id) && is_numeric($employeeId) && is_string($type) && is_string($name) && is_numeric($version) && is_string($scanStatus), 500, 'Invalid document metadata projection.');
        $expiresAt = $row->expires_at ?? null;
        $archivedAt = $row->archived_at ?? null;
        abort_unless($expiresAt === null || is_string($expiresAt), 500, 'Invalid document expiry projection.');
        abort_unless($archivedAt === null || is_string($archivedAt), 500, 'Invalid document archive projection.');

        return [
            'id' => (int) $id, 'employee_id' => (int) $employeeId,
            'type' => $type, 'name' => $name, 'version' => (int) $version,
            'expires_at' => $expiresAt, 'archived_at' => $archivedAt, 'scan_status' => $scanStatus,
        ];
    }
}
