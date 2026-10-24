<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $id = $this->route('branch');

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('branches')->where(fn (Builder $query): Builder => $query->where('company_id', $this->integer('company_id')))->ignore(is_numeric($id) ? (int) $id : null)],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'leader_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    /** @return array{company_id:int,leader_employee_id:?int,code:string,name:string,status:string,address_line:?string,city:?string,timezone:string,phone:?string,email:?string} */
    public function branchData(): array
    {
        return [
            'company_id' => $this->integer('company_id'),
            'leader_employee_id' => $this->filled('leader_employee_id') ? $this->integer('leader_employee_id') : null,
            'code' => $this->string('code')->toString(),
            'name' => $this->string('name')->toString(),
            'status' => $this->string('status')->toString(),
            'address_line' => $this->nullableString('address_line'),
            'city' => $this->nullableString('city'),
            'timezone' => $this->string('timezone')->toString(),
            'phone' => $this->nullableString('phone'),
            'email' => $this->nullableString('email'),
        ];
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
