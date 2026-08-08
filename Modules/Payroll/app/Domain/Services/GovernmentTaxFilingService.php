<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\DTOs\GovernmentFilingDownloadData;
use Modules\Payroll\Application\DTOs\GovernmentFilingResult;
use Modules\Payroll\Domain\Adapters\GovernmentDeclarationGeneratorInterface;
use Throwable;

final readonly class GovernmentTaxFilingService
{
    public function __construct(private ConnectionInterface $db, private GovernmentDeclarationGeneratorInterface $generator) {}

    public function create(int $companyId, int $reconciliationId, string $filingType, int $actorId, string $idempotencyKey): GovernmentFilingResult
    {
        return $this->db->transaction(function () use ($companyId, $reconciliationId, $filingType, $actorId, $idempotencyKey): GovernmentFilingResult {
            $annual = $this->db->table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('id', $reconciliationId)->lockForUpdate()->first();
            abort_unless(is_object($annual), 404);
            $existing = $this->db->table('government_tax_filings')->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if (is_object($existing)) {
                return $this->result($existing);
            }
            if ($this->string($annual, 'status') !== 'finalized' || strlen($this->string($annual, 'source_checksum')) !== 64) {
                throw ValidationException::withMessages(['reconciliation_id' => 'Annual Tax Reconciliation must be finalized with a verified source checksum.']);
            }
            $activeKey = $companyId.':'.$reconciliationId.':'.$filingType;
            $active = $this->db->table('government_tax_filings')->where('active_key', $activeKey)->lockForUpdate()->first();
            if (is_object($active)) {
                return $this->result($active);
            }
            $version = 1 + $this->numeric($this->db->table('government_tax_filings')->where('company_id', $companyId)->where('annual_tax_reconciliation_id', $reconciliationId)->where('filing_type', $filingType)->max('version'));
            $header = ['reference' => 'GTF-'.$this->integer($annual, 'tax_year').'-'.$reconciliationId.'-V'.$version, 'tax_year' => $this->integer($annual, 'tax_year'), 'jurisdiction' => $this->string($annual, 'jurisdiction'), 'filing_type' => $filingType, 'version' => $version, 'source_checksum' => $this->string($annual, 'source_checksum'), 'company_snapshot' => $this->jsonValue($annual, 'company_tax_snapshot')];
            $id = (int) $this->db->table('government_tax_filings')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $companyId, 'annual_tax_reconciliation_id' => $reconciliationId, 'tax_year' => $header['tax_year'], 'jurisdiction' => $header['jurisdiction'], 'filing_type' => $filingType, 'version' => $version, 'status' => 'draft', 'active_key' => $activeKey, 'idempotency_key' => $idempotencyKey, 'source_checksum' => $header['source_checksum'], 'header_snapshot' => $this->json($header), 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            $income = $this->pick($annual, ['gross_income_minor', 'taxable_income_minor', 'non_taxable_income_minor']);
            $deductions = $this->pick($annual, ['employee_insurance_deduction_minor', 'personal_deduction_minor', 'dependent_deduction_minor', 'other_deduction_minor']);
            $withheld = $this->pick($annual, ['pit_withheld_minor']);
            $result = $this->pick($annual, ['result_type', 'final_tax_liability_minor', 'additional_tax_payable_minor', 'tax_refund_minor', 'currency', 'calculation_version']);
            $identity = $this->jsonValue($annual, 'taxpayer_snapshot');
            $itemPayload = ['employee_identity_snapshot' => $identity, 'income_snapshot' => $income, 'deduction_snapshot' => $deductions, 'tax_withheld_snapshot' => $withheld, 'annual_result_snapshot' => $result];
            $this->db->table('government_tax_filing_items')->insert(['company_id' => $companyId, 'government_tax_filing_id' => $id, 'annual_tax_reconciliation_id' => $reconciliationId, 'line_number' => 1, ...$this->jsonColumns($itemPayload), 'item_checksum' => hash('sha256', $this->json($itemPayload)), 'created_at' => now(), 'updated_at' => now()]);
            $this->log($companyId, $id, $actorId, 'created', null, 'draft');

            return new GovernmentFilingResult($id, 'draft', $version);
        }, 5);
    }

    public function generate(int $companyId, int $filingId, int $actorId): GovernmentFilingResult
    {
        return $this->db->transaction(function () use ($companyId, $filingId, $actorId): GovernmentFilingResult {
            $filing = $this->filing($companyId, $filingId, true);
            if ($this->string($filing, 'status') === 'generated') {
                return $this->result($filing);
            }
            $this->expect($filing, ['draft']);
            $document = $this->db->table('government_tax_filing_documents')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->where('document_version', $this->integer($filing, 'version'))->first();
            if (! is_object($document)) {
                $items = [];
                foreach ($this->db->table('government_tax_filing_items')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->orderBy('line_number')->get() as $row) {
                    $items[] = $this->rowWithDecodedSnapshots($row);
                }
                $header = $this->jsonValue($filing, 'header_snapshot');
                $xml = $this->generator->generate($header, $items);
                $checksum = hash('sha256', $xml);
                $uuid = (string) Str::uuid();
                $path = 'government-filings/'.$companyId.'/'.$uuid.'/v'.$this->integer($filing, 'version').'.xml';
                Storage::disk('local')->put($path, $xml);
                $this->db->table('government_tax_filing_documents')->insert(['uuid' => $uuid, 'company_id' => $companyId, 'government_tax_filing_id' => $filingId, 'document_version' => $this->integer($filing, 'version'), 'storage_disk' => 'local', 'storage_path' => $path, 'file_name' => 'government-tax-filing-'.$uuid.'.xml', 'mime_type' => 'application/xml', 'file_size' => strlen($xml), 'checksum' => $checksum, 'source_checksum' => $this->string($filing, 'source_checksum'), 'generated_by' => $actorId, 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $this->db->table('government_tax_filings')->where('id', $filingId)->update(['payload_checksum' => $checksum, 'status' => 'generated', 'generated_by' => $actorId, 'generated_at' => now(), 'updated_at' => now()]);
                $this->log($companyId, $filingId, $actorId, 'generated', 'draft', 'generated');
            }

            return $this->result($this->filing($companyId, $filingId));
        }, 5);
    }

    public function validate(int $companyId, int $filingId, int $actorId): GovernmentFilingResult
    {
        try {
            return $this->transition($companyId, $filingId, $actorId, 'validate');
        } catch (ValidationException $exception) {
            if (($exception->errors()['filing'] ?? []) !== []) {
                $this->db->table('government_tax_validation_issues')->insert(['company_id' => $companyId, 'government_tax_filing_id' => $filingId, 'issue_code' => 'CHECKSUM_MISMATCH', 'severity' => 'blocking', 'field_name' => 'checksum', 'safe_message' => 'Persisted filing evidence failed integrity validation.', 'created_at' => now(), 'updated_at' => now()]);
            }
            throw $exception;
        }
    }

    public function approve(int $companyId, int $filingId, int $actorId): GovernmentFilingResult
    {
        return $this->transition($companyId, $filingId, $actorId, 'approve');
    }

    public function submit(int $companyId, int $filingId, int $actorId, string $idempotencyKey): GovernmentFilingResult
    {
        return $this->db->transaction(function () use ($companyId, $filingId, $actorId, $idempotencyKey): GovernmentFilingResult {
            $filing = $this->filing($companyId, $filingId, true);
            $existing = $this->db->table('government_tax_submission_attempts')->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->first();
            if (is_object($existing)) {
                return $this->result($filing);
            }
            $this->expect($filing, ['approved', 'rejected']);
            if ($this->integer($filing, 'approved_by') === $actorId) {
                throw ValidationException::withMessages(['actor' => 'Approver cannot submit the same filing.']);
            }
            $attempt = 1 + $this->numeric($this->db->table('government_tax_submission_attempts')->where('government_tax_filing_id', $filingId)->max('attempt_number'));
            $payload = $this->string($filing, 'payload_checksum');
            $requestHash = hash('sha256', $payload.':'.$attempt.':generic-local-submission');
            $this->db->table('government_tax_submission_attempts')->insert(['company_id' => $companyId, 'government_tax_filing_id' => $filingId, 'attempt_number' => $attempt, 'idempotency_key' => $idempotencyKey, 'payload_hash' => $payload, 'request_hash' => $requestHash, 'response_hash' => hash('sha256', 'queued:'.$requestHash), 'status' => 'submitted_locally', 'submitted_by' => $actorId, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $from = $this->string($filing, 'status');
            $this->db->table('government_tax_filings')->where('id', $filingId)->update(['status' => 'submitted', 'active_key' => null, 'submitted_by' => $actorId, 'submitted_at' => now(), 'rejected_at' => null, 'updated_at' => now()]);
            $this->log($companyId, $filingId, $actorId, $from === 'rejected' ? 'retried' : 'submitted', $from, 'submitted');

            return $this->result($this->filing($companyId, $filingId));
        }, 5);
    }

    public function reject(int $companyId, int $filingId, int $actorId, string $reason): GovernmentFilingResult
    {
        return $this->db->transaction(function () use ($companyId, $filingId, $actorId, $reason): GovernmentFilingResult {
            $filing = $this->filing($companyId, $filingId, true);
            $this->expect($filing, ['submitted']);
            $this->db->table('government_tax_filings')->where('id', $filingId)->update(['status' => 'rejected', 'active_key' => $companyId.':'.$this->integer($filing, 'annual_tax_reconciliation_id').':'.$this->string($filing, 'filing_type'), 'rejected_at' => now(), 'updated_at' => now()]);
            $this->log($companyId, $filingId, $actorId, 'rejected', 'submitted', 'rejected', mb_substr($reason, 0, 500));

            return $this->result($this->filing($companyId, $filingId));
        }, 5);
    }

    public function download(int $companyId, int $filingId, int $documentId, int $actorId): GovernmentFilingDownloadData
    {
        try {
            $filing = $this->filing($companyId, $filingId);
            $document = $this->db->table('government_tax_filing_documents')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->where('id', $documentId)->first();
            abort_unless(is_object($document), 404);
            $path = $this->string($document, 'storage_path');
            $bytes = Storage::disk('local')->get($path);
            if (! is_string($bytes)) {
                throw ValidationException::withMessages(['document' => 'Government filing document is unavailable.']);
            }
            if ($this->string($document, 'storage_disk') !== 'local' || $this->string($document, 'mime_type') !== 'application/xml' || strlen($bytes) !== $this->integer($document, 'file_size') || ! hash_equals($this->string($document, 'checksum'), hash('sha256', $bytes)) || ! hash_equals($this->string($filing, 'payload_checksum'), hash('sha256', $bytes)) || ! str_starts_with($bytes, '<?xml')) {
                throw ValidationException::withMessages(['document' => 'Government filing document integrity verification failed.']);
            }
            $this->access($companyId, $filingId, $documentId, $actorId, 'success');

            return new GovernmentFilingDownloadData($bytes, $this->string($document, 'file_name'), 'application/xml');
        } catch (Throwable $exception) {
            $this->access($companyId, $filingId, $documentId, $actorId, 'blocked');
            throw $exception;
        }
    }

    private function transition(int $companyId, int $filingId, int $actorId, string $action): GovernmentFilingResult
    {
        return $this->db->transaction(function () use ($companyId, $filingId, $actorId, $action): GovernmentFilingResult {
            $filing = $this->filing($companyId, $filingId, true);
            $from = $this->string($filing, 'status');
            [$expected, $to, $actorColumn, $timeColumn] = match ($action) {
                'validate' => [['generated'], 'validated', 'validated_by', 'validated_at'],
                'approve' => [['validated'], 'approved', 'approved_by', 'approved_at'],
                default => throw new \LogicException('Unsupported Government Filing transition.'),
            };
            $this->expect($filing, $expected);
            if ($action === 'validate') {
                $this->verifyIntegrity($companyId, $filingId, $filing);
            }
            if ($action === 'approve' && $this->integer($filing, 'created_by') === $actorId) {
                throw ValidationException::withMessages(['actor' => 'Creator cannot approve the same filing.']);
            }
            $this->db->table('government_tax_filings')->where('id', $filingId)->update(['status' => $to, $actorColumn => $actorId, $timeColumn => now(), 'updated_at' => now()]);
            $this->log($companyId, $filingId, $actorId, $action.'d', $from, $to);

            return $this->result($this->filing($companyId, $filingId));
        }, 5);
    }

    private function verifyIntegrity(int $companyId, int $filingId, object $filing): void
    {
        $annual = $this->db->table('payroll_annual_tax_reconciliations')->where('company_id', $companyId)->where('id', $this->integer($filing, 'annual_tax_reconciliation_id'))->first();
        $document = $this->db->table('government_tax_filing_documents')->where('company_id', $companyId)->where('government_tax_filing_id', $filingId)->first();
        if (! is_object($annual) || ! is_object($document) || ! hash_equals($this->string($filing, 'source_checksum'), $this->string($annual, 'source_checksum')) || ! hash_equals($this->string($filing, 'payload_checksum'), $this->string($document, 'checksum'))) {
            throw ValidationException::withMessages(['filing' => 'Government filing checksum validation failed.']);
        }
    }

    private function filing(int $companyId, int $filingId, bool $lock = false): object
    {
        $query = $this->db->table('government_tax_filings')->where('company_id', $companyId)->where('id', $filingId);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless(is_object($row), 404);

        return $row;
    }

    /** @param list<string> $statuses */
    private function expect(object $filing, array $statuses): void
    {
        if (! in_array($this->string($filing, 'status'), $statuses, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid Government Filing lifecycle transition.']);
        }
    }

    private function result(object $row): GovernmentFilingResult
    {
        return new GovernmentFilingResult($this->integer($row, 'id'), $this->string($row, 'status'), $this->integer($row, 'version'));
    }

    private function log(int $companyId, int $filingId, int $actorId, string $action, ?string $from, ?string $to, ?string $message = null): void
    {
        $this->db->table('government_tax_filing_logs')->insert(['company_id' => $companyId, 'government_tax_filing_id' => $filingId, 'actor_id' => $actorId, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'safe_message' => $message, 'created_at' => now()]);
    }

    private function access(int $companyId, int $filingId, int $documentId, int $actorId, string $result): void
    {
        if ($this->db->table('government_tax_filings')->where('company_id', $companyId)->where('id', $filingId)->exists()) {
            $this->db->table('government_tax_access_logs')->insert(['company_id' => $companyId, 'government_tax_filing_id' => $filingId, 'government_tax_filing_document_id' => $this->db->table('government_tax_filing_documents')->where('company_id', $companyId)->where('id', $documentId)->exists() ? $documentId : null, 'actor_id' => $actorId, 'action' => 'government_filing_document_download', 'result' => $result, 'accessed_at' => now()]);
        }
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, int|string|null>
     */
    private function pick(object $row, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $value = $row->{$field} ?? null;
            $result[$field] = is_int($value) || is_string($value) || $value === null ? $value : (is_numeric($value) ? (int) $value : null);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function jsonColumns(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            $result[$key] = $this->json($value);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function rowWithDecodedSnapshots(object $row): array
    {
        $result = [];
        foreach (get_object_vars($row) as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }
        foreach (['employee_identity_snapshot', 'income_snapshot', 'deduction_snapshot', 'tax_withheld_snapshot', 'annual_result_snapshot'] as $field) {
            $result[$field] = $this->jsonValue($row, $field);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function jsonValue(object $row, string $field): array
    {
        $value = $row->{$field} ?? null;
        $decoded = is_string($value) ? json_decode($value, true) : null;
        if (! is_array($decoded)) {
            return [];
        }
        $result = [];
        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function json(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        return is_string($encoded) ? $encoded : throw new \RuntimeException('Unable to encode Government Filing snapshot.');
    }

    private function integer(object $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function numeric(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(object $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
