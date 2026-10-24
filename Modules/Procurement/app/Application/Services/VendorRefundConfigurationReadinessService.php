<?php

namespace Modules\Procurement\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Application\Services\AccountingPostingPolicyResolver;

final readonly class VendorRefundConfigurationReadinessService
{
    public function __construct(private ConnectionInterface $db, private AccountingPostingPolicyResolver $policies) {}

    /** @return array<string,mixed> */
    public function forCompany(int $companyId, string $effectiveDate, string $currency = 'VND'): array
    {
        if (! Schema::hasTable('vendor_refund_receivables') || ! Schema::hasTable('supplier_refund_receipts')) {
            return ['paid_credit_policy' => $this->missing('SCHEMA_UPGRADE_REQUIRED'), 'refund_policy' => $this->missing('SCHEMA_UPGRADE_REQUIRED'), 'treasury' => ['status' => 'MISSING', 'error' => 'SCHEMA_UPGRADE_REQUIRED', 'eligible_accounts' => 0], 'overall' => 'SCHEMA_UPGRADE_REQUIRED', 'effective_date' => $effectiveDate, 'currency' => $currency];
        }
        $paid = $this->policy($companyId, 'SUPPLIER_CREDIT_PAID_PORTION', ['RECEIVABLE' => 'debit', 'GRNI' => 'credit'], $effectiveDate, $currency);
        $refund = $this->policy($companyId, 'SUPPLIER_REFUND_RECEIVED', ['CASH' => 'debit', 'RECEIVABLE' => 'credit'], $effectiveDate, $currency);
        $cashCode = $refund['status'] === 'READY' ? $this->policyAccountCode($companyId, $refund['policy_id'], 'CASH') : null;
        $treasury = $this->treasury($companyId, $effectiveDate, $currency, $cashCode);

        return ['paid_credit_policy' => $paid, 'refund_policy' => $refund, 'treasury' => $treasury, 'overall' => $paid['status'] === 'READY' && $refund['status'] === 'READY' && $treasury['status'] === 'READY' ? 'READY' : 'CONFIGURATION_REQUIRED', 'effective_date' => $effectiveDate, 'currency' => $currency];
    }

    /** @param array<string,string> $requiredRoles
     * @return array{status:string,error:?string,policy_id:?int}
     */
    private function policy(int $companyId, string $event, array $requiredRoles, string $date, string $currency): array
    {
        try {
            $policy = $this->policies->resolve($companyId, $event, 'vendor_refund', $date, $currency);
            $roles = [];
            foreach ($policy['lines'] as $line) {
                $roles[$line['line_role']] = $line['entry_side'];
            }
            foreach ($requiredRoles as $role => $side) {
                if (($roles[$role] ?? null) !== $side) {
                    return ['status' => 'INVALID', 'error' => 'ACCOUNTING_POSTING_POLICY_ROLE_INVALID:'.$role, 'policy_id' => $policy['policy_id']];
                }
            }

            return ['status' => 'READY', 'error' => null, 'policy_id' => $policy['policy_id']];
        } catch (ValidationException $exception) {
            $error = collect($exception->errors())->flatten()->first();
            $code = is_string($error) ? $error : 'ACCOUNTING_POSTING_POLICY_INVALID';

            return ['status' => $code === 'ACCOUNTING_POSTING_POLICY_MISSING' ? 'MISSING' : 'INVALID', 'error' => $code, 'policy_id' => null];
        }
    }

    /** @return array{status:string,error:?string,eligible_accounts:int} */
    private function treasury(int $companyId, string $date, string $currency, ?string $cashCode): array
    {
        $configured = $this->db->table('treasury_financial_accounts')->where('company_id', $companyId)->count();
        $query = $this->db->table('treasury_financial_accounts as financial')->join('accounting_accounts as ledger', 'ledger.id', '=', 'financial.accounting_account_id')
            ->where(['financial.company_id' => $companyId, 'financial.status' => 'active', 'financial.currency' => $currency, 'ledger.company_id' => $companyId, 'ledger.status' => 'active', 'ledger.allow_posting' => true])
            ->whereIn('financial.type', ['cash', 'bank', 'payment_clearing'])
            ->where(fn (Builder $builder): Builder => $builder->whereNull('ledger.currency')->orWhere('ledger.currency', $currency))
            ->whereDate('ledger.effective_from', '<=', $date)
            ->where(fn (Builder $builder): Builder => $builder->whereNull('ledger.effective_to')->orWhereDate('ledger.effective_to', '>=', $date));
        if ($cashCode !== null) {
            $query->where('ledger.code', $cashCode);
        }
        $eligible = $query->count();
        if ($eligible > 0) {
            return ['status' => 'READY', 'error' => null, 'eligible_accounts' => $eligible];
        }

        return ['status' => $configured === 0 ? 'MISSING' : 'INVALID', 'error' => $configured === 0 ? 'SUPPLIER_REFUND_TREASURY_ACCOUNT_MISSING' : 'SUPPLIER_REFUND_TREASURY_ACCOUNT_INVALID', 'eligible_accounts' => 0];
    }

    private function policyAccountCode(int $companyId, ?int $policyId, string $role): ?string
    {
        if ($policyId === null) {
            return null;
        }
        $code = $this->db->table('accounting_posting_policy_lines as line')->join('accounting_accounts as account', 'account.id', '=', 'line.account_id')->where(['line.accounting_posting_policy_id' => $policyId, 'line.line_role' => $role, 'account.company_id' => $companyId])->value('account.code');

        return is_string($code) && $code !== '' ? $code : null;
    }

    /** @return array{status:string,error:string,policy_id:null} */
    private function missing(string $error): array
    {
        return ['status' => 'MISSING', 'error' => $error, 'policy_id' => null];
    }
}
