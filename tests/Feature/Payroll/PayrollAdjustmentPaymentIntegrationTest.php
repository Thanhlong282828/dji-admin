<?php

namespace Tests\Feature\Payroll;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;
use Tests\Support\Payroll\PayrollAdjustmentDownstreamTestCase;

final class PayrollAdjustmentPaymentIntegrationTest extends PayrollAdjustmentDownstreamTestCase
{
    public function test_positive_adjustment_creates_one_immutable_real_payment_item_and_retry_is_idempotent(): void
    {
        $fixture = $this->payrollAdjustmentFixture('PAY');
        $this->verifiedPaymentAccount($fixture);
        $adjustment = $this->applyAdjustment($fixture, 5_100_000);
        $url = route('admin.payroll.adjustments.downstream', ['adjustment' => $adjustment, 'type' => 'payment']);
        $this->actingAs($fixture['maker'])->post($url, ['company_id' => $fixture['company']])->assertRedirect();
        $item = DB::table('payroll_payment_batch_items')->where('adjustment_request_id', $adjustment)->first();
        self::assertNotNull($item);
        self::assertSame('retroactive_adjustment', $item->source_type);
        self::assertNull($item->payroll_item_id);
        self::assertSame(100_000, $this->databaseInt($item->amount));
        self::assertSame($fixture['item'], $this->databaseInt($item->source_payroll_item_id));
        self::assertSame('001234567890', Crypt::decryptString($this->databaseString($item->account_number_ciphertext)));
        DB::table('employee_payment_accounts')->where('employee_id', $fixture['employee'])->update(['account_number_encrypted' => Crypt::encryptString('999999999999'), 'account_number_last4' => '9999']);
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.adjustments.downstream.retry', ['adjustment' => $adjustment, 'type' => 'payment']), ['company_id' => $fixture['company']])->assertRedirect();
        self::assertSame(1, DB::table('payroll_payment_batch_items')->where('adjustment_request_id', $adjustment)->count());
        self::assertSame('7890', DB::table('payroll_payment_batch_items')->where('adjustment_request_id', $adjustment)->value('account_number_last4'));
        $batch = $this->databaseInt($item->payment_batch_id);
        $service = app(PayrollPaymentBatchService::class);
        $service->validate($fixture['company'], $batch, $fixture['maker']->id);
        $service->review($fixture['company'], $batch, $fixture['maker']->id);
        $service->approve($fixture['company'], $batch, $fixture['checker']->id);
        $service->release($fixture['company'], $batch, $fixture['checker']->id);
        $path = $this->databaseString(DB::table('payroll_payment_batches')->where('id', $batch)->value('storage_path'));
        $csv = Storage::disk('local')->get($path);
        self::assertIsString($csv);
        self::assertStringContainsString('retroactive_adjustment', $csv);
        self::assertStringContainsString('100000', $csv);
    }

    public function test_negative_adjustment_creates_recovery_and_no_payment_transfer(): void
    {
        $fixture = $this->payrollAdjustmentFixture('NEG');
        $adjustment = $this->applyAdjustment($fixture, 4_900_000);
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.adjustments.downstream', ['adjustment' => $adjustment, 'type' => 'payment']), ['company_id' => $fixture['company']])->assertRedirect();
        self::assertSame(0, DB::table('payroll_payment_batch_items')->where('adjustment_request_id', $adjustment)->count());
        self::assertSame(100_000, $this->databaseInt(DB::table('payroll_adjustment_recoveries')->where('adjustment_request_id', $adjustment)->value('remaining_amount_minor')));
        self::assertSame('not_applicable', DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $adjustment)->where('intent_type', 'payment')->value('status'));
    }
}
