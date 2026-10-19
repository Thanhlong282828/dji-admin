<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Validation\ValidationException;
use Modules\Employee\Application\DTOs\EmployeeCredentialData;
use Modules\Employee\Domain\Models\Employee;
use Modules\Employee\Domain\Models\EmployeeCredential;

final readonly class EmployeeCredentialService
{
    public function __construct(private Encrypter $encrypter) {}

    public function create(int $companyId, int $employeeId, EmployeeCredentialData $data): EmployeeCredential
    {
        Employee::query()->where('company_id', $companyId)->findOrFail($employeeId);
        $this->validateDates($data);

        return EmployeeCredential::query()->create(['employee_id' => $employeeId, ...$data->toPersistence($this->encrypter)]);
    }

    public function update(int $companyId, int $credentialId, EmployeeCredentialData $data): EmployeeCredential
    {
        $credential = $this->find($companyId, $credentialId);
        $this->validateDates($data);
        $credential->update($data->toPersistence($this->encrypter));

        return $credential->refresh();
    }

    public function archive(int $companyId, int $credentialId): void
    {
        $this->find($companyId, $credentialId)->update(['archived_at' => now()]);
    }

    public function restore(int $companyId, int $credentialId): void
    {
        $this->find($companyId, $credentialId)->update(['archived_at' => null]);
    }

    private function find(int $companyId, int $credentialId): EmployeeCredential
    {
        return EmployeeCredential::query()->whereHas('employee', fn ($query) => $query->where('company_id', $companyId))->findOrFail($credentialId);
    }

    private function validateDates(EmployeeCredentialData $data): void
    {
        if ($data->issuedAt !== null && $data->expiresAt !== null && $data->expiresAt->isBefore($data->issuedAt)) {
            throw ValidationException::withMessages(['expires_at' => 'Ngày hết hạn không được trước ngày cấp.']);
        }
    }
}
