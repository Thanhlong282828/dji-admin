<?php

namespace Tests\Unit\Payroll;

use Modules\Payroll\Application\DTOs\AnnualTaxComponentData;
use Modules\Payroll\Application\DTOs\AnnualTaxSourceData;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxComponentCategory;
use Modules\Payroll\Domain\Enums\PayrollAnnualTaxSourceType;
use Modules\Payroll\Domain\Services\AnnualTaxChecksumService;
use PHPUnit\Framework\TestCase;

final class AnnualTaxChecksumServiceTest extends TestCase
{
    public function test_checksum_is_canonical_order_independent_and_sensitive_to_immutable_values(): void
    {
        $service = new AnnualTaxChecksumService;
        $a = $this->source('A', 1_000_000, 1);
        $b = $this->source('B', 2_000_000, 1);

        $checksum = $service->reconciliation(1, 2, 2026, 'VN', 'VND', 1, [$a, $b]);
        self::assertSame($checksum, $service->reconciliation(1, 2, 2026, 'VN', 'VND', 1, [$b, $a]));
        self::assertNotSame($checksum, $service->reconciliation(1, 2, 2026, 'VN', 'VND', 1, [$a, $this->source('B', 2_000_001, 1)]));
        self::assertNotSame($checksum, $service->reconciliation(1, 2, 2026, 'VN', 'VND', 2, [$a, $b]));
        self::assertNotSame($checksum, $service->reconciliation(1, 2, 2027, 'VN', 'VND', 1, [$a, $b]));
        self::assertNotSame($service->source($a), $service->source($this->source('A', 1_000_000, 2)));
        self::assertSame($service->hashPayload(['b' => 2, 'a' => 1]), $service->hashPayload(['a' => 1, 'b' => 2]));
    }

    private function source(string $reference, int $gross, int $version): AnnualTaxSourceData
    {
        $component = new AnnualTaxComponentData(PayrollAnnualTaxComponentCategory::TaxableIncome, 'TAXABLE_INCOME', 'Taxable income', $gross, 'VND', $version, 10, null, ['source_type' => 'formula'], 1);

        return new AnnualTaxSourceData(PayrollAnnualTaxSourceType::RegularPayroll, $reference, 2026, 7, 2026, 1, 2, 3, null, null, null, $version, 4, 1, $gross, $gross, 0, 0, 0, 0, 0, 100_000, 'VND', '2026-07-'.$reference, [$component]);
    }
}
