<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Domain\Governance\HRDataQualityRuleRegistry;
use stdClass;
use Throwable;

final readonly class HRDataGovernanceService
{
    public function __construct(
        private HRDataQualityRuleRegistry $registry,
        private HRGovernanceAuditService $audit,
        private EmployeeDocumentService $documents,
    ) {}

    public function run(int $companyId, int $actorId, string $key, string $date): int
    {
        return DB::transaction(function () use ($companyId, $actorId, $key, $date): int {
            $existing = DB::table('hr_data_quality_runs')->where('company_id', $companyId)->where('idempotency_key', $key)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }

            $runId = (int) DB::table('hr_data_quality_runs')->insertGetId([
                'company_id' => $companyId, 'run_date' => $date, 'rule_version' => 1,
                'status' => 'running', 'issue_count' => 0, 'idempotency_key' => $key,
                'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $checksums = [];
            foreach ($this->registry->rules() as $rule) {
                foreach ($rule->evaluate($companyId) as $issue) {
                    $payload = [
                        'entity_type' => $issue['entity_type'], 'entity_id' => $issue['entity_id'],
                        'rule_code' => $rule->code(), 'severity' => $rule->severity(), 'message' => $issue['message'],
                    ];
                    $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                    DB::table('hr_data_quality_issues')->insertOrIgnore([
                        'run_id' => $runId, ...$payload, 'status' => 'open', 'created_by' => $actorId,
                        'checksum' => $checksum, 'created_at' => now(),
                    ]);
                    $checksums[] = $checksum;
                }
            }
            sort($checksums);
            DB::table('hr_data_quality_runs')->where('id', $runId)->update([
                'status' => 'completed', 'issue_count' => count($checksums),
                'checksum' => hash('sha256', implode('|', $checksums)), 'updated_at' => now(),
            ]);
            $this->audit->append($companyId, $actorId, 'quality_run', $runId, 'quality_run_completed', $key, ['issue_count' => count($checksums)]);

            return $runId;
        }, 3);
    }

    public function reviewIssue(int $companyId, int $issueId, int $actorId, string $key, string $reason): int
    {
        return $this->transitionIssue($companyId, $issueId, $actorId, $key, $reason, 'open', 'reviewed');
    }

    public function approveIssue(int $companyId, int $issueId, int $actorId, string $key, string $reason): int
    {
        return $this->transitionIssue($companyId, $issueId, $actorId, $key, $reason, 'reviewed', 'approved');
    }

    public function resolveIssue(int $companyId, int $issueId, int $actorId, string $key, string $reason): int
    {
        return $this->transitionIssue($companyId, $issueId, $actorId, $key, $reason, 'approved', 'resolved');
    }

    public function archiveDocument(int $companyId, int $documentId, int $actorId, string $key, ?string $retentionDate): int
    {
        try {
            $id = DB::transaction(function () use ($companyId, $documentId, $actorId, $retentionDate): int {
                $existing = DB::table('hr_document_governance_history')->where('document_id', $documentId)->where('status', 'archived')->value('id');
                if (is_numeric($existing)) {
                    return (int) $existing;
                }
                $this->documents->download($companyId, $documentId);
                $this->documents->archive($companyId, $documentId);
                $latestVersion = DB::table('hr_document_governance_history')->where('document_id', $documentId)->max('version');
                $version = (is_numeric($latestVersion) ? (int) $latestVersion : 0) + 1;
                $payload = ['document_id' => $documentId, 'status' => 'archived', 'version' => $version, 'retention_date' => $retentionDate];
                $id = (int) DB::table('hr_document_governance_history')->insertGetId([
                    'company_id' => $companyId, ...$payload,
                    'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                    'actor_id' => $actorId, 'created_at' => now(),
                ]);

                return $id;
            }, 3);
            $this->audit->append($companyId, $actorId, 'employee_document', $documentId, 'document_accessed', $key.'-access-success', ['result' => 'success']);
            $this->audit->append($companyId, $actorId, 'employee_document', $documentId, 'document_archived', $key, ['history_id' => $id]);

            return $id;
        } catch (Throwable $exception) {
            $this->audit->append($companyId, $actorId, 'employee_document', $documentId, 'document_accessed', $key.'-access-blocked', ['result' => 'blocked']);
            throw $exception;
        }
    }

    private function transitionIssue(int $companyId, int $issueId, int $actorId, string $key, string $reason, string $from, string $to): int
    {
        return DB::transaction(function () use ($companyId, $issueId, $actorId, $key, $reason, $from, $to): int {
            $row = DB::table('hr_data_quality_issues as i')->join('hr_data_quality_runs as r', 'r.id', '=', 'i.run_id')
                ->where('r.company_id', $companyId)->where('i.id', $issueId)->lockForUpdate()->select('i.*')->first();
            abort_if(! $row instanceof stdClass, 404);
            $status = is_string($row->status ?? null) ? $row->status : '';
            if ($status === $to || ($status === 'resolved' && $to === 'resolved')) {
                return $issueId;
            }
            if ($status !== $from) {
                throw ValidationException::withMessages(['status' => "Issue phải ở trạng thái {$from}."]);
            }
            $creator = is_numeric($row->created_by ?? null) ? (int) $row->created_by : 0;
            if ($to === 'approved' && $actorId === $creator) {
                throw ValidationException::withMessages(['actor' => 'Creator không được tự phê duyệt correction.']);
            }
            $changes = ['status' => $to];
            if ($to === 'reviewed') {
                $changes += ['reviewed_by' => $actorId, 'reviewed_at' => now()];
            } elseif ($to === 'approved') {
                $changes += ['approved_by' => $actorId, 'approved_at' => now()];
            } else {
                $changes += ['resolved_by' => $actorId, 'resolved_at' => now(), 'resolution_key' => $key,
                    'resolution_reason' => $reason, 'resolution_checksum' => hash('sha256', $issueId.'|'.$reason.'|'.$actorId)];
            }
            DB::table('hr_data_quality_issues')->where('id', $issueId)->update($changes);
            $this->audit->append($companyId, $actorId, 'quality_issue', $issueId, "issue_{$to}", $key, ['from' => $from, 'to' => $to, 'reason' => $reason]);

            return $issueId;
        }, 3);
    }

    public function verifyIssueChecksum(int $companyId, int $issueId): bool
    {
        $row = DB::table('hr_data_quality_issues as i')->join('hr_data_quality_runs as r', 'r.id', '=', 'i.run_id')
            ->where('r.company_id', $companyId)->where('i.id', $issueId)
            ->select('i.entity_type', 'i.entity_id', 'i.rule_code', 'i.severity', 'i.message', 'i.checksum')->first();
        abort_if(! $row instanceof stdClass, 404);
        $payload = ['entity_type' => $row->entity_type, 'entity_id' => $row->entity_id, 'rule_code' => $row->rule_code, 'severity' => $row->severity, 'message' => $row->message];
        $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return is_string($row->checksum) && hash_equals($row->checksum, $checksum);
    }
}
