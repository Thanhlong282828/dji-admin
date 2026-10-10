<?php

namespace Modules\CRM\Application\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final readonly class CrmService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array<string, mixed> $data */
    public function execute(string $action, int $companyId, User $actor, array $data): int
    {
        if (! Schema::hasTable('crm_leads') || ! Schema::hasTable('crm_opportunities')) {
            throw ValidationException::withMessages(['schema' => 'SCHEMA_UPGRADE_REQUIRED']);
        }

        $this->assertScope($companyId, isset($data['branch_id']) ? $this->integer($data['branch_id']) : null, $actor);

        return $this->db->transaction(function () use ($action, $companyId, $actor, $data): int {
            $id = match ($action) {
                'lead-create' => $this->integer($this->db->table('crm_leads')->insertGetId(['company_id' => $companyId, 'branch_id' => $data['branch_id'] ?? null, 'name' => $data['name'], 'contact' => $data['contact'], 'source' => $data['source'] ?? null, 'status' => 'new', 'owner_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()])),
                'lead-status' => $this->updateLeadStatus($companyId, $this->integer($data['id'] ?? null), $this->string($data['status'] ?? null, 'status')),
                'lead-convert' => $this->convert($companyId, $actor, $data),
                'opportunity-create' => $this->integer($this->db->table('crm_opportunities')->insertGetId($this->opportunity($companyId, $actor, $data))),
                'opportunity-stage' => $this->updateOpportunityStage($companyId, $this->integer($data['id'] ?? null), $this->string($data['stage'] ?? null, 'stage')),
                default => throw ValidationException::withMessages(['action' => 'Unsupported CRM command']),
            };

            activity('crm')->causedBy($actor)->event($action)->withProperties(['company_id' => $companyId, 'record_id' => $id, 'action' => $action])->log('CRM '.$action);

            return $id;
        });
    }

    private function updateLeadStatus(int $companyId, int $id, string $status): int
    {
        $lead = $this->db->table('crm_leads')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->first();
        abort_if($lead === null, 404);
        $allowed = [
            'new' => ['contacted', 'lost'],
            'contacted' => ['qualified', 'lost'],
            'qualified' => ['lost'],
            'converted' => [],
            'lost' => [],
        ];
        $current = $this->string($lead->status, 'status');
        if (! in_array($status, $allowed[$current] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'CRM_LEAD_STATUS_TRANSITION_INVALID']);
        }

        return $this->update('crm_leads', $companyId, $id, ['status' => $status, 'updated_at' => now()]);
    }

    private function updateOpportunityStage(int $companyId, int $id, string $stage): int
    {
        $opportunity = $this->db->table('crm_opportunities')->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->first();
        abort_if($opportunity === null, 404);
        $allowed = [
            'discovery' => ['qualification', 'lost'],
            'qualification' => ['proposal', 'lost'],
            'proposal' => ['negotiation', 'lost'],
            'negotiation' => ['won', 'lost'],
            'won' => [],
            'lost' => [],
        ];
        $current = $this->string($opportunity->stage, 'stage');
        if (! in_array($stage, $allowed[$current] ?? [], true)) {
            throw ValidationException::withMessages(['stage' => 'CRM_OPPORTUNITY_STAGE_TRANSITION_INVALID']);
        }

        return $this->update('crm_opportunities', $companyId, $id, ['stage' => $stage, 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $values */
    private function update(string $table, int $companyId, int $id, array $values): int
    {
        $row = $this->db->table($table)->where(['id' => $id, 'company_id' => $companyId])->lockForUpdate()->first();
        abort_if($row === null, 404);
        $this->db->table($table)->where(['id' => $id, 'company_id' => $companyId])->update($values);

        return $id;
    }

    /** @param array<string, mixed> $data */
    private function convert(int $companyId, User $actor, array $data): int
    {
        $lead = $this->db->table('crm_leads')->where(['id' => $this->integer($data['id'] ?? null), 'company_id' => $companyId])->lockForUpdate()->first();
        abort_if($lead === null, 404);
        $existing = $this->db->table('crm_opportunities')->where(['company_id' => $companyId, 'lead_id' => $lead->id])->value('id');
        if (is_numeric($existing)) {
            return (int) $existing;
        }
        $id = $this->integer($this->db->table('crm_opportunities')->insertGetId($this->opportunity($companyId, $actor, [...$data, 'lead_id' => $lead->id, 'branch_id' => $lead->branch_id])));
        $this->db->table('crm_leads')->where(['id' => $lead->id, 'company_id' => $companyId])->update(['status' => 'converted', 'updated_at' => now()]);

        return $id;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function opportunity(int $companyId, User $actor, array $data): array
    {
        $stage = $this->string($data['stage'] ?? 'discovery', 'stage');
        if (! in_array($stage, ['discovery', 'qualification', 'proposal', 'negotiation', 'won', 'lost'], true)) {
            throw ValidationException::withMessages(['stage' => 'CRM_OPPORTUNITY_STAGE_INVALID']);
        }
        $customerId = isset($data['customer_id']) ? $this->integer($data['customer_id']) : null;
        if ($customerId !== null && ! $this->db->table('commerce_customers')->where(['id' => $customerId, 'company_id' => $companyId])->exists()) {
            throw ValidationException::withMessages(['customer_id' => 'CRM_CUSTOMER_SCOPE_INVALID']);
        }

        return ['company_id' => $companyId, 'branch_id' => $data['branch_id'] ?? null, 'lead_id' => $data['lead_id'] ?? null, 'customer_id' => $customerId, 'stage' => $stage, 'expected_value_minor' => $data['expected_value_minor'] ?? 0, 'probability' => $data['probability'] ?? 0, 'expected_close_date' => $data['expected_close_date'] ?? null, 'owner_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()];
    }

    private function assertScope(int $companyId, ?int $branchId, User $actor): void
    {
        abort_unless($companyId > 0 && $this->db->table('companies')->where('id', $companyId)->exists(), 404);
        abort_unless($this->db->table('company_user_access')->where(['company_id' => $companyId, 'user_id' => $actor->id])->exists(), 403);
        if ($branchId !== null) {
            abort_unless($this->db->table('branches')->where(['id' => $branchId, 'company_id' => $companyId])->exists(), 404);
        }
    }

    private function integer(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['id' => 'A numeric CRM identifier is required.']);
        }

        return (int) $value;
    }

    private function string(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => "A valid CRM {$field} is required."]);
        }

        return $value;
    }
}
