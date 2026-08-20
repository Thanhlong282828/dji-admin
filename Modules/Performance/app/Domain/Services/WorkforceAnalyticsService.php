<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Performance\Application\DTOs\AnalyticsFilter;
use Modules\Performance\Application\DTOs\WorkforceAnalyticsViewModel;

final readonly class WorkforceAnalyticsService
{
    public function __construct(private WorkforceSnapshotCalculator $workforce, private TalentSnapshotCalculator $talent, private OrganizationSnapshotCalculator $organization, private EnterpriseAnalyticsService $enterprise) {}

    public function calculate(int $c, ?AnalyticsFilter $filter = null, bool $salaryAllowed = false): WorkforceAnalyticsViewModel
    {
        $w = $this->workforce->calculate($c);
        $t = $this->talent->calculate($c);
        $o = $this->organization->calculate($c, (int) $w['active']);
        $m = [...$w, ...$t, ...$o];
        $active = (int) $w['active'];
        $inactive = (int) $w['inactive'];
        $hires = (int) $w['new_hires'];
        $prom = (int) $w['promotions'];
        $transfer = (int) $w['transfers'];
        $term = (int) $w['terminations'];

        return new WorkforceAnalyticsViewModel($m, ['headcount' => [$active, $inactive], 'mobility' => [$hires, $prom, $transfer, $term], 'talent' => [$m['average_rating'], $m['goal_completion'], $m['succession_coverage']]], null, $filter === null ? [] : $this->enterprise->build($filter, $salaryAllowed));
    }

    public function snapshot(int $c, int $actor, string $key, string $date, bool $rebuild = false): int
    {
        return DB::transaction(function () use ($c, $actor, $key, $date): int {
            $existing = DB::table('analytics_calculation_runs')->where('company_id', $c)->where('idempotency_key', $key)->value('id');
            if (is_int($existing)) {
                return $existing;
            }$vm = $this->calculate($c);
            $payload = $vm->metrics;
            $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
            $run = (int) DB::table('analytics_calculation_runs')->insertGetId(['company_id' => $c, 'period' => substr($date, 0, 7), 'status' => 'running', 'started_at' => now(), 'checksum' => $checksum, 'idempotency_key' => $key, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            foreach (['workforce', 'talent', 'organization'] as $type) {
                $latest = DB::table('workforce_analytics_snapshots')->where('company_id', $c)->where('snapshot_date', $date)->where('snapshot_type', $type)->max('version');
                $version = (is_int($latest) ? $latest : 0) + 1;
                DB::table('workforce_analytics_snapshots')->insert(['company_id' => $c, 'snapshot_date' => $date, 'snapshot_type' => $type, 'version' => $version, 'metric_payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'checksum' => $checksum, 'calculation_run_id' => $run, 'created_at' => now()]);
            }DB::table('analytics_calculation_runs')->where('id', $run)->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
            DB::table('analytics_outbox')->insert(['event_id' => (string) Str::uuid(), 'company_id' => $c, 'event_type' => 'WorkforceSnapshotGenerated', 'aggregate_id' => $run, 'payload' => json_encode(['date' => $date], JSON_THROW_ON_ERROR), 'payload_checksum' => $checksum, 'occurred_at' => now()]);

            return $run;
        }, 3);
    }
}
