<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Payroll\Domain\Enums\PayrollDownstreamSourceType;
use Modules\Payroll\Domain\Services\PayrollDownstreamSourceInvariant;
use stdClass;
use Tests\TestCase;

final class PayrollAdjustmentDownstreamSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_items_support_regular_and_adjustment_sources(): void
    {
        $this->withoutForeignKeys(function (): void {
            DB::table('payroll_payment_batch_items')->insert($this->paymentItem(1, 'regular_payroll', 1001, null, null));
            DB::table('payroll_payment_batch_items')->insert($this->paymentItem(2, 'retroactive_adjustment', null, 2001, 3001));
        });

        $regular = $this->row('payroll_payment_batch_items', 1);
        $adjustment = $this->row('payroll_payment_batch_items', 2);
        self::assertSame('regular_payroll', $this->string($regular->source_type));
        self::assertSame(1001, $this->integer($regular->payroll_item_id));
        self::assertSame('retroactive_adjustment', $this->string($adjustment->source_type));
        self::assertNull($adjustment->payroll_item_id);
        self::assertSame(2001, $this->integer($adjustment->adjustment_request_id));
        self::assertSame(3001, $this->integer($adjustment->adjustment_run_id));
    }

    public function test_accounting_journal_and_source_persist_adjustment_trace_and_signed_delta(): void
    {
        $this->withoutForeignKeys(function (): void {
            DB::table('payroll_accounting_journals')->insert($this->journal(1, 'regular_payroll', 5001, null, null));
            DB::table('payroll_accounting_journals')->insert($this->journal(2, 'retroactive_adjustment', null, 6001, 7001));
            DB::table('payroll_accounting_journal_sources')->insert([
                'id' => 1,
                'company_id' => 91,
                'journal_id' => 2,
                'journal_line_id' => 81,
                'payroll_item_id' => null,
                'payroll_item_component_id' => null,
                'employee_id' => null,
                'calculation_version' => 4,
                'source_type' => 'NET',
                'downstream_source_type' => 'retroactive_adjustment',
                'source_code' => 'ADJUSTMENT-NET',
                'source_amount_minor' => 125_000,
                'adjustment_request_id' => 6001,
                'adjustment_run_id' => 7001,
                'adjustment_component_id' => 8001,
                'source_payroll_run_id' => 5001,
                'source_payroll_item_id' => 5002,
                'historical_amount_minor' => 5_000_000,
                'corrected_amount_minor' => 4_875_000,
                'delta_amount_minor' => -125_000,
                'source_calculation_version' => 3,
                'delta_calculation_version' => 4,
                'created_at' => now(),
            ]);
        });

        $regular = $this->row('payroll_accounting_journals', 1);
        $adjustment = $this->row('payroll_accounting_journals', 2);
        $source = $this->row('payroll_accounting_journal_sources', 1);
        self::assertSame(5001, $this->integer($regular->payroll_run_id));
        self::assertNull($adjustment->payroll_run_id);
        self::assertSame('retroactive_adjustment', $this->string($adjustment->journal_source_type));
        self::assertSame(-125_000, $this->integer($source->delta_amount_minor));
        self::assertSame(4, $this->integer($source->delta_calculation_version));
    }

    public function test_statement_and_downstream_operational_metadata_persist(): void
    {
        $this->withoutForeignKeys(function (): void {
            DB::table('payroll_adjustment_payslips')->insert([
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'company_id' => 91,
                'adjustment_request_id' => 6001,
                'source_payroll_item_id' => 5002,
                'source_payslip_id' => null,
                'source_payroll_period_id' => 4001,
                'payment_period_id' => null,
                'document_type' => 'adjustment_statement',
                'snapshot_payload' => '{}',
                'payload_checksum' => str_repeat('a', 64),
                'status' => 'generated',
                'storage_disk' => 'private',
                'storage_path' => 'payroll/adjustments/statement.pdf',
                'file_name' => 'adjustment-statement.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 4096,
                'checksum' => str_repeat('b', 64),
                'snapshot_checksum' => str_repeat('c', 64),
                'generation_version' => 2,
                'generated_by' => 9001,
                'generated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('payroll_adjustment_downstream_intents')->insert([
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'company_id' => 91,
                'adjustment_request_id' => 6001,
                'intent_type' => 'statement',
                'amount_minor' => -125_000,
                'currency' => 'VND',
                'status' => 'failed',
                'downstream_record_id' => 1,
                'source_trace' => '{}',
                'idempotency_key' => (string) Str::uuid(),
                'attempt_count' => 3,
                'last_attempt_at' => now(),
                'processing_started_at' => now(),
                'failed_at' => now(),
                'failure_code' => 'STORAGE_UNAVAILABLE',
                'failure_message' => 'Private storage is temporarily unavailable.',
                'artifact_type' => 'adjustment_statement',
                'adjustment_statement_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $statement = $this->row('payroll_adjustment_payslips', 1);
        $intent = $this->row('payroll_adjustment_downstream_intents', 1);
        self::assertSame('application/pdf', $this->string($statement->mime_type));
        self::assertSame(4096, $this->integer($statement->file_size));
        self::assertSame(3, $this->integer($intent->attempt_count));
        self::assertSame('STORAGE_UNAVAILABLE', $this->string($intent->failure_code));
        self::assertSame(1, $this->integer($intent->adjustment_statement_id));
    }

    public function test_required_columns_and_nullable_source_columns_are_exposed(): void
    {
        self::assertTrue(Schema::hasColumns('payroll_payment_batch_items', ['source_type', 'adjustment_request_id', 'adjustment_run_id', 'source_payroll_item_id']));
        self::assertTrue(Schema::hasColumns('payroll_accounting_journals', ['journal_source_type', 'adjustment_request_id', 'adjustment_run_id', 'source_payroll_run_id']));
        self::assertTrue(Schema::hasColumns('payroll_accounting_journal_sources', ['downstream_source_type', 'historical_amount_minor', 'corrected_amount_minor', 'delta_amount_minor']));
        self::assertTrue(Schema::hasColumns('payroll_adjustment_payslips', ['storage_disk', 'storage_path', 'mime_type', 'file_size', 'checksum', 'generated_at']));
        self::assertTrue(Schema::hasColumns('payroll_adjustment_downstream_intents', ['attempt_count', 'processing_started_at', 'completed_at', 'failed_at', 'failure_code']));
    }

    public function test_source_invariant_accepts_valid_shapes_and_rejects_ambiguous_shapes(): void
    {
        $invariant = new PayrollDownstreamSourceInvariant;
        $invariant->assertPayment(PayrollDownstreamSourceType::RegularPayroll, 1, null, null);
        $invariant->assertPayment(PayrollDownstreamSourceType::RetroactiveAdjustment, null, 2, 3);
        $invariant->assertAccounting(PayrollDownstreamSourceType::RegularPayroll, 1, null, null);
        $invariant->assertAccounting(PayrollDownstreamSourceType::AdjustmentReversal, null, 2, 3);

        try {
            $invariant->assertPayment(PayrollDownstreamSourceType::RegularPayroll, null, 2, 3);
            self::fail('Ambiguous regular Payment source was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('A regular Payroll payment must reference only a Payroll Item.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $invariant->assertAccounting(PayrollDownstreamSourceType::RetroactiveAdjustment, 1, 2, 3);
    }

    /** @return array<string, int|string|null|\DateTimeInterface> */
    private function paymentItem(int $id, string $sourceType, ?int $payrollItemId, ?int $adjustmentRequestId, ?int $adjustmentRunId): array
    {
        return [
            'id' => $id, 'uuid' => (string) Str::uuid(), 'company_id' => 91, 'payment_batch_id' => 101,
            'payroll_run_id' => 5001, 'payroll_item_id' => $payrollItemId, 'source_type' => $sourceType,
            'adjustment_request_id' => $adjustmentRequestId, 'adjustment_run_id' => $adjustmentRunId,
            'source_payroll_item_id' => 5002, 'source_calculation_version' => 3, 'delta_calculation_version' => 4,
            'employee_id' => 301, 'calculation_version' => 4, 'payment_method' => 'bank_transfer',
            'currency' => 'VND', 'amount' => 125_000, 'beneficiary_name_snapshot' => 'Schema Employee',
            'payment_reference' => 'PAY-'.$id, 'status' => 'pending', 'validation_status' => 'valid',
            'reconciliation_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    /** @return array<string, int|string|null|\DateTimeInterface> */
    private function journal(int $id, string $sourceType, ?int $payrollRunId, ?int $adjustmentRequestId, ?int $adjustmentRunId): array
    {
        return [
            'id' => $id, 'uuid' => (string) Str::uuid(), 'company_id' => 91, 'payroll_run_id' => $payrollRunId,
            'journal_source_type' => $sourceType, 'adjustment_request_id' => $adjustmentRequestId,
            'adjustment_run_id' => $adjustmentRunId, 'source_payroll_run_id' => 5001,
            'source_payroll_item_id' => 5002, 'source_calculation_version' => 3, 'delta_calculation_version' => 4,
            'payroll_period_id' => 4001, 'accounting_policy_id' => 201, 'policy_version' => 1,
            'journal_number' => 'JRN-'.$id, 'journal_date' => '2026-07-29', 'posting_date' => '2026-07-29',
            'resolution_date' => '2026-07-29', 'currency' => 'VND', 'calculation_version' => 4,
            'status' => 'draft', 'total_debit_minor' => 0, 'total_credit_minor' => 0, 'line_count' => 0,
            'idempotency_key' => (string) Str::uuid(), 'version' => 1, 'created_by' => 9001,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function withoutForeignKeys(callable $callback): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            $callback();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function row(string $table, int $id): stdClass
    {
        $row = DB::table($table)->find($id);
        self::assertInstanceOf(stdClass::class, $row);

        return $row;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        self::fail('Expected an integer-compatible database value.');
    }

    private function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        self::fail('Expected a string database value.');
    }
}
