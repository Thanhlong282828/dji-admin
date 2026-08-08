<?php

namespace Modules\Payroll\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Events\PayrollPeriodChanged;
use Modules\Payroll\Domain\Models\PayrollPeriod;

final readonly class PayrollPeriodOperationsService
{
    public function __construct(private PayrollPeriodStateMachine $states) {}

    /** @param array{code:string,name:string,start:string,end:string,pay_date:string,status:string,note:string|null} $data */
    public function create(int $companyId, array $data, int $actorId, string $idempotencyKey): PayrollPeriod
    {
        return DB::transaction(function () use ($companyId, $data, $actorId, $idempotencyKey): PayrollPeriod {
            $existing = PayrollPeriod::query()->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof PayrollPeriod) {
                return $existing;
            }
            $this->assertDates($data['start'], $data['end'], $data['pay_date']);
            $this->assertCode($companyId, $data['code']);
            if ($data['status'] !== 'draft') {
                $this->assertNoOverlap($companyId, $data['start'], $data['end']);
            }
            $period = PayrollPeriod::query()->create(['company_id' => $companyId, 'code' => $data['code'], 'name' => $data['name'], 'period_start' => $data['start'], 'period_end' => $data['end'], 'cutoff_at' => $data['end'].' 23:59:59', 'payment_date' => $data['pay_date'], 'status' => 'draft', 'version' => 1, 'created_by' => $actorId, 'idempotency_key' => $idempotencyKey, 'note' => $data['note']]);
            $this->history($period, $actorId, 'created', null, 'draft', ['code' => $data['code']]);
            activity('payroll-period')->performedOn($period)->causedBy($actorId)->event('created')->withProperties(['company_id' => $companyId])->log('Payroll Period created');
            event(new PayrollPeriodChanged($companyId, $period->id, 'draft', 1, 'created'));
            if ($data['status'] === 'collecting') {
                $period = $this->transition($companyId, $period->id, PayrollPeriodStatus::Collecting, $actorId);
            }

            return $period;
        });
    }

    /** @param array{code:string,name:string,start:string,end:string,pay_date:string,note:string|null} $data */
    public function update(int $companyId, int $periodId, array $data, int $actorId): PayrollPeriod
    {
        return DB::transaction(function () use ($companyId, $periodId, $data, $actorId): PayrollPeriod {
            $period = $this->period($companyId, $periodId, true);
            if ($this->status($period) !== PayrollPeriodStatus::Draft || $this->dependencies($periodId) > 0) {
                throw ValidationException::withMessages(['period' => 'Chỉ được sửa kỳ draft chưa có Snapshot hoặc Payroll Run.']);
            }
            $this->assertDates($data['start'], $data['end'], $data['pay_date']);
            $this->assertCode($companyId, $data['code'], $periodId);
            $before = $period->only(['code', 'name', 'period_start', 'period_end', 'payment_date', 'note']);
            $period->fill(['code' => $data['code'], 'name' => $data['name'], 'period_start' => $data['start'], 'period_end' => $data['end'], 'cutoff_at' => $data['end'].' 23:59:59', 'payment_date' => $data['pay_date'], 'note' => $data['note'], 'version' => $period->version + 1])->save();
            $this->history($period, $actorId, 'updated', 'draft', 'draft', ['before' => $before]);
            activity('payroll-period')->performedOn($period)->causedBy($actorId)->event('updated')->withProperties(['company_id' => $companyId])->log('Payroll Period updated');
            event(new PayrollPeriodChanged($companyId, $periodId, 'draft', $period->version, 'updated'));

            return $period;
        });
    }

    public function transition(int $companyId, int $periodId, PayrollPeriodStatus $to, int $actorId): PayrollPeriod
    {
        return DB::transaction(function () use ($companyId, $periodId, $to, $actorId): PayrollPeriod {
            $period = $this->period($companyId, $periodId, true);
            $from = $this->status($period);
            $this->states->assertCanTransition($from, $to);
            if ($to === PayrollPeriodStatus::Collecting) {
                $this->assertNoOverlap($companyId, $this->date($period, 'period_start'), $this->date($period, 'period_end'), $periodId);
            }
            $attributes = ['status' => $to->value, 'version' => $period->version + 1];
            if ($to === PayrollPeriodStatus::Locked) {
                $attributes['locked_at'] = now();
                $attributes['locked_by'] = $actorId;
            }
            $period->forceFill($attributes)->save();
            $action = match ($to) {
                PayrollPeriodStatus::Collecting => 'opened',PayrollPeriodStatus::Locked => 'locked',PayrollPeriodStatus::Processed => 'closed',default => $to->value
            };
            $this->history($period, $actorId, $action, $from->value, $to->value, []);
            activity('payroll-period')->performedOn($period)->causedBy($actorId)->event($action)->withProperties(['company_id' => $companyId, 'from' => $from->value, 'to' => $to->value])->log('Payroll Period '.$action);
            event(new PayrollPeriodChanged($companyId, $periodId, $to->value, $period->version, $action));

            return $period;
        });
    }

    public function delete(int $companyId, int $periodId, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $periodId, $actorId): void {
            $period = $this->period($companyId, $periodId, true);
            if ($this->status($period) !== PayrollPeriodStatus::Draft || $this->dependencies($periodId) > 0) {
                throw ValidationException::withMessages(['period' => 'Chỉ được xóa kỳ draft chưa có Snapshot hoặc Payroll Run.']);
            }activity('payroll-period')->performedOn($period)->causedBy($actorId)->event('deleted')->withProperties(['company_id' => $companyId, 'code' => $period->code])->log('Payroll Period deleted');
            $period->delete();
        });
    }

    private function period(int $companyId, int $periodId, bool $lock): PayrollPeriod
    {
        $q = PayrollPeriod::query()->where('company_id', $companyId);
        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->findOrFail($periodId);
    }

    private function status(PayrollPeriod $period): PayrollPeriodStatus
    {
        $value = $period->getRawOriginal('status');

        return PayrollPeriodStatus::from(is_string($value) ? $value : 'draft');
    }

    private function date(PayrollPeriod $period, string $attribute): string
    {
        $value = $period->getRawOriginal($attribute);
        if (! is_string($value)) {
            throw ValidationException::withMessages([$attribute => 'Payroll Period date không hợp lệ.']);
        }

        return substr($value, 0, 10);
    }

    private function dependencies(int $periodId): int
    {
        return DB::table('payroll_input_snapshots')->where('payroll_period_id', $periodId)->count() + DB::table('payroll_runs')->where('payroll_period_id', $periodId)->count();
    }

    private function assertDates(string $start, string $end, string $pay): void
    {
        if ($end < $start) {
            throw ValidationException::withMessages(['end_date' => 'End date phải lớn hơn hoặc bằng Start date.']);
        }if ($pay < $start) {
            throw ValidationException::withMessages(['pay_date' => 'Pay date không được trước Start date.']);
        }
    }

    private function assertCode(int $companyId, string $code, ?int $except = null): void
    {
        $q = PayrollPeriod::query()->where('company_id', $companyId)->where('code', $code);
        if ($except !== null) {
            $q->whereKeyNot($except);
        }if ($q->exists()) {
            throw ValidationException::withMessages(['code' => 'Mã Payroll Period đã tồn tại trong Company.']);
        }
    }

    private function assertNoOverlap(int $companyId, string $start, string $end, ?int $except = null): void
    {
        $q = PayrollPeriod::query()->where('company_id', $companyId)->whereIn('status', ['collecting', 'review'])->whereDate('period_start', '<=', $end)->whereDate('period_end', '>=', $start);
        if ($except !== null) {
            $q->whereKeyNot($except);
        }if ($q->exists()) {
            throw ValidationException::withMessages(['start_date' => 'Payroll Period đang mở không được chồng ngày trong cùng Company.']);
        }
    }

    /** @param array<string,mixed> $changes */
    private function history(PayrollPeriod $period, int $actor, string $action, ?string $from, string $to, array $changes): void
    {
        DB::table('payroll_period_histories')->insert(['company_id' => $period->company_id, 'payroll_period_id' => $period->id, 'actor_id' => $actor, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'version' => $period->version, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
