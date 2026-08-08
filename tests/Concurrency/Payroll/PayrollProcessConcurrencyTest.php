<?php

namespace Tests\Concurrency\Payroll;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Application\Commands\CreatePayrollAdjustment\CreatePayrollAdjustmentCommand;
use Modules\Payroll\Application\Commands\ProcessPayrollAdjustment\ProcessPayrollAdjustmentCommand;
use Modules\Payroll\Domain\Enums\PayrollRunStatus;
use Modules\Payroll\Domain\Services\PayrollAccountingJournalService;
use Modules\Payroll\Domain\Services\PayrollAdjustmentDownstreamService;
use Modules\Payroll\Domain\Services\PayrollAdjustmentManagementService;
use Modules\Payroll\Domain\Services\PayrollPaymentBatchService;
use Modules\Payroll\Domain\Services\PayrollRunService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('payroll-mysql-concurrency')]
final class PayrollProcessConcurrencyTest extends TestCase
{
    public function test_adjustment_creation_race_persists_one_active_intent(): void
    {
        $fixture = $this->approvedFixture();
        app(PayrollRunService::class)->transition($fixture['company'], $fixture['run'], PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $fixture['approver']);
        $item = $this->intValue(DB::table('payroll_items')->where('payroll_run_id', $fixture['run'])->value('id'));
        $reason = (int) DB::table('payroll_adjustment_reasons')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => 'RACE-'.$fixture['token'], 'name' => 'Concurrent correction', 'category' => 'earning_correction', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        try {
            $base = ['company_id' => $fixture['company'], 'item_id' => $item, 'reason_id' => $reason, 'actor_id' => $fixture['actor'], 'effective_date' => '2026-07-31'];
            $race = (new MysqlProcessRaceRunner)->run('payroll_adjustment_create', $base + ['idempotency_key' => (string) Str::uuid()], $base + ['idempotency_key' => (string) Str::uuid()]);
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_adjustment_requests')->where('company_id', $fixture['company'])->count());
            self::assertSame(1, DB::table('payroll_adjustment_snapshots')->where('company_id', $fixture['company'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_accounting_journal_creation_race_persists_one_active_intent(): void
    {
        $fixture = $this->accountingJournalFixture();
        try {
            $base = ['company_id' => $fixture['company'], 'run_id' => $fixture['run'], 'actor_id' => $fixture['actor'], 'posting_date' => '2026-07-31'];
            $race = (new MysqlProcessRaceRunner)->run('payroll_accounting_journal_create', $base + ['idempotency_key' => (string) Str::uuid()], $base + ['idempotency_key' => (string) Str::uuid()]);
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_accounting_journals')->where('company_id', $fixture['company'])->count());
            self::assertSame(2, DB::table('payroll_accounting_journal_lines')->where('company_id', $fixture['company'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_accounting_approve_reject_race_commits_one_transition(): void
    {
        $fixture = $this->accountingJournalFixture(true);
        try {
            $base = ['company_id' => $fixture['company'], 'journal_id' => $fixture['journal']];
            $race = (new MysqlProcessRaceRunner)->run('payroll_accounting_journal_approve', $base + ['actor_id' => $fixture['approver']], $base + ['actor_id' => $fixture['reviewer'], 'reason' => 'Concurrent reject'], 'payroll_accounting_journal_reject');
            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertContains(DB::table('payroll_accounting_journals')->where('id', $fixture['journal'])->value('status'), ['approved', 'rejected']);
            self::assertSame(1, DB::table('activity_log')->where('log_name', 'payroll-accounting')->where('properties->journal_id', $fixture['journal'])->whereIn('event', ['approved', 'rejected'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_overlapping_assignments_commit_exactly_once(): void
    {
        $fixture = $this->fixture();
        try {
            $component = (int) DB::table('salary_components')->insertGetId(['company_id' => $fixture['company'], 'code' => 'BASE-'.$fixture['token'], 'name' => 'Base salary', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'status' => 'active', 'version' => 1, 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
            $base = ['company_id' => $fixture['company'], 'employee_id' => $fixture['employee'], 'component_id' => $component, 'actor_id' => $fixture['actor'], 'amount_minor' => 1000000, 'currency_code' => 'VND', 'effective_from' => '2026-07-01', 'effective_to' => '2026-12-31'];
            $race = (new MysqlProcessRaceRunner)->run('payroll_assignment_overlap', $base + ['idempotency_key' => (string) Str::uuid()], $base + ['idempotency_key' => (string) Str::uuid()]);

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('employee_salary_component_assignments')->where('company_id', $fixture['company'])->count());
            self::assertSame(1, DB::table('activity_log')->where('log_name', 'payroll-configuration')->where('properties->company_id', $fixture['company'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_same_period_run_creation_is_serialized(): void
    {
        $fixture = $this->fixture();
        try {
            $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $fixture['company'], 'code' => 'P-'.$fixture['token'], 'name' => 'July', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $fixture['company'], 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $fixture['actor'], 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $base = ['company_id' => $fixture['company'], 'period_id' => $period, 'snapshot_id' => $snapshot, 'actor_id' => $fixture['actor'], 'currency_code' => 'VND'];
            $race = (new MysqlProcessRaceRunner)->run('payroll_create_same_period', $base + ['run_number' => 'RUN-A-'.$fixture['token'], 'idempotency_key' => (string) Str::uuid()], $base + ['run_number' => 'RUN-B-'.$fixture['token'], 'idempotency_key' => (string) Str::uuid()]);

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_runs')->where('company_id', $fixture['company'])->where('payroll_period_id', $period)->count());
            self::assertSame(1, DB::table('payroll_run_histories')->where('company_id', $fixture['company'])->where('action', 'created')->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_calculate_race_persists_one_consistent_calculation_set(): void
    {
        $fixture = $this->calculationFixture();
        try {
            $base = ['company_id' => $fixture['company'], 'run_id' => $fixture['run'], 'actor_id' => $fixture['actor'], 'expected_version' => 1];
            $race = (new MysqlProcessRaceRunner)->run('payroll_calculate', $base + ['idempotency_key' => (string) Str::uuid()], $base + ['idempotency_key' => (string) Str::uuid()]);

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('calculated', DB::table('payroll_runs')->where('id', $fixture['run'])->value('status'));
            self::assertSame(2, $this->intValue(DB::table('payroll_runs')->where('id', $fixture['run'])->value('version')));
            self::assertSame(1, $this->intValue(DB::table('payroll_runs')->where('id', $fixture['run'])->value('calculation_version')));
            self::assertSame(1, DB::table('payroll_items')->where('payroll_run_id', $fixture['run'])->count());
            self::assertSame(1, DB::table('payroll_item_components')->where('payroll_run_id', $fixture['run'])->count());
            self::assertSame(1, DB::table('payroll_run_histories')->where('payroll_run_id', $fixture['run'])->where('action', 'calculated')->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_submit_and_recalculate_race_preserves_consistent_items(): void
    {
        $fixture = $this->calculatedFixture();
        try {
            $base = ['company_id' => $fixture['company'], 'run_id' => $fixture['run'], 'actor_id' => $fixture['actor'], 'expected_version' => 2];
            $race = (new MysqlProcessRaceRunner)->run('payroll_submit', $base + ['idempotency_key' => (string) Str::uuid()], $base + ['idempotency_key' => (string) Str::uuid()], 'payroll_recalculate');

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertContains(DB::table('payroll_runs')->where('id', $fixture['run'])->value('status'), ['submitted', 'calculated']);
            self::assertSame(1, DB::table('payroll_items')->where('payroll_run_id', $fixture['run'])->count());
            self::assertSame(1, DB::table('payroll_item_components')->where('payroll_run_id', $fixture['run'])->count());
            self::assertSame($this->intValue(DB::table('payroll_runs')->where('id', $fixture['run'])->value('calculation_version')), $this->intValue(DB::table('payroll_items')->where('payroll_run_id', $fixture['run'])->value('calculation_version')));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_approve_and_reject_race_has_one_terminal_transition(): void
    {
        $fixture = $this->submittedFixture();
        try {
            $base = ['company_id' => $fixture['company'], 'run_id' => $fixture['run'], 'expected_version' => 3];
            $race = (new MysqlProcessRaceRunner)->run('payroll_approve', $base + ['actor_id' => $fixture['approver'], 'idempotency_key' => (string) Str::uuid()], $base + ['actor_id' => $fixture['reviewer'], 'idempotency_key' => (string) Str::uuid()], 'payroll_reject');

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            $run = DB::table('payroll_runs')->where('id', $fixture['run'])->first();
            self::assertContains($run?->status, ['approved', 'rejected']);
            self::assertSame(4, $this->intValue($run?->version));
            self::assertNotSame($run?->approved_at !== null, $run?->rejected_at !== null);
            self::assertSame(1, DB::table('payroll_run_histories')->where('payroll_run_id', $fixture['run'])->whereIn('action', ['approved', 'rejected'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_finalize_races_do_not_change_calculation_rows(): void
    {
        foreach ([['payroll_finalize', 'payroll_recalculate'], ['payroll_finalize', 'payroll_finalize']] as [$scenarioA, $scenarioB]) {
            $fixture = $this->approvedFixture();
            try {
                $beforeItems = DB::table('payroll_items')->where('payroll_run_id', $fixture['run'])->orderBy('id')->get()->toJson();
                $beforeComponents = DB::table('payroll_item_components')->where('payroll_run_id', $fixture['run'])->orderBy('id')->get()->toJson();
                $base = ['company_id' => $fixture['company'], 'run_id' => $fixture['run'], 'expected_version' => 4, 'idempotency_key' => (string) Str::uuid()];
                $race = (new MysqlProcessRaceRunner)->run($scenarioA, $base + ['actor_id' => $fixture['approver']], $base + ['actor_id' => $fixture['reviewer'], 'idempotency_key' => (string) Str::uuid()], $scenarioB);

                self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
                self::assertSame('finalized', DB::table('payroll_runs')->where('id', $fixture['run'])->value('status'));
                self::assertSame($beforeItems, DB::table('payroll_items')->where('payroll_run_id', $fixture['run'])->orderBy('id')->get()->toJson());
                self::assertSame($beforeComponents, DB::table('payroll_item_components')->where('payroll_run_id', $fixture['run'])->orderBy('id')->get()->toJson());
                self::assertSame(1, DB::table('payroll_run_histories')->where('payroll_run_id', $fixture['run'])->where('action', 'finalized')->count());
            } finally {
                $this->cleanup($fixture);
            }
        }
    }

    public function test_payroll_notification_delivery_race_is_idempotent_per_channel(): void
    {
        $fixture = $this->fixture();
        try {
            $payload = ['company_id' => $fixture['company'], 'recipient_id' => $fixture['actor'], 'notification_type' => 'payroll_run', 'entity_type' => 'payroll_run', 'entity_id' => 900001, 'action' => 'submitted', 'version' => 3];
            $race = (new MysqlProcessRaceRunner)->run('payroll_notification_delivery', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(2, DB::table('notification_deliveries')->where('company_id', $fixture['company'])->where('recipient_id', $fixture['actor'])->count());
            self::assertSame(['broadcast', 'database'], DB::table('notification_deliveries')->where('company_id', $fixture['company'])->orderBy('channel')->pluck('channel')->all());
            self::assertSame(1, DB::table('notifications')->where('notifiable_id', $fixture['actor'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_payment_batch_creation_race_persists_one_active_batch_and_item(): void
    {
        $fixture = $this->approvedFixture();
        try {
            app(PayrollRunService::class)->transition($fixture['company'], $fixture['run'], PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $fixture['approver']);
            DB::table('employee_payment_accounts')->insert([
                'uuid' => (string) Str::uuid(),
                'company_id' => $fixture['company'],
                'employee_id' => $fixture['employee'],
                'payment_method' => 'bank_transfer',
                'bank_code' => 'SCHEMA-BANK',
                'bank_name' => 'Schema Internal Bank',
                'branch_code' => 'HCM',
                'account_number_encrypted' => encrypt('1234567890'),
                'account_number_last4' => '7890',
                'account_holder_name' => 'Payroll Race Employee',
                'currency' => 'VND',
                'is_primary' => true,
                'verification_status' => 'verified',
                'verified_by' => $fixture['approver'],
                'verified_at' => now(),
                'effective_from' => '2026-01-01',
                'status' => 'active',
                'created_by' => $fixture['actor'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $base = ['company_id' => $fixture['company'], 'run_id' => $fixture['run'], 'actor_id' => $fixture['actor']];
            $race = (new MysqlProcessRaceRunner)->run(
                'payroll_payment_batch_create',
                $base + ['idempotency_key' => (string) Str::uuid()],
                $base + ['idempotency_key' => (string) Str::uuid()],
            );

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_payment_batches')->where('company_id', $fixture['company'])->where('payroll_run_id', $fixture['run'])->count());
            self::assertSame(1, DB::table('payroll_payment_batch_items')->where('company_id', $fixture['company'])->count());
            self::assertSame(1, DB::table('activity_log')->where('log_name', 'payroll-payment')->where('properties->company_id', $fixture['company'])->where('event', 'created')->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_payment_batch_approve_and_reject_race_commits_one_transition(): void
    {
        $fixture = $this->paymentBatchFixture();
        try {
            $base = ['company_id' => $fixture['company'], 'batch_id' => $fixture['batch']];
            $race = (new MysqlProcessRaceRunner)->run(
                'payroll_payment_batch_approve',
                $base + ['actor_id' => $fixture['approver']],
                $base + ['actor_id' => $fixture['reviewer'], 'reason' => 'Checker rejected the instruction.'],
                'payroll_payment_batch_reject',
            );

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertContains(DB::table('payroll_payment_batches')->where('id', $fixture['batch'])->value('status'), ['approved', 'rejected']);
            self::assertSame(1, DB::table('activity_log')->where('log_name', 'payroll-payment')->where('properties->payment_batch_id', $fixture['batch'])->whereIn('event', ['approved', 'rejected'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_payment_batch_duplicate_approve_and_release_are_idempotent(): void
    {
        foreach (['approve', 'release'] as $action) {
            $fixture = $this->paymentBatchFixture($action === 'release');
            try {
                $base = ['company_id' => $fixture['company'], 'batch_id' => $fixture['batch']];
                $scenario = 'payroll_payment_batch_'.$action;
                $race = (new MysqlProcessRaceRunner)->run($scenario, $base + ['actor_id' => $fixture['approver']], $base + ['actor_id' => $fixture['reviewer']]);

                self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
                self::assertSame($action === 'approve' ? 'approved' : 'released', DB::table('payroll_payment_batches')->where('id', $fixture['batch'])->value('status'));
                self::assertSame(1, DB::table('activity_log')->where('log_name', 'payroll-payment')->where('properties->payment_batch_id', $fixture['batch'])->where('event', $action === 'approve' ? 'approved' : 'released')->count());
                if ($action === 'release') {
                    self::assertSame(1, DB::table('payroll_payment_access_logs')->where('payment_batch_id', $fixture['batch'])->where('action', 'release_file')->count());
                }
            } finally {
                $this->cleanup($fixture);
            }
        }
    }

    public function test_payment_batch_release_beats_invalid_reject_without_duplicate_file(): void
    {
        $fixture = $this->paymentBatchFixture(true);
        try {
            $base = ['company_id' => $fixture['company'], 'batch_id' => $fixture['batch']];
            $race = (new MysqlProcessRaceRunner)->run(
                'payroll_payment_batch_release',
                $base + ['actor_id' => $fixture['approver']],
                $base + ['actor_id' => $fixture['reviewer'], 'reason' => 'Too late to reject approved batch.'],
                'payroll_payment_batch_reject',
            );

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('released', DB::table('payroll_payment_batches')->where('id', $fixture['batch'])->value('status'));
            self::assertSame(1, DB::table('payroll_payment_access_logs')->where('payment_batch_id', $fixture['batch'])->where('action', 'release_file')->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_adjustment_approve_approve_race_persists_one_transition(): void
    {
        $fixture = $this->adjustmentFixture('reviewed');
        try {
            $base = ['company_id' => $fixture['company'], 'adjustment_id' => $fixture['adjustment'], 'action' => 'approve'];
            $race = (new MysqlProcessRaceRunner)->run('payroll_adjustment_process', $base + ['actor_id' => $fixture['approver']], $base + ['actor_id' => $fixture['reviewer']]);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('approved', DB::table('payroll_adjustment_requests')->where('id', $fixture['adjustment'])->value('status'));
            self::assertSame(1, DB::table('payroll_adjustment_histories')->where('adjustment_request_id', $fixture['adjustment'])->where('action', 'approved')->count());
            self::assertNotNull(DB::table('payroll_adjustment_requests')->where('id', $fixture['adjustment'])->value('approved_by'));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_adjustment_approve_reject_race_has_one_terminal_transition(): void
    {
        $fixture = $this->adjustmentFixture('reviewed');
        try {
            $base = ['company_id' => $fixture['company'], 'adjustment_id' => $fixture['adjustment']];
            $race = (new MysqlProcessRaceRunner)->run(
                'payroll_adjustment_process',
                $base + ['actor_id' => $fixture['approver'], 'action' => 'approve'],
                $base + ['actor_id' => $fixture['reviewer'], 'action' => 'reject', 'reason' => 'Concurrent checker rejection'],
            );

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            $row = DB::table('payroll_adjustment_requests')->where('id', $fixture['adjustment'])->first();
            self::assertContains($row?->status, ['approved', 'rejected']);
            self::assertSame(1, DB::table('payroll_adjustment_histories')->where('adjustment_request_id', $fixture['adjustment'])->whereIn('action', ['approved', 'rejected'])->count());
            self::assertNotSame($row?->approved_at !== null, $row?->rejected_at !== null);
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_adjustment_apply_apply_race_creates_one_run_and_intent_set(): void
    {
        $fixture = $this->adjustmentFixture('approved');
        try {
            $base = ['company_id' => $fixture['company'], 'adjustment_id' => $fixture['adjustment'], 'actor_id' => $fixture['approver'], 'action' => 'apply'];
            $race = (new MysqlProcessRaceRunner)->run('payroll_adjustment_process', $base, $base + ['actor_id' => $fixture['reviewer']]);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('applied', DB::table('payroll_adjustment_requests')->where('id', $fixture['adjustment'])->value('status'));
            self::assertSame(1, DB::table('payroll_adjustment_runs')->where('adjustment_request_id', $fixture['adjustment'])->count());
            self::assertSame(3, DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $fixture['adjustment'])->count());
            self::assertSame(1, DB::table('payroll_adjustment_histories')->where('adjustment_request_id', $fixture['adjustment'])->where('action', 'applied')->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_adjustment_apply_reject_race_cannot_leave_orphans(): void
    {
        $fixture = $this->adjustmentFixture('approved');
        try {
            $base = ['company_id' => $fixture['company'], 'adjustment_id' => $fixture['adjustment']];
            $race = (new MysqlProcessRaceRunner)->run(
                'payroll_adjustment_process',
                $base + ['actor_id' => $fixture['approver'], 'action' => 'apply'],
                $base + ['actor_id' => $fixture['reviewer'], 'action' => 'reject', 'reason' => 'Late rejection'],
            );

            self::assertSame([0, 2], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame('applied', DB::table('payroll_adjustment_requests')->where('id', $fixture['adjustment'])->value('status'));
            self::assertSame(1, DB::table('payroll_adjustment_runs')->where('adjustment_request_id', $fixture['adjustment'])->count());
            self::assertSame(3, DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $fixture['adjustment'])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_positive_adjustment_payment_create_race_creates_one_item_and_no_orphan_batch(): void
    {
        $fixture = $this->adjustmentFixture('applied');
        $this->adjustmentPaymentAccount($fixture);
        try {
            $race = $this->downstreamRace($fixture, 'payment');
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_payment_batches')->where('company_id', $fixture['company'])->count());
            self::assertSame(1, DB::table('payroll_payment_batch_items')->where('adjustment_request_id', $fixture['adjustment'])->count());
            $item = DB::table('payroll_payment_batch_items')->where('adjustment_request_id', $fixture['adjustment'])->first();
            self::assertNotNull($item);
            self::assertSame('retroactive_adjustment', $item->source_type);
            self::assertNull($item->payroll_item_id);
            self::assertSame(200000, $this->intValue($item->amount));
            self::assertSame(1, DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $fixture['adjustment'])->whereIn('intent_type', ['payment', 'payment_eligible'])->where('status', 'completed')->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_negative_adjustment_payment_race_creates_no_transfer(): void
    {
        $fixture = $this->adjustmentFixture('applied', 800000);
        try {
            $race = $this->downstreamRace($fixture, 'payment');
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(0, DB::table('payroll_payment_batches')->where('company_id', $fixture['company'])->count());
            self::assertSame(0, DB::table('payroll_payment_batch_items')->where('company_id', $fixture['company'])->count());
            self::assertSame(1, DB::table('payroll_adjustment_recoveries')->where('adjustment_request_id', $fixture['adjustment'])->count());
            self::assertSame('not_applicable', DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $fixture['adjustment'])->where('intent_type', 'payment')->value('status'));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_adjustment_accounting_create_race_creates_one_balanced_journal(): void
    {
        $fixture = $this->adjustmentFixture('applied');
        $this->adjustmentAccountingMappings($fixture);
        try {
            $race = $this->downstreamRace($fixture, 'accounting');
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            self::assertSame(1, DB::table('payroll_accounting_journals')->where('adjustment_request_id', $fixture['adjustment'])->count());
            $journal = DB::table('payroll_accounting_journals')->where('adjustment_request_id', $fixture['adjustment'])->first();
            self::assertNotNull($journal);
            self::assertSame($this->intValue($journal->total_debit_minor), $this->intValue($journal->total_credit_minor));
            self::assertGreaterThan(0, $this->intValue($journal->total_debit_minor));
            self::assertSame(0, DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->where(fn (Builder $query): Builder => $query->where('debit_minor', '<', 0)->orWhere('credit_minor', '<', 0))->count());
            self::assertSame(DB::table('payroll_accounting_journal_lines')->where('journal_id', $journal->id)->count(), DB::table('payroll_accounting_journal_sources')->where('journal_id', $journal->id)->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_adjustment_statement_generate_race_creates_one_integral_pdf(): void
    {
        $fixture = $this->adjustmentFixture('applied');
        try {
            $race = $this->downstreamRace($fixture, 'statement');
            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            $statement = DB::table('payroll_adjustment_payslips')->where('adjustment_request_id', $fixture['adjustment'])->first();
            self::assertNotNull($statement);
            self::assertNotNull($statement->storage_path);
            self::assertSame('application/pdf', $statement->mime_type);
            $path = is_string($statement->storage_path) ? $statement->storage_path : '';
            self::assertTrue(Storage::disk('local')->exists($path));
            $bytes = Storage::disk('local')->get($path);
            self::assertIsString($bytes);
            self::assertStringStartsWith('%PDF-', $bytes);
            self::assertSame(strlen($bytes), $this->intValue($statement->file_size));
            self::assertSame(hash('sha256', $bytes), $statement->checksum);
            self::assertSame(1, count(Storage::disk('local')->files('payroll-adjustments/'.$fixture['company'].'/'.$fixture['adjustment'])));
        } finally {
            $this->cleanup($fixture);
        }
    }

    public function test_completed_adjustment_downstream_retry_races_return_existing_artifacts(): void
    {
        $fixture = $this->adjustmentFixture('applied');
        $this->adjustmentPaymentAccount($fixture);
        $this->adjustmentAccountingMappings($fixture);
        try {
            app(PayrollAdjustmentDownstreamService::class)->executeAll($fixture['company'], $fixture['adjustment'], $fixture['approver']);
            $before = DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $fixture['adjustment'])->orderBy('id')->get(['intent_type', 'attempt_count', 'payment_batch_item_id', 'accounting_journal_id', 'adjustment_statement_id'])->toJson();
            $statement = DB::table('payroll_adjustment_payslips')->where('adjustment_request_id', $fixture['adjustment'])->first();
            $checksum = $statement?->checksum;

            foreach (['payment', 'accounting', 'statement'] as $type) {
                $race = $this->downstreamRace($fixture, $type);
                self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            }

            self::assertSame($before, DB::table('payroll_adjustment_downstream_intents')->where('adjustment_request_id', $fixture['adjustment'])->orderBy('id')->get(['intent_type', 'attempt_count', 'payment_batch_item_id', 'accounting_journal_id', 'adjustment_statement_id'])->toJson());
            self::assertSame(1, DB::table('payroll_payment_batch_items')->where('adjustment_request_id', $fixture['adjustment'])->count());
            self::assertSame(1, DB::table('payroll_accounting_journals')->where('adjustment_request_id', $fixture['adjustment'])->count());
            self::assertSame(1, DB::table('payroll_adjustment_payslips')->where('adjustment_request_id', $fixture['adjustment'])->count());
            self::assertSame($checksum, DB::table('payroll_adjustment_payslips')->where('adjustment_request_id', $fixture['adjustment'])->value('checksum'));
        } finally {
            $this->cleanup($fixture);
        }
    }

    /** @return array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string} */
    private function fixture(): array
    {
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
        $token = Str::upper(Str::random(8));
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PCR-'.$token, 'name' => 'Payroll Race '.$token, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $actor = User::factory()->create();
        $approver = User::factory()->create();
        $reviewer = User::factory()->create();
        foreach ([$actor, $approver, $reviewer] as $user) {
            DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        }
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $actor->id, 'employee_code' => 'PCR-'.$token, 'full_name' => 'Payroll Race Employee', 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return ['company' => (int) $company->id, 'employee' => (int) $employee->id, 'actor' => (int) $actor->id, 'approver' => (int) $approver->id, 'reviewer' => (int) $reviewer->id, 'token' => $token];
    }

    /** @return array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string,period:int,snapshot:int,run:int,adjustment:int} */
    private function adjustmentFixture(string $status, int $corrected = 1200000): array
    {
        $fixture = $this->approvedFixture();
        app(PayrollRunService::class)->transition($fixture['company'], $fixture['run'], PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $fixture['approver']);
        $item = $this->intValue(DB::table('payroll_items')->where('payroll_run_id', $fixture['run'])->value('id'));
        $reason = (int) DB::table('payroll_adjustment_reasons')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => 'C3-'.$fixture['token'], 'name' => 'Concurrent adjustment', 'category' => 'earning_correction', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        $service = app(PayrollAdjustmentManagementService::class);
        $adjustment = $service->create(new CreatePayrollAdjustmentCommand($fixture['company'], $item, $reason, 'earning_correction', '2026-07-31', $fixture['period'], 'C3 process-race fixture', null, (string) Str::uuid(), $fixture['actor']));
        $service->process(new ProcessPayrollAdjustmentCommand($fixture['company'], $adjustment, 'calculate', $fixture['actor'], null, $corrected, 0, 0, 0, $corrected));
        foreach (['validate', 'submit', 'review'] as $action) {
            $service->process(new ProcessPayrollAdjustmentCommand($fixture['company'], $adjustment, $action, $fixture['actor']));
        }
        if (in_array($status, ['approved', 'applied'], true)) {
            $service->process(new ProcessPayrollAdjustmentCommand($fixture['company'], $adjustment, 'approve', $fixture['approver']));
        }
        if ($status === 'applied') {
            $service->process(new ProcessPayrollAdjustmentCommand($fixture['company'], $adjustment, 'apply', $fixture['approver']));
        }

        return $fixture + ['adjustment' => $adjustment];
    }

    /**
     * @param  array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string,adjustment:int}  $fixture
     * @return array{exit_codes:list<int>,outputs:list<string>}
     */
    private function downstreamRace(array $fixture, string $type): array
    {
        $payload = ['company_id' => $fixture['company'], 'adjustment_id' => $fixture['adjustment'], 'actor_id' => $fixture['approver'], 'type' => $type];

        return (new MysqlProcessRaceRunner)->run('payroll_adjustment_downstream', $payload, array_replace($payload, ['actor_id' => $fixture['reviewer']]));
    }

    /** @param array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string} $fixture */
    private function adjustmentPaymentAccount(array $fixture): void
    {
        DB::table('employee_payment_accounts')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'employee_id' => $fixture['employee'], 'payment_method' => 'bank_transfer', 'bank_code' => 'SCHEMA', 'bank_name' => 'Schema Bank', 'branch_code' => 'HCM', 'account_number_encrypted' => encrypt('001234567890'), 'account_number_last4' => '7890', 'account_holder_name' => 'C3 PAYEE', 'currency' => 'VND', 'is_primary' => true, 'verification_status' => 'verified', 'verified_by' => $fixture['approver'], 'verified_at' => now(), 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string} $fixture */
    private function adjustmentAccountingMappings(array $fixture): void
    {
        $expense = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => 'C3-6100-'.$fixture['token'], 'name' => 'Adjustment expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        $payable = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => 'C3-3340-'.$fixture['token'], 'name' => 'Adjustment payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        $policy = (int) DB::table('payroll_accounting_policies')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => 'C3-'.$fixture['token'], 'name' => 'C3 Adjustment policy', 'version' => 1, 'currency' => 'VND', 'aggregation_strategy' => 'employee', 'effective_from' => '2026-01-01', 'rounding_rule' => 'minor_unit', 'status' => 'active', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['adjustment_gross', 'GROSS_ADJUSTMENT', $expense, null], ['adjustment_net_pay', 'NET_PAY_ADJUSTMENT', null, $payable]] as [$type, $code, $debit, $credit]) {
            DB::table('payroll_accounting_mappings')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'accounting_policy_id' => $policy, 'source_type' => $type, 'source_code' => $code, 'debit_account_id' => $debit, 'credit_account_id' => $credit, 'priority' => 100, 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @return array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string,period:int,snapshot:int,run:int} */
    private function calculationFixture(): array
    {
        $fixture = $this->fixture();
        $period = (int) DB::table('payroll_periods')->insertGetId(['company_id' => $fixture['company'], 'code' => 'CAL-'.$fixture['token'], 'name' => 'July', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cutoff_at' => '2026-08-01', 'payment_date' => '2026-08-05', 'status' => 'locked', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = (int) DB::table('payroll_input_snapshots')->insertGetId(['company_id' => $fixture['company'], 'payroll_period_id' => $period, 'snapshot_version' => 1, 'status' => 'locked', 'generated_by' => $fixture['actor'], 'generated_at' => now(), 'locked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payroll_employee_inputs')->insert(['company_id' => $fixture['company'], 'payroll_input_snapshot_id' => $snapshot, 'employee_id' => $fixture['employee'], 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'paid_leave_minutes' => 0, 'unpaid_leave_minutes' => 0, 'approved_overtime_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'absence_minutes' => 0, 'fte_basis_points' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $component = (int) DB::table('salary_components')->insertGetId(['company_id' => $fixture['company'], 'code' => 'FIX-'.$fixture['token'], 'name' => 'Fixed salary', 'category' => 'earning', 'calculation_type' => 'fixed', 'value_type' => 'money', 'currency_code' => 'VND', 'affects_gross' => true, 'affects_net' => true, 'status' => 'active', 'version' => 1, 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_salary_component_assignments')->insert(['company_id' => $fixture['company'], 'employee_id' => $fixture['employee'], 'salary_component_id' => $component, 'amount_minor' => 1000000, 'currency_code' => 'VND', 'effective_from' => '2026-01-01', 'source' => 'manual', 'status' => 'active', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        $run = app(PayrollRunService::class)->create($fixture['company'], $period, $snapshot, 'CAL-RUN-'.$fixture['token'], 'VND', (string) Str::uuid(), $fixture['actor']);

        return $fixture + ['period' => $period, 'snapshot' => $snapshot, 'run' => (int) $run->id];
    }

    /** @return array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string,period:int,snapshot:int,run:int} */
    private function calculatedFixture(): array
    {
        $fixture = $this->calculationFixture();
        app(PayrollRunService::class)->calculate($fixture['company'], $fixture['run'], 1, (string) Str::uuid(), $fixture['actor']);

        return $fixture;
    }

    /** @return array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string,period:int,snapshot:int,run:int} */
    private function submittedFixture(): array
    {
        $fixture = $this->calculatedFixture();
        app(PayrollRunService::class)->transition($fixture['company'], $fixture['run'], PayrollRunStatus::Submitted, 2, (string) Str::uuid(), $fixture['actor']);

        return $fixture;
    }

    /** @return array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string,period:int,snapshot:int,run:int} */
    private function approvedFixture(): array
    {
        $fixture = $this->submittedFixture();
        app(PayrollRunService::class)->transition($fixture['company'], $fixture['run'], PayrollRunStatus::Approved, 3, (string) Str::uuid(), $fixture['approver']);

        return $fixture;
    }

    /** @return array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string,period:int,snapshot:int,run:int,journal:int} */
    private function accountingJournalFixture(bool $reviewed = false): array
    {
        $fixture = $this->approvedFixture();
        app(PayrollRunService::class)->transition($fixture['company'], $fixture['run'], PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $fixture['approver']);
        $expense = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => '6100-'.$fixture['token'], 'name' => 'Payroll expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        $payable = (int) DB::table('accounting_accounts')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => '3340-'.$fixture['token'], 'name' => 'Payroll payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'currency' => 'VND', 'allow_posting' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        $policy = (int) DB::table('payroll_accounting_policies')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'code' => 'POL-'.$fixture['token'], 'name' => 'Race policy', 'version' => 1, 'currency' => 'VND', 'aggregation_strategy' => 'per_account_and_cost_center', 'effective_from' => '2026-01-01', 'rounding_rule' => 'half_up', 'status' => 'active', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['earning_component', 'FIX-'.$fixture['token'], $expense, null], ['net_pay', 'NET_PAY', null, $payable]] as [$type, $code, $debit, $credit]) {
            DB::table('payroll_accounting_mappings')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'accounting_policy_id' => $policy, 'source_type' => $type, 'source_code' => $code, 'debit_account_id' => $debit, 'credit_account_id' => $credit, 'priority' => 100, 'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $fixture['actor'], 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($reviewed) {
            $service = app(PayrollAccountingJournalService::class);
            $journal = $service->create($fixture['company'], $fixture['run'], '2026-07-31', $fixture['actor'], (string) Str::uuid());
            $service->validate($fixture['company'], $journal, $fixture['actor']);
            $service->review($fixture['company'], $journal, $fixture['actor']);

            return $fixture + ['journal' => $journal];
        }

        return $fixture + ['journal' => 0];
    }

    /** @return array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string,period:int,snapshot:int,run:int,batch:int} */
    private function paymentBatchFixture(bool $approved = false): array
    {
        $fixture = $this->approvedFixture();
        app(PayrollRunService::class)->transition($fixture['company'], $fixture['run'], PayrollRunStatus::Finalized, 4, (string) Str::uuid(), $fixture['approver']);
        DB::table('employee_payment_accounts')->insert([
            'uuid' => (string) Str::uuid(), 'company_id' => $fixture['company'], 'employee_id' => $fixture['employee'],
            'payment_method' => 'bank_transfer', 'bank_code' => 'SCHEMA-BANK', 'bank_name' => 'Schema Internal Bank',
            'branch_code' => 'HCM', 'account_number_encrypted' => encrypt('1234567890'), 'account_number_last4' => '7890',
            'account_holder_name' => 'Payroll Race Employee', 'currency' => 'VND', 'is_primary' => true,
            'verification_status' => 'verified', 'verified_by' => $fixture['approver'], 'verified_at' => now(),
            'effective_from' => '2026-01-01', 'status' => 'active', 'created_by' => $fixture['actor'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(PayrollPaymentBatchService::class);
        $batch = $service->create($fixture['company'], $fixture['run'], $fixture['actor'], (string) Str::uuid());
        $service->validate($fixture['company'], $batch, $fixture['actor']);
        $service->review($fixture['company'], $batch, $fixture['actor']);
        if ($approved) {
            $service->approve($fixture['company'], $batch, $fixture['approver']);
        }

        return $fixture + ['batch' => $batch];
    }

    /** @param array{company:int,employee:int,actor:int,approver:int,reviewer:int,token:string} $fixture */
    private function cleanup(array $fixture): void
    {
        $statementPaths = DB::table('payroll_adjustment_payslips')->where('company_id', $fixture['company'])->whereNotNull('storage_path')->pluck('storage_path');
        foreach ($statementPaths as $statementPath) {
            if (is_string($statementPath)) {
                Storage::disk('local')->delete($statementPath);
            }
        }
        $paths = DB::table('payroll_payment_batches')->where('company_id', $fixture['company'])->whereNotNull('storage_path')->pluck('storage_path');
        foreach ($paths as $path) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
        }
        foreach (['payroll_adjustment_access_logs', 'payroll_adjustment_histories', 'payroll_adjustment_downstream_intents', 'payroll_adjustment_payslips', 'payroll_accounting_access_logs', 'payroll_accounting_validation_issues', 'payroll_accounting_journal_sources', 'payroll_accounting_journal_lines', 'payroll_accounting_journals', 'payroll_payment_access_logs', 'payroll_payment_validation_issues', 'payroll_payment_batch_items', 'payroll_payment_batches'] as $table) {
            if (Schema::hasColumn($table, 'company_id')) {
                DB::table($table)->where('company_id', $fixture['company'])->delete();
            }
        }
        foreach (['payroll_adjustment_recoveries', 'payroll_adjustment_validation_issues', 'payroll_adjustment_components', 'payroll_adjustment_snapshots', 'payroll_adjustment_runs', 'payroll_adjustment_requests', 'payroll_adjustment_reasons'] as $adjustmentTable) {
            DB::table($adjustmentTable)->where('company_id', $fixture['company'])->delete();
        }
        foreach (['payroll_accounting_mappings', 'payroll_accounting_policies', 'accounting_accounts', 'employee_payment_accounts', 'notification_deliveries', 'payroll_validation_issues', 'payroll_item_components', 'payroll_items', 'payroll_run_histories', 'payroll_runs', 'employee_salary_component_assignments', 'salary_components', 'payroll_employee_inputs', 'payroll_input_snapshots', 'payroll_periods'] as $table) {
            if (Schema::hasColumn($table, 'company_id')) {
                DB::table($table)->where('company_id', $fixture['company'])->delete();
            }
        }
        DB::transaction(static function () use ($fixture): void {
            DB::table('notifications')->whereIn('notifiable_id', [$fixture['actor'], $fixture['approver'], $fixture['reviewer']])->delete();
        }, 5);
        DB::table('activity_log')->where('properties->company_id', $fixture['company'])->delete();
        DB::table('company_user_access')->where('company_id', $fixture['company'])->delete();
        DB::table('employees')->where('company_id', $fixture['company'])->delete();
        DB::table('users')->whereIn('id', [$fixture['actor'], $fixture['approver'], $fixture['reviewer']])->delete();
        DB::table('companies')->where('id', $fixture['company'])->delete();
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
