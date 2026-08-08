<?php

namespace Tests\Unit\Payroll;

use Modules\Organization\Domain\Services\SpreadsheetFormulaSanitizer;
use Modules\Payroll\Application\DTOs\ErpAdapterPayload;
use Modules\Payroll\Infrastructure\Adapters\GenericCsvErpAdapter;
use PHPUnit\Framework\TestCase;

final class GenericCsvErpAdapterTest extends TestCase
{
    public function test_it_generates_utf8_csv_in_minor_units_and_neutralizes_formulas(): void
    {
        $adapter = new GenericCsvErpAdapter(new SpreadsheetFormulaSanitizer);
        $artifact = $adapter->export(new ErpAdapterPayload('REF-1', 'JRN-1', [[
            'line_number' => 1,
            'account' => '=DANGEROUS',
            'description' => '+Lương tháng',
            'debit_minor' => 6200000000,
            'credit_minor' => 0,
            'currency' => 'VND',
            'cost_center' => 'TECH',
            'organization_unit' => 'BACKEND',
        ]]));

        self::assertSame('text/csv', $artifact->mime);
        self::assertStringStartsWith("\xEF\xBB\xBF", $artifact->bytes);
        self::assertStringContainsString('6200000000', $artifact->bytes);
        self::assertStringNotContainsString(',=DANGEROUS,', $artifact->bytes);
        self::assertStringNotContainsString(',+Lương tháng,', $artifact->bytes);
    }
}
