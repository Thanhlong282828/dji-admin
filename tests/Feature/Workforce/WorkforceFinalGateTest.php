<?php

namespace Tests\Feature\Workforce;

use App\Contracts\WorkforceNotificationChannelSenderInterface;
use App\Models\User;
use App\Notifications\WorkforceNotification;
use App\Services\LaravelWorkforceNotificationChannelSender;
use App\Services\WorkforceCacheRegistry;
use App\Services\WorkforceNotificationDeliveryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Attendance\Domain\Events\WorkforceAttendanceChanged;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

final class WorkforceFinalGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_record_gate_blocks_cross_company_idor_before_query_handler(): void
    {
        [$companyA,, $user] = $this->context('IDA');
        [$companyB, $employeeB] = $this->context('IDB');
        $user->givePermissionTo('attendance.view');
        $record = $this->attendance($companyB->id, $employeeB->id, '2026-07-01', 'approved');

        $this->actingAs($user)->get("/admin/attendance/{$record}?company_id={$companyA->id}")->assertNotFound();
        $this->actingAs($user)->get("/admin/attendance/{$record}?company_id={$companyB->id}")->assertForbidden();
    }

    public function test_cache_invalidation_is_company_scoped_and_invalidates_payroll_dependency(): void
    {
        [$companyA, $employeeA] = $this->context('CAA');
        [$companyB] = $this->context('CAB');
        $keys = app(WorkforceCacheRegistry::class);
        $a = $keys->key($companyA->id, 'attendance');
        $payroll = $keys->key($companyA->id, 'payroll-input');
        $b = $keys->key($companyB->id, 'attendance');
        Cache::put($a, ['stale' => true]);
        Cache::put($payroll, ['stale' => true]);
        Cache::put($b, ['safe' => true]);

        event(new WorkforceAttendanceChanged($companyA->id, 91, $employeeA->id, 'attendance.approved'));

        self::assertFalse(Cache::has($a));
        self::assertFalse(Cache::has($payroll));
        self::assertTrue(Cache::has($b));
        self::assertSame(1, Cache::get($keys->key($companyA->id, 'attendance', 'version')));
    }

    public function test_export_applies_status_date_and_tenant_filters_to_streamed_query(): void
    {
        [$companyA, $employeeA, $user] = $this->context('EXA');
        [$companyB, $employeeB] = $this->context('EXB');
        $user->givePermissionTo('attendance.export');
        $this->attendance($companyA->id, $employeeA->id, '2026-07-01', 'approved');
        $this->attendance($companyA->id, $employeeA->id, '2026-07-02', 'draft');
        $this->attendance($companyB->id, $employeeB->id, '2026-07-01', 'approved');

        $content = $this->actingAs($user)->get('/admin/attendance-export.csv?'.http_build_query([
            'company_id' => $companyA->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
            'approval_status' => 'approved',
        ]))->assertOk()->streamedContent();

        self::assertStringContainsString('EXA', $content);
        self::assertStringContainsString('2026-07-01', $content);
        self::assertStringNotContainsString('2026-07-02', $content);
        self::assertStringNotContainsString('EXB', $content);
    }

    public function test_leave_pages_expose_partial_refresh_regions_without_sensitive_attributes(): void
    {
        [$company,, $user] = $this->context('DOM');
        $user->givePermissionTo(['leave.view', 'leave.manage-policy']);

        $this->actingAs($user)->get('/admin/leave?company_id='.$company->id)->assertOk()
            ->assertSee('data-refresh-scope="leave-request-list"', false)
            ->assertDontSee('attachment_storage_key');
        $this->actingAs($user)->get('/admin/leave-management?company_id='.$company->id)->assertOk()
            ->assertSee('data-refresh-scope="leave-balance-summary"', false)
            ->assertSee('data-refresh-scope="leave-ledger"', false);
    }

    public function test_notification_failed_channel_retries_without_duplicating_successful_channel(): void
    {
        [$company,, $user] = $this->context('RETRY');
        $sender = new class(new LaravelWorkforceNotificationChannelSender) implements WorkforceNotificationChannelSenderInterface
        {
            public bool $failBroadcast = true;

            public function __construct(private readonly LaravelWorkforceNotificationChannelSender $real) {}

            public function send(User $recipient, WorkforceNotification $notification, string $channel): void
            {
                if ($channel === 'broadcast' && $this->failBroadcast) {
                    throw new \RuntimeException('Injected broadcast channel failure.');
                }
                $this->real->send($recipient, $notification, $channel);
            }
        };
        $this->app->instance(WorkforceNotificationChannelSenderInterface::class, $sender);
        $payload = ['company_id' => $company->id, 'entity_type' => 'attendance_record', 'entity_id' => 77, 'employee_id' => null, 'action' => 'approved', 'safe_title' => 'Cập nhật', 'safe_message' => 'Trạng thái đã đổi.', 'version' => 1, 'timestamp' => now()->toIso8601String(), 'url' => null];
        $delivery = app(WorkforceNotificationDeliveryService::class);

        $delivery->deliver($user, 'attendance', $payload);
        self::assertSame('sent', DB::table('notification_deliveries')->where('channel', 'database')->value('status'));
        self::assertSame('failed', DB::table('notification_deliveries')->where('channel', 'broadcast')->value('status'));
        $sender->failBroadcast = false;
        $delivery->deliver($user, 'attendance', $payload);

        self::assertSame(1, DB::table('notifications')->where('notifiable_id', $user->id)->count());
        self::assertSame(1, (int) DB::table('notification_deliveries')->where('channel', 'database')->value('attempts'));
        self::assertSame(2, (int) DB::table('notification_deliveries')->where('channel', 'broadcast')->value('attempts'));
        self::assertSame('sent', DB::table('notification_deliveries')->where('channel', 'broadcast')->value('status'));
    }

    #[Group('large-export')]
    public function test_overtime_export_streams_five_thousand_rows_with_bounded_memory(): void
    {
        [$company, $employee, $user] = $this->context('BIG');
        $user->givePermissionTo('overtime.export');
        $now = now();
        foreach (range(1, 10) as $chunk) {
            $rows = [];
            foreach (range(1, 500) as $offset) {
                $number = (($chunk - 1) * 500) + $offset;
                $rows[] = ['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2026-07-01', 'starts_at' => '2026-07-01 18:00:00', 'ends_at' => '2026-07-01 19:00:00', 'requested_minutes' => 60, 'approved_minutes' => 0, 'actual_minutes' => 0, 'payable_minutes' => 0, 'calculation_status' => 'pending', 'reason' => 'Load '.$number, 'status' => 'draft', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('overtime_requests')->insert($rows);
        }

        $before = memory_get_usage(true);
        $startedAt = hrtime(true);
        $content = $this->actingAs($user)->get('/admin/overtime-export.csv?company_id='.$company->id)->assertOk()->streamedContent();
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
        $growth = memory_get_peak_usage(true) - $before;

        self::assertSame(5001, substr_count($content, "\n"));
        self::assertLessThan(64 * 1024 * 1024, $growth);
        File::put(storage_path('logs/workforce-large-export-metrics.json'), json_encode(['rows' => 5000, 'duration_ms' => round($durationMs, 2), 'peak_memory_growth_bytes' => $growth], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /** @return array{Company, Employee, User} */
    private function context(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => $code, 'name' => 'Schema '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => $code, 'full_name' => 'Employee '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$company, $employee, $user];
    }

    private function attendance(int $companyId, int $employeeId, string $date, string $approval): int
    {
        return (int) DB::table('attendance_records')->insertGetId(['company_id' => $companyId, 'employee_id' => $employeeId, 'work_date' => $date, 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'break_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => 0, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => $approval, 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()]);
    }
}
