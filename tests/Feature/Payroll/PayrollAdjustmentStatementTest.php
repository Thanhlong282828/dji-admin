<?php

namespace Tests\Feature\Payroll;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Payroll\PayrollAdjustmentDownstreamTestCase;

final class PayrollAdjustmentStatementTest extends PayrollAdjustmentDownstreamTestCase
{
    public function test_private_pdf_is_real_idempotent_and_checksum_verified_on_authorized_download(): void
    {
        $fixture = $this->payrollAdjustmentFixture('PDF');
        $adjustment = $this->applyAdjustment($fixture, 5_100_000);
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.adjustments.downstream', ['adjustment' => $adjustment, 'type' => 'statement']), ['company_id' => $fixture['company']])->assertRedirect();
        $statement = DB::table('payroll_adjustment_payslips')->where('adjustment_request_id', $adjustment)->first();
        self::assertNotNull($statement);
        self::assertSame('application/pdf', $statement->mime_type);
        $path = $this->databaseString($statement->storage_path);
        Storage::disk('local')->assertExists($path);
        $bytes = Storage::disk('local')->get($path);
        self::assertIsString($bytes);
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertSame(strlen($bytes), $this->databaseInt($statement->file_size));
        self::assertSame(hash('sha256', $bytes), $statement->checksum);
        self::assertSame($statement->payload_checksum, $statement->snapshot_checksum);
        $this->actingAs($fixture['maker'])->get(route('admin.payroll.adjustments.statement.download', ['adjustment' => $adjustment, 'company_id' => $fixture['company']]))->assertOk()->assertHeader('content-type', 'application/pdf');
        self::assertSame(1, DB::table('payroll_adjustment_access_logs')->where('adjustment_request_id', $adjustment)->where('action', 'statement_download')->count());
        $this->actingAs($fixture['maker'])->post(route('admin.payroll.adjustments.downstream.retry', ['adjustment' => $adjustment, 'type' => 'statement']), ['company_id' => $fixture['company']]);
        self::assertSame(1, DB::table('payroll_adjustment_payslips')->where('adjustment_request_id', $adjustment)->count());
        Storage::disk('local')->put($path, 'corrupt');
        $this->actingAs($fixture['maker'])->get(route('admin.payroll.adjustments.statement.download', ['adjustment' => $adjustment, 'company_id' => $fixture['company']]))->assertSessionHasErrors('statement');
    }
}
