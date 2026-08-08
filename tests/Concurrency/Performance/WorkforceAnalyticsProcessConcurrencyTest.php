<?php

namespace Tests\Concurrency\Performance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class WorkforceAnalyticsProcessConcurrencyTest extends TestCase
{
    public function test_snapshot_refresh_and_rebuild_are_idempotent_under_process_race(): void
    {
        $c = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'ANA-'.Str::random(6), 'name' => 'Analytics Race', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $u = User::factory()->create();
        $runner = new MysqlProcessRaceRunner;
        try {
            foreach (['analytics_snapshot', 'analytics_refresh', 'analytics_rebuild'] as $i => $scenario) {
                $payload = ['company_id' => (int) $c->id, 'actor_id' => (int) $u->id, 'idempotency_key' => 'race-'.$i, 'date' => '2026-08-03'];
                $race = $runner->run($scenario, $payload, $payload);
                self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
                self::assertSame(1, DB::table('analytics_calculation_runs')->where('company_id', $c->id)->where('idempotency_key', 'race-'.$i)->count());
                self::assertSame(3, DB::table('workforce_analytics_snapshots')->where('company_id', $c->id)->where('version', $i + 1)->count());
                self::assertSame(1, DB::table('workforce_analytics_snapshots')->where('company_id', $c->id)->where('version', $i + 1)->distinct()->count('checksum'));
            }
        } finally {
            DB::table('analytics_outbox')->where('company_id', $c->id)->delete();
            DB::table('workforce_analytics_snapshots')->where('company_id', $c->id)->delete();
            DB::table('analytics_calculation_runs')->where('company_id', $c->id)->delete();
            $u->delete();
            $c->forceDelete();
        }
    }
}
