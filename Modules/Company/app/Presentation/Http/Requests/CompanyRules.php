<?php

namespace Modules\Company\Presentation\Http\Requests;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Company\Domain\Enums\CompanyStatus;

trait CompanyRules
{
    /** @return array<string,mixed> */
    private function companyRules(?int $companyId = null): array
    {
        return ['code' => ['required', 'alpha_dash', 'max:30', Rule::unique('companies', 'code')->ignore($companyId)], 'name' => ['required', 'string', 'max:255'], 'short_name' => ['nullable', 'string', 'max:100'], 'tax_code' => ['nullable', 'string', 'max:30', Rule::unique('companies', 'tax_code')->ignore($companyId)], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'website' => ['nullable', 'url', 'max:255'], 'address' => ['nullable', 'string', 'max:255'], 'country_code' => ['required', 'string', 'size:2'], 'timezone' => ['required', 'timezone'], 'currency_code' => ['required', 'string', 'size:3'], 'legal_representative' => ['nullable', 'string', 'max:255'], 'status' => ['required', Rule::enum(CompanyStatus::class)], 'description' => ['nullable', 'string', 'max:5000'],
            'branches' => ['sometimes', 'array', 'max:25'],
            'branches.*.id' => ['nullable', 'integer'],
            'branches.*.code' => ['required_with:branches', 'string', 'max:50', 'distinct'],
            'branches.*.name' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.address_line' => ['nullable', 'string', 'max:255'],
            'branches.*.city' => ['nullable', 'string', 'max:100'],
            'branches.*.status' => ['required_with:branches', Rule::in(['active', 'inactive', 'archived'])],
            'branches.*.timezone' => ['required_with:branches', 'timezone'],
            'seller_accounts' => ['sometimes', 'array', 'max:10'],
            'seller_accounts.*.id' => ['nullable', 'integer'],
            'seller_accounts.*.display_name' => ['required_with:seller_accounts', 'string', 'max:255'],
            'seller_accounts.*.account_name' => ['required_with:seller_accounts', 'string', 'max:255'],
            'seller_accounts.*.email' => ['required_with:seller_accounts', 'email', 'max:255', 'distinct'],
            'seller_accounts.*.phone_e164' => ['nullable', 'string', 'max:20', 'distinct'],
            'seller_accounts.*.password' => [$companyId === null ? 'required_with:seller_accounts' : 'nullable', 'string', 'min:12', 'max:255'],
            'seller_accounts.*.password_confirmation' => ['nullable', 'same:seller_accounts.*.password'],
        ];
    }

    private function validateBranchRows(Validator $validator, ?int $companyId): void
    {
        $validator->after(function (Validator $validator) use ($companyId): void {
            $rows = $this->input('branches', []);
            if (! is_array($rows)) {
                return;
            }
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
                if ($id !== null && ($companyId === null || ! DB::table('branches')->where('id', $id)->where('company_id', $companyId)->whereNull('deleted_at')->exists())) {
                    $validator->errors()->add("branches.{$index}.id", 'Branch không thuộc Company đang chỉnh sửa.');
                }
                $code = isset($row['code']) && is_string($row['code']) ? $row['code'] : '';
                if ($code === '') {
                    continue;
                }
                $scopeCompany = $companyId;
                if ($scopeCompany !== null && DB::table('branches')->where('company_id', $scopeCompany)->where('code', $code)->when($id !== null, fn ($query) => $query->where('id', '!=', $id))->exists()) {
                    $validator->errors()->add("branches.{$index}.code", 'Mã chi nhánh đã tồn tại trong Company.');
                }
            }

            $sellerRows = $this->input('seller_accounts', []);
            if (! is_array($sellerRows)) {
                return;
            }
            foreach ($sellerRows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $profileId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
                $profile = $profileId === null ? null : DB::table('seller_profiles')->where('id', $profileId)->where('company_id', $companyId)->first();
                if ($profileId !== null && $profile === null) {
                    $validator->errors()->add("seller_accounts.{$index}.id", 'Seller account không thuộc Company đang chỉnh sửa.');

                    continue;
                }
                if ($profile === null && (! isset($row['password']) || ! is_string($row['password']) || $row['password'] === '')) {
                    $validator->errors()->add("seller_accounts.{$index}.password", 'Mật khẩu là bắt buộc khi tạo Seller account mới.');
                }
                $userId = $profile !== null && is_numeric($profile->user_id) ? (int) $profile->user_id : null;
                $email = $row['email'] ?? null;
                if (is_string($email) && DB::table('users')->where('email', $email)->when($userId !== null, fn ($query) => $query->where('id', '!=', $userId))->exists()) {
                    $validator->errors()->add("seller_accounts.{$index}.email", 'Email tài khoản đã tồn tại.');
                }
                $phone = $row['phone_e164'] ?? null;
                if (is_string($phone) && $phone !== '' && DB::table('users')->where('phone_e164', $phone)->when($userId !== null, fn ($query) => $query->where('id', '!=', $userId))->exists()) {
                    $validator->errors()->add("seller_accounts.{$index}.phone_e164", 'Số điện thoại tài khoản đã tồn tại.');
                }
            }
        });
    }
}
