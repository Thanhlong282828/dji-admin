<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class EmployeePaymentAccountService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param array{employee_id:int,payment_method:string,bank_code:?string,bank_name:?string,branch_code:?string,account_number:?string,account_holder_name:string,currency:string,is_primary:bool,effective_from:string,effective_to:?string,status:string} $data */
    public function save(int $companyId, ?int $accountId, array $data, int $actorId): int
    {
        return $this->db->transaction(function () use ($companyId, $accountId, $data, $actorId): int {
            $employee = $this->db->table('employees')->where('company_id', $companyId)->where('id', $data['employee_id'])->exists();
            if (! $employee) {
                abort(404);
            }
            if ($data['effective_to'] !== null && $data['effective_to'] < $data['effective_from']) {
                throw ValidationException::withMessages(['effective_to' => 'Effective To phải sau Effective From.']);
            }
            $overlap = $this->db->table('employee_payment_accounts')->where('company_id', $companyId)->where('employee_id', $data['employee_id'])->where('payment_method', $data['payment_method'])->where('currency', $data['currency'])->where('is_primary', true)->where('status', 'active')->when($accountId !== null, fn (Builder $q): Builder => $q->where('id', '!=', $accountId))->where(fn (Builder $q): Builder => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']))->when($data['effective_to'] !== null, fn (Builder $q): Builder => $q->where('effective_from', '<=', $data['effective_to']))->exists();
            if ($data['is_primary'] && $data['status'] === 'active' && $overlap) {
                throw ValidationException::withMessages(['effective_from' => 'Primary payment account bị chồng khoảng hiệu lực.']);
            }
            $values = ['company_id' => $companyId, 'employee_id' => $data['employee_id'], 'payment_method' => $data['payment_method'], 'bank_code' => $data['bank_code'], 'bank_name' => $data['bank_name'], 'branch_code' => $data['branch_code'], 'account_holder_name' => $data['account_holder_name'], 'currency' => strtoupper($data['currency']), 'is_primary' => $data['is_primary'], 'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'], 'status' => $data['status'], 'updated_at' => now()];
            if ($data['account_number'] !== null && $data['account_number'] !== '') {
                $normalized = preg_replace('/\s+/', '', $data['account_number']) ?? '';
                if ($normalized === '') {
                    throw ValidationException::withMessages(['account_number' => 'Account number không hợp lệ.']);
                }$values['account_number_encrypted'] = Crypt::encryptString($normalized);
                $values['account_number_last4'] = substr($normalized, -4);
                $values['verification_status'] = 'unverified';
                $values['verified_by'] = null;
                $values['verified_at'] = null;
            }
            if ($accountId === null) {
                if (! isset($values['account_number_encrypted'])) {
                    throw ValidationException::withMessages(['account_number' => 'Account number bắt buộc.']);
                }$values += ['uuid' => (string) Str::uuid(), 'created_by' => $actorId, 'created_at' => now(), 'verification_status' => 'unverified'];
                $accountId = (int) $this->db->table('employee_payment_accounts')->insertGetId($values);
                $event = 'created';
            } else {
                $updated = $this->db->table('employee_payment_accounts')->where('company_id', $companyId)->where('id', $accountId)->update($values);
                if ($updated < 1 && ! $this->db->table('employee_payment_accounts')->where('company_id', $companyId)->where('id', $accountId)->exists()) {
                    abort(404);
                }$event = 'updated';
            }
            activity('payroll-payment')->causedBy($actorId)->event('payment_account_'.$event)->withProperties(['company_id' => $companyId, 'employee_id' => $data['employee_id'], 'account_id' => $accountId, 'last4' => $values['account_number_last4'] ?? null])->log('Payment account '.$event);

            return $accountId;
        }, 3);
    }

    public function verify(int $companyId, int $accountId, int $actorId): void
    {
        $updated = $this->db->table('employee_payment_accounts')->where('company_id', $companyId)->where('id', $accountId)->where('status', 'active')->update(['verification_status' => 'verified', 'verified_by' => $actorId, 'verified_at' => now(), 'updated_at' => now()]);
        if ($updated < 1) {
            abort(404);
        } activity('payroll-payment')->causedBy($actorId)->event('payment_account_verified')->withProperties(['company_id' => $companyId, 'account_id' => $accountId])->log('Payment account verified');
    }
}
