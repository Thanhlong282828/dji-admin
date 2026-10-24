<?php

namespace Tests\Unit\Performance;

use Modules\Performance\Domain\Services\EnterpriseReportCatalog;
use PHPUnit\Framework\TestCase;

final class EnterpriseReportCatalogTest extends TestCase
{
    public function test_catalog_is_typed_and_sensitive_report_is_permission_filtered(): void
    {
        $catalog = new EnterpriseReportCatalog;
        self::assertCount(6, $catalog->all(false));
        self::assertCount(7, $catalog->all(true));
        self::assertNotContains('payroll', array_column($catalog->all(false), 'key'));
        self::assertContains('payroll', array_column($catalog->all(true), 'key'));
        foreach ($catalog->all(true) as $report) {
            self::assertNotSame('', $report['title']);
            self::assertContains('html', $report['formats']);
        }
    }
}
