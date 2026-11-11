<?php

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxReconciliationStatus;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxResultType;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int $tax_year
 * @property int $version
 * @property int|null $calculation_version
 * @property string $currency
 * @property string $jurisdiction
 * @property string $reference
 * @property PayrollAnnualTaxReconciliationStatus|string $status
 * @property PayrollAnnualTaxResultType|string|null $result_type
 * @property array<string, mixed>|string $taxpayer_snapshot
 * @property array<string, mixed>|string $company_tax_snapshot
 * @property array<string, mixed>|string|null $tax_policy_snapshot
 * @property string|null $source_checksum
 */
final class PayrollAnnualTaxReconciliation extends Model
{
    protected $fillable = [
    ];

    protected function casts(): array
    {
        return ['status' => PayrollAnnualTaxReconciliationStatus::class, 'result_type' => PayrollAnnualTaxResultType::class, 'taxpayer_snapshot' => 'array', 'company_tax_snapshot' => 'array', 'tax_policy_snapshot' => 'array', 'calculated_at' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'finalized_at' => 'immutable_datetime', 'superseded_at' => 'immutable_datetime'];
    }

    public function statusValue(): PayrollAnnualTaxReconciliationStatus
    {
        $status = $this->getAttribute('status');

        return $status instanceof PayrollAnnualTaxReconciliationStatus
            ? $status
            : PayrollAnnualTaxReconciliationStatus::from($this->requiredString($status, 'status'));
    }

    /** @return array<string, mixed> */
    public function taxpayerSnapshotValue(): array
    {
        return $this->arrayAttribute('taxpayer_snapshot');
    }

    /** @return array<string, mixed> */
    public function companySnapshotValue(): array
    {
        return $this->arrayAttribute('company_tax_snapshot');
    }

    /** @return array<string, mixed>|null */
    public function policySnapshotValue(): ?array
    {
        $value = $this->getAttribute('tax_policy_snapshot');

        return $value === null ? null : $this->normalizeArray($value, 'tax_policy_snapshot');
    }

    private function requiredString(mixed $value, string $attribute): string
    {
        if (! is_string($value)) {
            throw new \UnexpectedValueException("Annual Tax {$attribute} must be a string.");
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function arrayAttribute(string $attribute): array
    {
        return $this->normalizeArray($this->getAttribute($attribute), $attribute);
    }

    /** @return array<string, mixed> */
    private function normalizeArray(mixed $value, string $attribute): array
    {
        if (! is_array($value)) {
            throw new \UnexpectedValueException("Annual Tax {$attribute} must be an object snapshot.");
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
