<?php

namespace Modules\Document\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class EnterpriseDocumentService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string,mixed> $data */
    public function create(array $data, UploadedFile $file, int $actorId): int
    {
        return $this->db->transaction(function () use ($data, $file, $actorId): int {
            $companyId = $this->integer($data['company_id'] ?? null);
            $subjectType = $this->string($data['subject_type'] ?? null);
            $this->validateSubject($companyId, $subjectType, $this->integer($data['subject_id'] ?? null));
            $id = (int) $this->db->table('enterprise_documents')->insertGetId(['company_id' => $companyId, 'document_type' => $data['document_type'], 'document_number' => $data['document_number'] ?? null, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'classification' => $data['classification'], 'owner_module' => $data['owner_module'], 'subject_type' => $data['subject_type'], 'subject_id' => $data['subject_id'], 'status' => 'ACTIVE', 'retention_until' => $data['retention_until'] ?? null, 'created_by' => $actorId, 'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            $this->upload($companyId, $id, $file, $actorId);
            activity('enterprise-document')->causedBy($actorId)->event('created')->withProperties(['company_id' => $companyId, 'document_id' => $id, 'owner_module' => $data['owner_module'], 'subject_type' => $data['subject_type'], 'subject_id' => $data['subject_id']])->log('Enterprise document created');

            return $id;
        });
    }

    public function upload(int $companyId, int $documentId, UploadedFile $file, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $documentId, $file, $actorId): int {
            $document = $this->db->table('enterprise_documents')->where(['id' => $documentId, 'company_id' => $companyId])->lockForUpdate()->first();
            abort_unless($document !== null, 404);
            abort_if(($document->status ?? null) === 'ARCHIVED', 409, 'Tài liệu đã lưu trữ.');
            $latestVersion = $this->db->table('enterprise_document_versions')->where('document_id', $documentId)->max('version_number');
            $version = $this->integer($latestVersion) + 1;
            $realPath = $file->getRealPath();
            abort_unless(is_string($realPath), 422);
            $sha = hash_file('sha256', $realPath);
            if (! is_string($sha)) {
                throw new RuntimeException('Không thể tính SHA-256.');
            }
            $name = Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin');
            $path = $file->storeAs("enterprise-documents/{$companyId}/{$documentId}", $name, 'local');
            abort_unless(is_string($path), 500);
            $id = (int) $this->db->table('enterprise_document_versions')->insertGetId(['document_id' => $documentId, 'version_number' => $version, 'storage_driver' => 'local_private', 'storage_path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'sha256' => $sha, 'uploaded_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            $this->db->table('enterprise_documents')->where('id', $documentId)->update(['current_version_id' => $id, 'updated_by' => $actorId, 'updated_at' => now()]);
            activity('enterprise-document')->causedBy($actorId)->event('version_uploaded')->withProperties(['company_id' => $companyId, 'document_id' => $documentId, 'version_id' => $id, 'version' => $version, 'sha256' => $sha])->log('Enterprise document version uploaded');

            return $id;
        });
    }

    public function archive(int $companyId, int $documentId, int $actorId): void
    {
        $updated = $this->db->table('enterprise_documents')->where(['id' => $documentId, 'company_id' => $companyId, 'status' => 'ACTIVE'])->update(['status' => 'ARCHIVED', 'updated_by' => $actorId, 'updated_at' => now()]);
        abort_if($updated === 0, 404);
        activity('enterprise-document')->causedBy($actorId)->event('archived')->withProperties(['company_id' => $companyId, 'document_id' => $documentId])->log('Enterprise document archived');
    }

    /** @return array<string,mixed> */
    public function read(int $companyId, ?int $documentId = null): array
    {
        $query = $this->db->table('enterprise_documents as d')->leftJoin('enterprise_document_versions as v', 'v.id', '=', 'd.current_version_id')->where('d.company_id', $companyId)->select('d.*', 'v.version_number', 'v.original_name', 'v.mime_type', 'v.size_bytes', 'v.sha256');
        if ($documentId !== null) {
            $document = (clone $query)->where('d.id', $documentId)->first();
            abort_unless($document !== null, 404);

            return ['document' => $document, 'versions' => $this->db->table('enterprise_document_versions')->where('document_id', $documentId)->orderByDesc('version_number')->get(['id', 'version_number', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by', 'created_at'])];
        }

        return ['documents' => $query->orderByDesc('d.updated_at')->paginate(30)];
    }

    /** @return array{path:string,name:string,mime:string} */
    public function downloadPath(int $companyId, int $versionId): array
    {
        $row = $this->db->table('enterprise_document_versions as v')->join('enterprise_documents as d', 'd.id', '=', 'v.document_id')->where(['d.company_id' => $companyId, 'v.id' => $versionId])->first(['v.storage_path', 'v.original_name', 'v.mime_type']);
        abort_unless($row !== null, 404);
        $path = is_string($row->storage_path ?? null) ? $row->storage_path : '';
        abort_unless(Storage::disk('local')->exists($path), 404);

        return ['path' => Storage::disk('local')->path($path), 'name' => is_string($row->original_name ?? null) ? $row->original_name : 'document', 'mime' => is_string($row->mime_type ?? null) ? $row->mime_type : 'application/octet-stream'];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateSubject(int $companyId, string $type, int $id): void
    {
        $tables = ['EMPLOYEE' => 'employees', 'EMPLOYEE_CONTRACT' => 'employment_contracts', 'PURCHASE_ORDER' => 'purchase_orders', 'GOODS_RECEIPT' => 'procurement_goods_receipts', 'SERVICE_ACCEPTANCE' => 'service_acceptances', 'VENDOR_INVOICE' => 'vendor_bills', 'EXPENSE' => 'finance_expenses', 'ASSET' => 'finance_assets', 'SALES_ORDER' => 'sales_orders', 'CUSTOMER_INVOICE' => 'customer_invoices', 'ACCOUNTING_ADJUSTMENT' => 'accounting_adjustment_requests', 'PROJECT' => 'projects', 'PROJECT_MILESTONE' => 'project_milestones', 'PROJECT_TASK' => 'project_tasks', 'MAINTENANCE_REQUEST' => 'maintenance_requests', 'MAINTENANCE_WORK_ORDER' => 'maintenance_work_orders', 'MAINTENANCE_PLAN' => 'preventive_maintenance_plans'];
        $table = $tables[$type] ?? null;
        abort_unless(is_string($table), 422, 'Loại liên kết không được hỗ trợ.');
        if ($type === 'EMPLOYEE_CONTRACT') {
            $exists = $this->db->table('employment_contracts as c')->join('employees as e', 'e.id', '=', 'c.employee_id')->where(['c.id' => $id, 'e.company_id' => $companyId])->exists();
        } elseif (in_array($type, ['PROJECT_MILESTONE', 'PROJECT_TASK'], true)) {
            $exists = $this->db->table($table.' as subject')->join('projects as p', 'p.id', '=', 'subject.project_id')->where(['subject.id' => $id, 'p.company_id' => $companyId])->exists();
        } else {
            $exists = $this->db->table($table)->where(['id' => $id, 'company_id' => $companyId])->exists();
        }
        abort_unless($exists, 404);
    }
}
