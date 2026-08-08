<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Application\Commands\CreatePayrollAdjustment\CreatePayrollAdjustmentCommand;
use Modules\Payroll\Application\Commands\ProcessPayrollAdjustment\ProcessPayrollAdjustmentCommand;
use Modules\Payroll\Application\DTOs\PayrollAdjustmentDeltaResult;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentStatus;
use Modules\Payroll\Domain\Enums\PayrollAdjustmentType;
use Modules\Payroll\Domain\Events\PayrollAdjustmentChanged;

final readonly class PayrollAdjustmentManagementService
{
    public function __construct(private ConnectionInterface $db) {}

    public function create(CreatePayrollAdjustmentCommand $c): int
    {
        return $this->db->transaction(function () use ($c): int {
            $prior = $this->db->table('payroll_adjustment_requests')->where('company_id', $c->companyId)->where('idempotency_key', $c->idempotencyKey)->value('id');
            if (is_numeric($prior)) {
                return (int) $prior;
            }
            $source = $this->db->table('payroll_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')->where('i.company_id', $c->companyId)->where('i.id', $c->sourceItemId)->where('r.status', 'finalized')->lockForUpdate()->select(['i.*', 'r.payroll_period_id', 'r.id as source_run_id', 'r.status as run_status'])->first();
            if (! is_object($source)) {
                throw ValidationException::withMessages(['source_payroll_item_id' => 'ADJUSTMENT_SOURCE_RUN_NOT_FINALIZED']);
            }
            $reason = $this->db->table('payroll_adjustment_reasons')->where('company_id', $c->companyId)->where('id', $c->reasonId)->where('status', 'active')->first();
            if (! is_object($reason)) {
                throw ValidationException::withMessages(['adjustment_reason_id' => 'ADJUSTMENT_REASON_MISSING']);
            }
            if ((bool) ($reason->requires_evidence ?? false) && $c->evidenceDocumentId === null) {
                throw ValidationException::withMessages(['evidence_document_id' => 'ADJUSTMENT_EVIDENCE_REQUIRED']);
            }
            PayrollAdjustmentType::from($c->type);
            $version = $this->i($source, 'calculation_version');
            $intent = $c->sourceItemId.':'.$c->reasonId.':'.$c->type;
            $active = $this->db->table('payroll_adjustment_requests')->where('company_id', $c->companyId)->where('active_intent_key', $intent)->lockForUpdate()->value('id');
            if (is_numeric($active)) {
                throw ValidationException::withMessages(['adjustment' => 'ADJUSTMENT_DUPLICATE_ACTIVE_REQUEST']);
            }
            if ($c->supersedesId !== null) {
                $this->assertSupersede($c->companyId, $c->supersedesId, $c->sourceItemId);
            }
            $id = (int) $this->db->table('payroll_adjustment_requests')->insertGetId(['uuid' => (string) Str::uuid(), 'adjustment_number' => 'ADJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)), 'company_id' => $c->companyId, 'employee_id' => $this->i($source, 'employee_id'), 'source_payroll_period_id' => $this->i($source, 'payroll_period_id'), 'source_payroll_run_id' => $this->i($source, 'source_run_id'), 'source_payroll_item_id' => $c->sourceItemId, 'source_calculation_version' => $version, 'adjustment_reason_id' => $c->reasonId, 'adjustment_type' => $c->type, 'requested_effective_date' => $c->effectiveDate, 'target_payment_period_id' => $c->targetPeriodId, 'status' => PayrollAdjustmentStatus::Draft->value, 'description' => $c->description, 'evidence_document_id' => $c->evidenceDocumentId, 'idempotency_key' => $c->idempotencyKey, 'active_intent_key' => $intent, 'created_by' => $c->actorId, 'supersedes_adjustment_request_id' => $c->supersedesId, 'created_at' => now(), 'updated_at' => now()]);
            $historical = $this->historicalPayload($c->companyId, $source);
            $this->snapshot($c->companyId, $id, 'historical', $historical);
            $this->history($c->companyId, $id, $c->actorId, 'created', null, 'draft', 1, 1, null);
            $this->emit($c->companyId, $id, 'draft', $this->i($source, 'employee_id'));

            return $id;
        }, 3);
    }

    public function process(ProcessPayrollAdjustmentCommand $c): void
    {
        match ($c->action) {
            'calculate' => $this->calculate($c), 'validate' => $this->transition($c, 'calculated', 'validated'), 'submit' => $this->transition($c, 'validated', 'submitted'),
            'review' => $this->transition($c, 'submitted', 'reviewed'), 'approve' => $this->approve($c), 'reject' => $this->reject($c), 'apply' => $this->apply($c),
            default => throw new \InvalidArgumentException('Unsupported Payroll Adjustment action.'),
        };
    }

    private function calculate(ProcessPayrollAdjustmentCommand $c): void
    {
        $this->db->transaction(function () use ($c): void {
            $a = $this->adjustment($c->companyId, $c->adjustmentId, true);
            $status = $this->s($a, 'status');
            if (! in_array($status, ['draft', 'calculated', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'Adjustment cannot be calculated in current state.']);
            }
            foreach ([$c->correctedGross, $c->correctedEmployeeInsurance, $c->correctedEmployerInsurance, $c->correctedTax, $c->correctedNet] as $amount) {
                if ($amount === null) {
                    throw ValidationException::withMessages(['correction' => 'Correction totals are required.']);
                }
            }
            if ($c->correctedGross === null || $c->correctedEmployeeInsurance === null || $c->correctedEmployerInsurance === null || $c->correctedTax === null || $c->correctedNet === null) {
                throw new \LogicException('Correction totals were not normalized.');
            }
            $historical = $this->snapshotPayload($c->adjustmentId, 'historical');
            $current = $this->effectiveTotals($this->i($a, 'source_payroll_item_id'), $historical);
            $corrected = ['gross' => $c->correctedGross, 'employee_insurance' => $c->correctedEmployeeInsurance, 'employer_insurance' => $c->correctedEmployerInsurance, 'tax' => $c->correctedTax, 'net' => $c->correctedNet, 'currency' => $this->historicalCurrency($historical)];
            $this->snapshot($c->companyId, $c->adjustmentId, 'correction', ['policy_semantics' => 'recalculate_under_historical_policy', 'source_calculation_version' => $this->i($a, 'source_calculation_version'), 'totals' => $corrected, 'captured_at' => now()->toIso8601String()]);
            $result = $this->delta($current, $corrected);
            $this->db->table('payroll_adjustment_components')->where('adjustment_request_id', $c->adjustmentId)->delete();
            foreach ($result->components as $line) {
                $this->db->table('payroll_adjustment_components')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $c->companyId, 'adjustment_request_id' => $c->adjustmentId, 'component_code' => $line['code'], 'component_type' => $line['type'], 'historical_amount_minor' => $line['historical'], 'corrected_amount_minor' => $line['corrected'], 'delta_amount_minor' => $line['delta'], 'currency' => $line['currency'], 'taxable' => $line['taxable'], 'insurable' => $line['insurable'], 'employer_cost' => $line['employer_cost'], 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->db->table('payroll_adjustment_validation_issues')->where('adjustment_request_id', $c->adjustmentId)->delete();
            if ($result->gross === 0 && $result->employeeInsurance === 0 && $result->employerInsurance === 0 && $result->tax === 0 && $result->net === 0) {
                $this->issue($c->companyId, $c->adjustmentId, 'ADJUSTMENT_ZERO_DELTA', 'Correction does not produce a delta.', true);
            }
            if ($result->net < 0) {
                $this->issue($c->companyId, $c->adjustmentId, 'ADJUSTMENT_NEGATIVE_NET_REQUIRES_RECOVERY', 'Negative net delta requires recovery and never becomes a negative bank transfer.', false);
            }
            $this->setStatus($a, $c->actorId, 'calculated', 'calculated');
        }, 3);
    }

    private function approve(ProcessPayrollAdjustmentCommand $c): void
    {
        $this->db->transaction(function () use ($c): void {
            $a = $this->adjustment($c->companyId, $c->adjustmentId, true);
            if ($this->i($a, 'created_by') === $c->actorId) {
                throw ValidationException::withMessages(['approval' => 'Maker cannot approve own adjustment.']);
            } $this->transitionLocked($a, $c->actorId, 'reviewed', 'approved');
        }, 3);
    }

    private function reject(ProcessPayrollAdjustmentCommand $c): void
    {
        if (trim((string) $c->reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Reject reason is required.']);
        }
        $this->db->transaction(function () use ($c): void {
            $a = $this->adjustment($c->companyId, $c->adjustmentId, true);
            if (! in_array($this->s($a, 'status'), ['submitted', 'reviewed'], true)) {
                throw ValidationException::withMessages(['status' => 'Invalid rejection transition.']);
            } $this->setStatus($a, $c->actorId, 'rejected', 'rejected', $c->reason, ['rejected_by' => $c->actorId, 'rejected_at' => now(), 'rejection_reason' => $c->reason, 'active_intent_key' => null]);
        }, 3);
    }

    private function apply(ProcessPayrollAdjustmentCommand $c): void
    {
        $this->db->transaction(function () use ($c): void {
            $a = $this->adjustment($c->companyId, $c->adjustmentId, true);
            if ($this->s($a, 'status') === 'applied') {
                return;
            } if ($this->s($a, 'status') !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Only approved adjustment can be applied.']);
            }
            $blocking = $this->db->table('payroll_adjustment_validation_issues')->where('adjustment_request_id', $c->adjustmentId)->where('is_blocking', true)->whereNull('resolved_at')->exists();
            if ($blocking) {
                throw ValidationException::withMessages(['validation' => 'Adjustment has blocking issues.']);
            }
            $tot = $this->componentTotals($c->adjustmentId);
            $run = (int) $this->db->table('payroll_adjustment_runs')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $c->companyId, 'adjustment_request_id' => $c->adjustmentId, 'source_payroll_run_id' => $this->i($a, 'source_payroll_run_id'), 'source_payroll_item_id' => $this->i($a, 'source_payroll_item_id'), 'source_calculation_version' => $this->i($a, 'source_calculation_version'), 'delta_gross_minor' => $tot['gross'], 'delta_employee_insurance_minor' => $tot['employee_insurance'], 'delta_employer_insurance_minor' => $tot['employer_insurance'], 'delta_personal_income_tax_minor' => $tot['tax'], 'delta_net_minor' => $tot['net'], 'currency' => $tot['currency'], 'status' => 'applied', 'created_at' => now(), 'updated_at' => now()]);
            $statement = ['type' => 'Payroll Adjustment Statement', 'adjustment_id' => $c->adjustmentId, 'source_period_id' => $this->i($a, 'source_payroll_period_id'), 'payment_period_id' => $this->ni($a, 'target_payment_period_id'), 'totals' => $tot, 'components' => $this->db->table('payroll_adjustment_components')->where('adjustment_request_id', $c->adjustmentId)->get()->map(fn (object $x): array => (array) $x)->all()];
            $payload = $this->canonical($statement);
            $sourcePayslip = $this->db->table('payroll_payslips')->where('company_id', $c->companyId)->where('payroll_item_id', $this->i($a, 'source_payroll_item_id'))->whereNot('status', 'revoked')->value('id');
            $this->db->table('payroll_adjustment_payslips')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $c->companyId, 'adjustment_request_id' => $c->adjustmentId, 'source_payroll_item_id' => $this->i($a, 'source_payroll_item_id'), 'source_payslip_id' => is_numeric($sourcePayslip) ? (int) $sourcePayslip : null, 'source_payroll_period_id' => $this->i($a, 'source_payroll_period_id'), 'payment_period_id' => $this->ni($a, 'target_payment_period_id'), 'snapshot_payload' => $payload, 'payload_checksum' => hash('sha256', $payload), 'created_at' => now(), 'updated_at' => now()]);
            if ($tot['net'] > 0) {
                $this->intent($c->companyId, $c->adjustmentId, 'payment_eligible', $tot['net'], $tot['currency'], $run);
            } elseif ($tot['net'] < 0) {
                $this->db->table('payroll_adjustment_recoveries')->insert(['uuid' => (string) Str::uuid(), 'company_id' => $c->companyId, 'adjustment_request_id' => $c->adjustmentId, 'outstanding_amount_minor' => abs($tot['net']), 'remaining_amount_minor' => abs($tot['net']), 'currency' => $tot['currency'], 'active_intent_key' => 'REC:'.$c->adjustmentId, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->intent($c->companyId, $c->adjustmentId, 'accounting_eligible', $tot['net'], $tot['currency'], $run);
            $this->intent($c->companyId, $c->adjustmentId, 'statement', $tot['net'], $tot['currency'], $run);
            if ($tot['net'] <= 0) {
                $this->intent($c->companyId, $c->adjustmentId, 'payment', $tot['net'], $tot['currency'], $run);
            }
            $this->setStatus($a, $c->actorId, 'applied', 'applied', null, ['applied_by' => $c->actorId, 'applied_at' => now()]);
        }, 3);
    }

    private function transition(ProcessPayrollAdjustmentCommand $c, string $from, string $to): void
    {
        $this->db->transaction(function () use ($c, $from, $to): void {
            $a = $this->adjustment($c->companyId, $c->adjustmentId, true);
            $this->transitionLocked($a, $c->actorId, $from, $to);
        }, 3);
    }

    private function transitionLocked(object $a, int $actor, string $from, string $to): void
    {
        if ($this->s($a, 'status') === $to) {
            return;
        }if ($this->s($a, 'status') !== $from) {
            throw ValidationException::withMessages(['status' => 'Invalid adjustment transition.']);
        }if ($to === 'validated' && $this->db->table('payroll_adjustment_validation_issues')->where('adjustment_request_id', $this->i($a, 'id'))->where('is_blocking', true)->exists()) {
            throw ValidationException::withMessages(['validation' => 'Adjustment has blocking issues.']);
        }$extra = match ($to) {
            'submitted' => ['submitted_by' => $actor, 'submitted_at' => now()],'reviewed' => ['reviewed_by' => $actor, 'reviewed_at' => now()],'approved' => ['approved_by' => $actor, 'approved_at' => now()],default => []
        };
        $this->setStatus($a, $actor, $to, $to, null, $extra);
    }

    /** @return array<string,mixed> */
    private function historicalPayload(int $company, object $s): array
    {
        $id = $this->i($s, 'id');
        $components = $this->db->table('payroll_item_components')->where('company_id', $company)->where('payroll_item_id', $id)->orderBy('calculation_order')->get()->map(fn (object $x): array => (array) $x)->all();

        return ['schema_version' => 1, 'source_item_id' => $id, 'source_run_id' => $this->i($s, 'source_run_id'), 'source_calculation_version' => $this->i($s, 'calculation_version'), 'employee' => ['id' => $this->i($s, 'employee_id'), 'number' => $this->s($s, 'employee_number_snapshot'), 'name' => $this->s($s, 'employee_name_snapshot')], 'dimensions' => ['organization' => $this->n($s, 'organization_unit_snapshot'), 'position' => $this->n($s, 'job_position_snapshot')], 'policy' => ['insurance_id' => $this->ni($s, 'insurance_policy_id'), 'insurance_version' => $this->ni($s, 'insurance_policy_version'), 'tax_id' => $this->ni($s, 'tax_policy_id'), 'tax_version' => $this->ni($s, 'tax_policy_version')], 'totals' => ['gross' => $this->i($s, 'gross_amount_minor'), 'employee_insurance' => $this->i($s, 'insurance_employee_total_minor'), 'employer_insurance' => $this->i($s, 'insurance_employer_total_minor'), 'tax' => $this->i($s, 'personal_income_tax_minor'), 'net' => $this->i($s, 'net_after_personal_income_tax_minor') ?: $this->i($s, 'net_amount_minor'), 'currency' => $this->s($s, 'currency_code')], 'components' => $components, 'captured_at' => now()->toIso8601String()];
    }

    /**
     * @param  array<string,mixed>  $historical
     * @return array{gross:int,employee_insurance:int,employer_insurance:int,tax:int,net:int,currency:string}
     */
    private function effectiveTotals(int $item, array $historical): array
    {
        $rawTotals = $historical['totals'] ?? null;
        if (! is_array($rawTotals)) {
            throw new \RuntimeException('Historical totals invalid.');
        }
        $t = $this->stringKeyedArray($rawTotals);
        $out = ['gross' => $this->arrInt($t, 'gross'), 'employee_insurance' => $this->arrInt($t, 'employee_insurance'), 'employer_insurance' => $this->arrInt($t, 'employer_insurance'), 'tax' => $this->arrInt($t, 'tax'), 'net' => $this->arrInt($t, 'net'), 'currency' => $this->str($t, 'currency')];
        $sums = $this->db->table('payroll_adjustment_runs')->where('source_payroll_item_id', $item)->where('status', 'applied')->selectRaw('COALESCE(SUM(delta_gross_minor),0) gross,COALESCE(SUM(delta_employee_insurance_minor),0) employee_insurance,COALESCE(SUM(delta_employer_insurance_minor),0) employer_insurance,COALESCE(SUM(delta_personal_income_tax_minor),0) tax,COALESCE(SUM(delta_net_minor),0) net')->first();
        if (is_object($sums)) {
            foreach (['gross', 'employee_insurance', 'employer_insurance', 'tax', 'net'] as $k) {
                $out[$k] += $this->i($sums, $k);
            }
        }

        return $out;
    }

    /**
     * @param  array{gross:int,employee_insurance:int,employer_insurance:int,tax:int,net:int,currency:string}  $h
     * @param  array{gross:int,employee_insurance:int,employer_insurance:int,tax:int,net:int,currency:string}  $c
     */
    private function delta(array $h, array $c): PayrollAdjustmentDeltaResult
    {
        $defs = [['GROSS', 'gross', true, true, false], ['EMPLOYEE_INSURANCE', 'employee_insurance', false, false, false], ['EMPLOYER_INSURANCE', 'employer_insurance', false, false, true], ['PERSONAL_INCOME_TAX', 'tax', false, false, false], ['NET', 'net', false, false, false]];
        $lines = [];
        foreach ($defs as [$code,$key,$taxable,$insurable,$employer]) {
            $lines[] = ['code' => $code, 'type' => strtolower($code), 'historical' => $h[$key], 'corrected' => $c[$key], 'delta' => $c[$key] - $h[$key], 'currency' => $c['currency'], 'taxable' => $taxable, 'insurable' => $insurable, 'employer_cost' => $employer];
        }

        return new PayrollAdjustmentDeltaResult($c['gross'] - $h['gross'], $c['employee_insurance'] - $h['employee_insurance'], $c['employer_insurance'] - $h['employer_insurance'], $c['tax'] - $h['tax'], $c['net'] - $h['net'], $c['currency'], $lines);
    }

    /** @return array{gross:int,employee_insurance:int,employer_insurance:int,tax:int,net:int,currency:string} */
    private function componentTotals(int $id): array
    {
        $r = [];
        foreach ($this->db->table('payroll_adjustment_components')->where('adjustment_request_id', $id)->get() as $x) {
            $r[$this->s($x, 'component_type')] = $this->i($x, 'delta_amount_minor');
        }$currency = $this->db->table('payroll_adjustment_components')->where('adjustment_request_id', $id)->value('currency');

        return ['gross' => $r['gross'] ?? 0, 'employee_insurance' => $r['employee_insurance'] ?? 0, 'employer_insurance' => $r['employer_insurance'] ?? 0, 'tax' => $r['personal_income_tax'] ?? 0, 'net' => $r['net'] ?? 0, 'currency' => is_string($currency) ? $currency : 'VND'];
    }

    /** @param array<string,mixed> $payload */
    private function snapshot(int $c, int $id, string $type, array $payload): void
    {
        $json = $this->canonical($payload);
        $this->db->table('payroll_adjustment_snapshots')->updateOrInsert(['adjustment_request_id' => $id, 'snapshot_type' => $type], ['company_id' => $c, 'schema_version' => 1, 'snapshot_payload' => $json, 'payload_checksum' => hash('sha256', $json), 'captured_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string,mixed> */
    private function snapshotPayload(int $id, string $type): array
    {
        $v = $this->db->table('payroll_adjustment_snapshots')->where('adjustment_request_id', $id)->where('snapshot_type', $type)->value('snapshot_payload');
        if (! is_string($v)) {
            throw new \RuntimeException('Adjustment snapshot missing.');
        }
        $decoded = json_decode($v, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Adjustment snapshot invalid.');
        }

        return $this->stringKeyedArray($decoded);
    }

    /** @param array<string,mixed> $v */
    private function canonical(array $v): string
    {
        ksort($v);

        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $extra */
    private function setStatus(object $a, int $actor, string $to, string $action, ?string $reason = null, array $extra = []): void
    {
        $id = $this->i($a, 'id');
        $before = $this->i($a, 'version');
        $from = $this->s($a, 'status');
        $this->db->table('payroll_adjustment_requests')->where('id', $id)->update(['status' => $to, 'version' => $before + 1, 'updated_at' => now()] + $extra);
        $this->history($this->i($a, 'company_id'), $id, $actor, $action, $from, $to, $before, $before + 1, $reason);
        activity('payroll-adjustment')->causedBy($actor)->event($action)->withProperties(['company_id' => $this->i($a, 'company_id'), 'adjustment_id' => $id, 'from' => $from, 'to' => $to])->log('Payroll adjustment '.$action);
        $this->emit($this->i($a, 'company_id'), $id, $to, $this->i($a, 'employee_id'));
    }

    private function history(int $c, int $id, int $actor, string $action, ?string $from, string $to, int $before, int $after, ?string $reason): void
    {
        $this->db->table('payroll_adjustment_histories')->insert(['company_id' => $c, 'adjustment_request_id' => $id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'actor_id' => $actor, 'safe_reason' => $reason, 'version_before' => $before, 'version_after' => $after, 'created_at' => now()]);
    }

    private function issue(int $c, int $id, string $code, string $msg, bool $blocking): void
    {
        $this->db->table('payroll_adjustment_validation_issues')->insert(['company_id' => $c, 'adjustment_request_id' => $id, 'severity' => $blocking ? 'error' : 'warning', 'code' => $code, 'safe_message' => $msg, 'is_blocking' => $blocking, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function intent(int $c, int $id, string $type, int $amount, string $currency, int $run): void
    {
        $this->db->table('payroll_adjustment_downstream_intents')->insertOrIgnore(['uuid' => (string) Str::uuid(), 'company_id' => $c, 'adjustment_request_id' => $id, 'intent_type' => $type, 'amount_minor' => $amount, 'currency' => $currency, 'status' => 'eligible', 'source_trace' => json_encode(['adjustment_run_id' => $run], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function assertSupersede(int $c, int $old, int $item): void
    {
        $a = $this->db->table('payroll_adjustment_requests')->where('company_id', $c)->where('id', $old)->where('source_payroll_item_id', $item)->whereIn('status', ['approved', 'applied'])->first();
        if (! is_object($a)) {
            throw ValidationException::withMessages(['supersedes' => 'ADJUSTMENT_SUPERSEDE_INVALID']);
        }
    }

    private function adjustment(int $c, int $id, bool $lock): object
    {
        $q = $this->db->table('payroll_adjustment_requests')->where('company_id', $c)->where('id', $id);
        if ($lock) {
            $q->lockForUpdate();
        }$r = $q->first();
        if (! is_object($r)) {
            abort(404);
        }if ($this->s($r, 'status') === 'superseded') {
            throw ValidationException::withMessages(['status' => 'Superseded adjustment is immutable.']);
        }

        return $r;
    }

    private function emit(int $c, int $id, string $s, int $employee): void
    {
        event(new PayrollAdjustmentChanged($c, $id, $s, $employee));
    }

    private function i(object $r, string $k): int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    private function ni(object $r, string $k): ?int
    {
        $v = $r->{$k} ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    private function s(object $r, string $k): string
    {
        $v = $r->{$k} ?? null;

        return is_string($v) ? $v : '';
    }

    private function n(object $r, string $k): ?string
    {
        $v = $r->{$k} ?? null;

        return is_string($v) ? $v : null;
    }

    /** @param array<string,mixed> $a */
    private function arrInt(array $a, string $k): int
    {
        $v = $a[$k] ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    /** @param array<string,mixed> $a */
    private function str(array $a, string $k): string
    {
        $v = $a[$k] ?? null;

        return is_string($v) ? $v : '';
    }

    /** @param  array<string,mixed>  $historical */
    private function historicalCurrency(array $historical): string
    {
        $totals = $historical['totals'] ?? null;

        return is_array($totals) ? $this->str($this->stringKeyedArray($totals), 'currency') : '';
    }

    /**
     * @param  array<mixed,mixed>  $values
     * @return array<string,mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
