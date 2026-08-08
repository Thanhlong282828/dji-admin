<?php

namespace Modules\Employee\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Employee\Application\DTOs\DataConsentData;
use Modules\Employee\Domain\Models\DataConsent;
use Modules\Employee\Domain\Models\Employee;

final class DataConsentService
{
    public function decide(int $companyId, int $employeeId, DataConsentData $data, int $actorId): DataConsent
    {
        Employee::query()->where('company_id', $companyId)->findOrFail($employeeId);
        if ($data->expiresAt !== null && $data->expiresAt->isBefore($data->decidedAt)) {
            throw ValidationException::withMessages(['expires_at' => 'Ngày hết hạn phải sau thời điểm quyết định.']);
        }

        return DataConsent::query()->create(['employee_id' => $employeeId, 'actor_id' => $actorId, ...$data->toArray()]);
    }

    public function withdraw(int $companyId, int $consentId, string $reason, int $actorId): DataConsent
    {
        $current = DataConsent::query()->whereHas('employee', fn ($query) => $query->where('company_id', $companyId))->findOrFail($consentId);
        if (! $current->granted) {
            throw ValidationException::withMessages(['consent' => 'Quyết định này đã được rút trước đó.']);
        }

        return DataConsent::query()->create(['employee_id' => $current->employee_id, 'purpose' => $current->purpose, 'policy_version' => $current->policy_version, 'granted' => false, 'decided_at' => now(), 'expires_at' => null, 'reason' => $reason, 'actor_id' => $actorId]);
    }
}
