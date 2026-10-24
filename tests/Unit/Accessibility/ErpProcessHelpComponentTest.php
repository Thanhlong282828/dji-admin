<?php

namespace Tests\Unit\Accessibility;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class ErpProcessHelpComponentTest extends TestCase
{
    public function test_process_help_is_accessible_and_explains_lineage_without_fake_data(): void
    {
        $html = Blade::render(
            '<x-erp.process-help title="Customer Refund" :steps="$steps" description="Canonical workflow" owner="Commerce + Finance" accounting-impact="Dr Liability · Cr Cash" />',
            ['steps' => [[
                'title' => 'Customer Refund', 'owner' => 'Commerce Module', 'source' => 'GetCustomerRefundWorkspaceQuery → CustomerRefundReadService',
                'meaning' => 'Refund from posted liability.', 'accounting' => 'Dr Liability · Cr Cash', 'next' => 'Treasury Outflow',
                'url' => null, 'link_label' => 'View Customer Refund', 'has_operating_data' => false, 'evidence_count' => 0,
                'empty_message' => 'No operating data available', 'empty_action' => 'Create from upstream workflow',
            ]]],
        );

        self::assertStringContainsString('aria-label="Mở giải thích quy trình Customer Refund"', $html);
        self::assertStringContainsString('role="dialog"', $html);
        self::assertStringContainsString('aria-modal="true"', $html);
        self::assertStringContainsString('data-process-help-close', $html);
        self::assertStringContainsString('aria-label="Đóng giải thích quy trình Customer Refund"', $html);
        self::assertStringContainsString('No operating data available', $html);
        self::assertStringContainsString('GetCustomerRefundWorkspaceQuery', $html);
        self::assertStringContainsString('Source link unavailable for current permission or schema.', $html);
        self::assertStringNotContainsString('href=""', $html);
    }
}
