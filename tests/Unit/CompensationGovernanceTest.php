<?php

namespace Tests\Unit;

use App\Support\Compensation\PositionCompensationReference;
use App\Support\Navigation\AdminHelpRegistry;
use PHPUnit\Framework\TestCase;

final class CompensationGovernanceTest extends TestCase
{
    public function test_reference_uses_integer_level_and_department_weighting(): void
    {
        $service = new PositionCompensationReference;
        $engineering = $service->resolve(3, 'Phòng Backend', 'Backend Developer');
        $sales = $service->resolve(3, 'Phòng Sales', 'Sales Executive');

        self::assertSame(26_400_000, $engineering->baseSalaryMinor);
        self::assertSame(33_150_000, $engineering->targetGrossMinor());
        self::assertLessThan($engineering->baseSalaryMinor, $sales->baseSalaryMinor);
        self::assertGreaterThan($engineering->kpiTargetMinor, $sales->kpiTargetMinor);
        self::assertSame('VND', $engineering->currency);
        self::assertIsInt($engineering->targetGrossMinor());
    }

    public function test_help_registry_resolves_primary_contexts(): void
    {
        $registry = new AdminHelpRegistry;

        self::assertSame('Nhân viên', $registry->resolve('admin.employees.index')['title']);
        self::assertSame('Vòng đời nhân sự', $registry->resolve('admin.employee-lifecycle.show')['title']);
        self::assertSame('Chấm công', $registry->resolve('admin.attendance.index')['title']);
        self::assertSame('Payroll Inputs', $registry->resolve('admin.payroll.inputs.index')['title']);
        self::assertSame('Government Filing', $registry->resolve('admin.payroll.government-filings.index')['title']);
        self::assertSame('Data Quality', $registry->resolve('admin.operations.data-quality')['title']);
    }
}
