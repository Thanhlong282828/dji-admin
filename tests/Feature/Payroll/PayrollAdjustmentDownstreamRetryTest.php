<?php

namespace Tests\Feature\Payroll;

use Illuminate\Support\Facades\DB;
use Tests\Support\Payroll\PayrollAdjustmentDownstreamTestCase;

final class PayrollAdjustmentDownstreamRetryTest extends PayrollAdjustmentDownstreamTestCase
{
    public function test_blocked_payment_completes_after_prerequisite_is_fixed_without_duplicate_artifact(): void
    {
        $fixture = $this->payrollAdjustmentFixture('RETRY');
        $adjustment = $this->applyAdjustment($fixture, 5_100_000);
        $url = route('admin.payroll.adjustments.downstream', ['adjustment' => $adjustment, 'type' => 'payment']);
        $this->actingAs($fixture['maker'])->post($url, ['company_id' => $fixture['company']]);
        $intent = DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $adjustment)->where('intent_type', 'payment_eligible')->first();
        self::assertNotNull($intent);
        self::assertSame('blocked', $intent->status);
        self::assertSame(1, $this->databaseInt($intent->attempt_count));
        $this->verifiedPaymentAccount($fixture);
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.adjustments.downstream.retry', ['adjustment' => $adjustment, 'type' => 'payment']), ['company_id' => $fixture['company']])->assertRedirect();
        $intent = DB::table('payroll_adjustment_downstream_intents')->where('id', $intent->id)->first();
        self::assertNotNull($intent);
        self::assertSame('completed', $intent->status);
        self::assertSame(2, $this->databaseInt($intent->attempt_count));
        self::assertNull($intent->failure_code);
        self::assertNotNull($intent->completed_at);
        self::assertSame(1, DB::table('payroll_payment_batch_items')->where('adjustment_request_id', $adjustment)->count());
    }
}
