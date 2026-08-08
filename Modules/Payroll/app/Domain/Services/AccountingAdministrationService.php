<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AccountingAdministrationService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array{code:string,name:string,account_type:string,normal_balance:string,currency:?string,allow_posting:bool,status:string,effective_from:string,effective_to:?string,parent_id:?int} $data */
    public function saveAccount(int $companyId, ?int $accountId, array $data, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $accountId, $data, $actorId): int {
            $this->dates($data['effective_from'], $data['effective_to']);
            if ($data['parent_id'] !== null && ! $this->db->table('accounting_accounts')->where('company_id', $companyId)->where('id', $data['parent_id'])->exists()) {
                abort(404);
            }
            $duplicate = $this->db->table('accounting_accounts')->where('company_id', $companyId)->where('code', $data['code'])->when($accountId !== null, fn (Builder $q): Builder => $q->where('id', '<>', $accountId))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['code' => 'Account code đã tồn tại trong Company.']);
            }
            $values = $data + ['company_id' => $companyId, 'updated_at' => now()];
            if ($accountId === null) {
                $accountId = (int) $this->db->table('accounting_accounts')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'created_by' => $actorId, 'created_at' => now()]);
            } elseif ($this->db->table('accounting_accounts')->where('company_id', $companyId)->where('id', $accountId)->update($values) < 1 && ! $this->db->table('accounting_accounts')->where('company_id', $companyId)->where('id', $accountId)->exists()) {
                abort(404);
            }
            $this->activity($actorId, 'account_saved', $companyId, ['account_id' => $accountId]);

            return $accountId;
        });
    }

    /** @param array{code:string,name:string,version:int,currency:string,aggregation_strategy:string,effective_from:string,effective_to:?string,rounding_rule:string,status:string,note:?string} $data */
    public function savePolicy(int $companyId, ?int $policyId, array $data, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $policyId, $data, $actorId): int {
            $this->dates($data['effective_from'], $data['effective_to']);
            $used = $policyId !== null && $this->db->table('payroll_accounting_journals')->where('company_id', $companyId)->where('accounting_policy_id', $policyId)->whereIn('status', ['approved', 'internally_posted', 'exported', 'reversed'])->exists();
            if ($used) {
                throw ValidationException::withMessages(['policy' => 'Policy đã dùng bởi Journal khóa; hãy tạo version mới.']);
            }
            $overlap = $this->db->table('payroll_accounting_policies')->where('company_id', $companyId)->where('code', $data['code'])->where('status', 'active')->when($policyId !== null, fn (Builder $q): Builder => $q->where('id', '<>', $policyId))->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']))->when($data['effective_to'] !== null, fn (Builder $q): Builder => $q->where('effective_from', '<=', $data['effective_to']))->exists();
            if ($data['status'] === 'active' && $overlap) {
                throw ValidationException::withMessages(['effective_from' => 'Active Accounting Policy bị chồng khoảng hiệu lực.']);
            }
            $values = $data + ['company_id' => $companyId, 'updated_at' => now()];
            if ($policyId === null) {
                $policyId = (int) $this->db->table('payroll_accounting_policies')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'created_by' => $actorId, 'created_at' => now()]);
            } elseif ($this->db->table('payroll_accounting_policies')->where('company_id', $companyId)->where('id', $policyId)->update($values) < 1 && ! $this->db->table('payroll_accounting_policies')->where('company_id', $companyId)->where('id', $policyId)->exists()) {
                abort(404);
            }
            $this->activity($actorId, 'policy_saved', $companyId, ['policy_id' => $policyId]);

            return $policyId;
        });
    }

    /** @param array{accounting_policy_id:int,source_type:string,source_code:string,debit_account_id:?int,credit_account_id:?int,cost_center_id:?int,organization_unit_id:?int,priority:int,effective_from:string,effective_to:?string,status:string} $data */
    public function saveMapping(int $companyId, ?int $mappingId, array $data, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $mappingId, $data, $actorId): int {
            $this->dates($data['effective_from'], $data['effective_to']);
            if ($data['debit_account_id'] === null && $data['credit_account_id'] === null) {
                throw ValidationException::withMessages(['account' => 'Mapping cần Debit hoặc Credit account.']);
            }
            if (! $this->db->table('payroll_accounting_policies')->where('company_id', $companyId)->where('id', $data['accounting_policy_id'])->exists()) {
                abort(404);
            }
            foreach (array_filter([$data['debit_account_id'], $data['credit_account_id']]) as $accountId) {
                $valid = $this->db->table('accounting_accounts')->where('company_id', $companyId)->where('id', $accountId)->where('status', 'active')->where('allow_posting', true)->exists();
                if (! $valid) {
                    throw ValidationException::withMessages(['account' => 'Account phải active, postable và thuộc cùng Company.']);
                }
            }
            foreach (['cost_centers' => $data['cost_center_id'], 'organization_units' => $data['organization_unit_id']] as $table => $id) {
                if ($id !== null && ! $this->db->table($table)->where('company_id', $companyId)->where('id', $id)->exists()) {
                    abort(404);
                }
            }
            $overlap = $this->db->table('payroll_accounting_mappings')->where('company_id', $companyId)->where('accounting_policy_id', $data['accounting_policy_id'])->where('source_type', $data['source_type'])->where('source_code', $data['source_code'])->where(fn (Builder $q): Builder => $q->where('cost_center_id', $data['cost_center_id']))->where(fn (Builder $q): Builder => $q->where('organization_unit_id', $data['organization_unit_id']))->where('status', 'active')->when($mappingId !== null, fn (Builder $q): Builder => $q->where('id', '<>', $mappingId))->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']))->when($data['effective_to'] !== null, fn (Builder $q): Builder => $q->where('effective_from', '<=', $data['effective_to']))->exists();
            if ($data['status'] === 'active' && $overlap) {
                throw ValidationException::withMessages(['effective_from' => 'Mapping cùng resolution key bị chồng khoảng hiệu lực.']);
            }
            $values = $data + ['company_id' => $companyId, 'updated_at' => now()];
            if ($mappingId === null) {
                $mappingId = (int) $this->db->table('payroll_accounting_mappings')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'created_by' => $actorId, 'created_at' => now()]);
            } elseif ($this->db->table('payroll_accounting_mappings')->where('company_id', $companyId)->where('id', $mappingId)->update($values) < 1 && ! $this->db->table('payroll_accounting_mappings')->where('company_id', $companyId)->where('id', $mappingId)->exists()) {
                abort(404);
            }
            $this->activity($actorId, 'mapping_saved', $companyId, ['mapping_id' => $mappingId]);

            return $mappingId;
        });
    }

    private function dates(string $from, ?string $to): void
    {
        if ($to !== null && $to < $from) {
            throw ValidationException::withMessages(['effective_to' => 'Effective To phải sau Effective From.']);
        }
    }

    /** @param array<string,int> $safe */
    private function activity(int $actor, string $event, int $company, array $safe): void
    {
        activity('payroll-accounting')->causedBy($actor)->event($event)->withProperties(['company_id' => $company] + $safe)->log('Payroll accounting '.$event);
    }
}
