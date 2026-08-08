<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use App\Services\WorkforceNotificationDeliveryService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Leave\Domain\Enums\MalwareScanStatus;
use Modules\Leave\Domain\Services\AutomatedLeaveLedgerService;
use Modules\Leave\Domain\Services\MalwareScannerInterface;
use Modules\Leave\Domain\Services\MalwareScanResult;
use Modules\Leave\Infrastructure\Security\UnavailableMalwareScanner;
use Tests\TestCase;

final class WorkforceSharedGapClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_calendar_day_editor_persists_exactly_seven_days_and_blocks_cross_tenant_shift(): void
    {
        [$company,$employee,$user] = $this->context('DAY');
        $user->givePermissionTo('work-calendar.manage');
        $calendar = (int) DB::table('work_calendars')->insertGetId(['company_id' => $company->id, 'code' => 'CAL', 'name' => 'Lịch chuẩn', 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_work_minutes' => 0, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $shift = (int) DB::table('work_shifts')->insertGetId($this->shift($company->id, 'DAY'));
        $days = [];
        foreach (range(1, 7) as $weekday) {
            $days[$weekday] = ['weekday' => $weekday, 'is_working_day' => $weekday <= 5 ? 1 : 0, 'planned_minutes' => $weekday <= 5 ? 480 : 0, 'default_shift_id' => $weekday <= 5 ? $shift : null];
        }
        $this->actingAs($user)->put("/admin/workforce/calendar-rules/calendar.days.update/{$calendar}", ['company_id' => $company->id, 'version' => 1, 'days' => $days])->assertRedirect();
        self::assertSame(7, DB::table('work_calendar_days')->where('work_calendar_id', $calendar)->count());
        $this->assertDatabaseHas('work_calendars', ['id' => $calendar, 'weekly_work_minutes' => 2400, 'version' => 2]);
        [$other] = $this->context('OTHER');
        $days[1]['default_shift_id'] = (int) DB::table('work_shifts')->insertGetId($this->shift($other->id, 'OTHER'));
        $this->actingAs($user)->put("/admin/workforce/calendar-rules/calendar.days.update/{$calendar}", ['company_id' => $company->id, 'version' => 2, 'days' => $days])->assertSessionHasErrors('days');
    }

    public function test_fixed_break_rules_validate_window_overlap_and_archive(): void
    {
        [$company,,$user] = $this->context('BREAK');
        $user->givePermissionTo('shift.manage');
        $shift = (int) DB::table('work_shifts')->insertGetId($this->shift($company->id, 'BREAK'));
        $payload = ['company_id' => $company->id, 'version' => 1, 'code' => 'LUNCH', 'name' => 'Nghỉ trưa', 'kind' => 'meal', 'mode' => 'fixed', 'starts_at' => '12:00', 'ends_at' => '13:00', 'duration_minutes' => 60, 'minimum_work_minutes' => 240, 'sort_order' => 1, 'effective_from' => '2026-01-01'];
        $this->actingAs($user)->post("/admin/workforce/shift-rules/break.create/{$shift}", $payload)->assertRedirect();
        $id = (int) DB::table('shift_break_rules')->value('id');
        $payload['code'] = 'OVERLAP';
        $payload['starts_at'] = '12:30';
        $payload['ends_at'] = '13:00';
        $payload['duration_minutes'] = 30;
        $this->actingAs($user)->post("/admin/workforce/shift-rules/break.create/{$shift}", $payload)->assertSessionHasErrors('starts_at');
        $this->actingAs($user)->put("/admin/workforce/shift-rules/break.archive/{$shift}/{$id}", ['company_id' => $company->id, 'version' => 1])->assertRedirect();
        $this->assertDatabaseHas('shift_break_rules', ['id' => $id, 'status' => 'archived', 'version' => 2]);
    }

    public function test_attendance_export_is_utf8_formula_safe_and_tenant_scoped(): void
    {
        [$company,$employee,$user] = $this->context('CSV');
        $user->givePermissionTo('attendance.export');
        DB::table('employees')->where('id', $employee->id)->update(['full_name' => '=HYPERLINK("bad")']);
        DB::table('attendance_records')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => '2026-07-20', 'scheduled_minutes' => 480, 'worked_minutes' => 480, 'break_minutes' => 0, 'late_minutes' => 0, 'early_leave_minutes' => 0, 'overtime_minutes' => 0, 'attendance_status' => 'present', 'presence_status' => 'present', 'approval_status' => 'approved', 'calculation_status' => 'calculated', 'calculation_version' => 1, 'version' => 1, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()]);
        $response = $this->actingAs($user)->get('/admin/attendance-export.csv?company_id='.$company->id)->assertOk();
        $content = $response->streamedContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString("'=HYPERLINK", $content);
        self::assertStringNotContainsString('storage_key', $content);
        self::assertStringNotContainsString('bank', $content);
    }

    public function test_notification_delivery_is_durable_per_channel_and_scanner_fallback_is_truthful(): void
    {
        [$company,,$user] = $this->context('NOTIFY');
        $payload = ['company_id' => $company->id, 'entity_type' => 'attendance', 'entity_id' => 42, 'employee_id' => null, 'action' => 'submitted', 'safe_title' => 'Cập nhật', 'safe_message' => 'Trạng thái đã thay đổi.', 'version' => 1, 'timestamp' => now()->toIso8601String(), 'url' => null];
        $service = app(WorkforceNotificationDeliveryService::class);
        $service->deliver($user, 'attendance', $payload);
        $service->deliver($user, 'attendance', $payload);
        self::assertSame(2, DB::table('notification_deliveries')->where('recipient_id', $user->id)->count());
        self::assertSame(1, DB::table('notifications')->where('notifiable_id', $user->id)->count());
        self::assertSame(MalwareScanStatus::NotConnected, (new UnavailableMalwareScanner)->scan('/not-used')->status);
    }

    public function test_private_attachment_clean_flow_download_and_approved_retention(): void
    {
        [$company,$employee,$user] = $this->context('FILE');
        $user->givePermissionTo(['leave.request', 'leave.view']);
        Storage::fake('leave-test');
        config(['leave.attachments.disk' => 'leave-test']);
        $this->app->instance(MalwareScannerInterface::class, new class implements MalwareScannerInterface
        {
            public function scan(string $absolutePath): MalwareScanResult
            {
                return new MalwareScanResult(MalwareScanStatus::Clean);
            }
        });
        $type = (int) DB::table('leave_types')->insertGetId(['company_id' => $company->id, 'code' => 'ANNUAL', 'name' => 'Phép năm', 'accrual_policy' => '{}', 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $policy = (int) DB::table('leave_policies')->insertGetId(['company_id' => $company->id, 'leave_type_id' => $type, 'code' => 'AL', 'name' => 'Annual', 'unit' => 'minutes', 'accrual_method' => 'annual_grant', 'annual_entitlement_minutes' => 5760, 'maximum_carry_forward_minutes' => 0, 'negative_balance_allowed' => false, 'requires_attachment' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $requestId = (int) DB::table('leave_requests')->insertGetId(['company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $type, 'leave_policy_id' => $policy, 'starts_at' => '2026-07-20 08:00:00', 'ends_at' => '2026-07-20 17:00:00', 'timezone' => 'Asia/Ho_Chi_Minh', 'requested_minutes' => 480, 'days' => 1, 'reason' => 'Private evidence', 'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $response = $this->actingAs($user)->post("/admin/leave/{$requestId}/attachments/upload", ['company_id' => $company->id, 'idempotency_key' => (string) Str::uuid(), 'attachment' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf')])->assertRedirect();
        self::assertStringNotContainsString('leave/clean', $response->getContent());
        $attachment = DB::table('leave_attachments')->first();
        self::assertNotNull($attachment);
        self::assertSame('clean', $attachment->scan_status);
        self::assertSame(64, strlen((string) $attachment->sha256));
        $this->actingAs($user)->get("/admin/leave/{$requestId}/attachments/{$attachment->id}/download?company_id={$company->id}")->assertOk()->assertHeader('x-content-type-options', 'nosniff');
        DB::table('leave_requests')->where('id', $requestId)->update(['status' => 'approved']);
        $this->actingAs($user)->put("/admin/leave/{$requestId}/attachments/{$attachment->id}/remove", ['company_id' => $company->id, 'version' => 1])->assertForbidden();
    }

    public function test_leave_accrual_scheduler_service_is_database_idempotent(): void
    {
        [$company,$employee] = $this->context('ACCRUAL');
        $type = (int) DB::table('leave_types')->insertGetId(['company_id' => $company->id, 'code' => 'AL', 'name' => 'Annual', 'accrual_policy' => '{}', 'is_paid' => true, 'created_at' => now(), 'updated_at' => now()]);
        $policy = (int) DB::table('leave_policies')->insertGetId(['company_id' => $company->id, 'leave_type_id' => $type, 'code' => 'AL', 'name' => 'Annual', 'unit' => 'minutes', 'accrual_method' => 'annual_grant', 'annual_entitlement_minutes' => 5760, 'maximum_carry_forward_minutes' => 0, 'negative_balance_allowed' => false, 'requires_attachment' => false, 'status' => 'active', 'effective_from' => '2026-01-01', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('employee_leave_policy_assignments')->insert(['company_id' => $company->id, 'employee_id' => $employee->id, 'leave_policy_id' => $policy, 'effective_from' => '2026-01-01', 'version' => 1, 'idempotency_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        $service = app(AutomatedLeaveLedgerService::class);
        self::assertSame(1, $service->accrue(CarbonImmutable::parse('2027-01-01')));
        self::assertSame(0, $service->accrue(CarbonImmutable::parse('2027-01-01')));
        self::assertSame(1, DB::table('leave_balance_transactions')->where('transaction_type', 'accrual')->count());
        self::assertSame(5760, (int) DB::table('leave_balance_transactions')->sum('minutes'));
    }

    /** @return array{Company,Employee,User} */
    private function context(string $code): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'WG-'.$code, 'name' => 'Schema Gap '.$code, 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $employee = Employee::query()->create(['company_id' => $company->id, 'user_id' => $user->id, 'employee_code' => 'WG-'.$code, 'full_name' => 'Workforce '.$code, 'nationality' => 'VN', 'join_date' => '2026-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time']);

        return [$company, $employee, $user];
    }

    /** @return array<string,bool|int|string|null> */
    private function shift(int $company, string $code): array
    {
        return ['company_id' => $company, 'code' => 'S-'.$code, 'name' => 'Ca '.$code, 'timezone' => 'Asia/Ho_Chi_Minh', 'starts_at' => '08:00', 'ends_at' => '17:00', 'crosses_midnight' => false, 'planned_work_minutes' => 480, 'grace_in_minutes' => 0, 'grace_out_minutes' => 0, 'rounding_rule' => 'none', 'minimum_half_day_minutes' => 240, 'minimum_full_day_minutes' => 480, 'status' => 'active', 'rules' => '{}', 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
    }
}
