<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use App\Support\RuntimeConfiguration\FeatureFlag;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Company\Domain\Models\Company;
use Modules\Notification\Application\DTOs\QueueNotificationData;
use Modules\Notification\Domain\Services\NotificationDeliveryService;
use Modules\Notification\Domain\Services\NotificationReadStateService;
use Modules\Notification\Domain\Services\NotificationTemplateService;
use Modules\Workflow\Application\DTOs\StartWorkflowData;
use Modules\Workflow\Application\DTOs\WorkflowDefinitionData;
use Modules\Workflow\Domain\Services\WorkflowDefinitionService;
use Modules\Workflow\Domain\Services\WorkflowExecutionService;
use Tests\TestCase;

final class EnterpriseNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_message_pins_published_template_and_delivery_is_idempotent_per_channel(): void
    {
        [$company,$user] = $this->context(['notification.view']);
        $template = app(NotificationTemplateService::class)->create((int) $company->id, (int) $user->id, 'WORKFLOW', 'Workflow', 'workflow', 'Subject v1', 'Body v1', true);
        app(NotificationTemplateService::class)->publish((int) $company->id, $template, (int) $user->id);
        $data = $this->data((int) $company->id, (int) $user->id, $template, 'message-same-key');
        $first = app(NotificationDeliveryService::class)->queue($data);
        $second = app(NotificationDeliveryService::class)->queue($data);
        self::assertSame($first, $second);
        self::assertSame(1, DB::table('notification_messages')->count());
        self::assertSame(2, DB::table('notification_deliveries')->count());
        self::assertSame(1, DB::table('notifications')->where('notifiable_id', $user->id)->count());
        self::assertNotNull(DB::table('notification_messages')->value('notification_template_version_id'));
        self::assertSame(2, DB::table('notification_delivery_attempts')->count());
    }

    public function test_partial_channel_retry_does_not_duplicate_successful_database_notification(): void
    {
        [$company,$user] = $this->context([]);
        $message = app(NotificationDeliveryService::class)->queue($this->data((int) $company->id, (int) $user->id, null, 'partial-retry'));
        $broadcast = $this->integer(DB::table('notification_deliveries')->where('notification_message_id', $message)->where('channel', 'broadcast')->value('id'));
        DB::table('notification_deliveries')->where('id', $broadcast)->update(['status' => 'failed']);
        app(NotificationDeliveryService::class)->retry((int) $company->id, $broadcast);
        self::assertSame(1, DB::table('notifications')->where('notifiable_id', $user->id)->count());
        self::assertSame(1, DB::table('notification_deliveries')->where('channel', 'database')->value('attempts'));
        self::assertSame(2, DB::table('notification_deliveries')->where('channel', 'broadcast')->value('attempts'));
        self::assertSame('sent', DB::table('notification_deliveries')->where('id', $broadcast)->value('status'));
    }

    public function test_broadcast_kill_switch_preserves_intent_and_retry_is_idempotent(): void
    {
        [$company, $user] = $this->context([]);
        $flags = config('runtime-configuration.flags');
        self::assertIsArray($flags);
        $broadcast = $flags[FeatureFlag::NotificationBroadcast->value] ?? [];
        self::assertIsArray($broadcast);
        $broadcast['enabled'] = false;
        $flags[FeatureFlag::NotificationBroadcast->value] = $broadcast;
        config()->set('runtime-configuration.flags', $flags);

        $message = app(NotificationDeliveryService::class)->queue($this->data((int) $company->id, (int) $user->id, null, 'broadcast-switch'));
        self::assertSame(1, DB::table('notifications')->where('notifiable_id', $user->id)->count());
        self::assertSame('failed', DB::table('notification_deliveries')->where('notification_message_id', $message)->where('channel', 'broadcast')->value('status'));
        self::assertSame(0, DB::table('notification_deliveries')->where('notification_message_id', $message)->where('channel', 'broadcast')->value('attempts'));

        $broadcast['enabled'] = true;
        $flags[FeatureFlag::NotificationBroadcast->value] = $broadcast;
        config()->set('runtime-configuration.flags', $flags);
        app(NotificationDeliveryService::class)->queue($this->data((int) $company->id, (int) $user->id, null, 'broadcast-switch'));
        app(NotificationDeliveryService::class)->queue($this->data((int) $company->id, (int) $user->id, null, 'broadcast-switch'));

        self::assertSame(2, DB::table('notification_deliveries')->where('notification_message_id', $message)->count());
        self::assertSame(1, DB::table('notification_deliveries')->where('notification_message_id', $message)->where('channel', 'broadcast')->value('attempts'));
        self::assertSame(1, DB::table('notifications')->where('notifiable_id', $user->id)->count());
    }

    public function test_inbox_read_state_is_owned_by_recipient_and_idempotent(): void
    {
        [$company,$user] = $this->context(['notification.view']);
        $other = $this->user($company, ['notification.view']);
        $message = app(NotificationDeliveryService::class)->queue($this->data((int) $company->id, (int) $user->id, null, 'read-state'));
        $this->actingAs($other)->get(route('admin.notifications.show', ['message' => $message, 'company_id' => $company->id]))->assertNotFound();
        $this->actingAs($user)->get(route('admin.notifications.show', ['message' => $message, 'company_id' => $company->id]))->assertOk();
        app(NotificationReadStateService::class)->mark((int) $company->id, (int) $user->id, $message);
        app(NotificationReadStateService::class)->mark((int) $company->id, (int) $user->id, $message);
        self::assertSame(1, DB::table('notification_read_states')->count());
    }

    public function test_security_matrix_enforces_permission_and_company_scope(): void
    {
        [$company,$viewer] = $this->context(['notification.view']);
        [$foreign,$foreignViewer] = $this->context(['notification.view']);
        $none = $this->user($company, []);
        $this->get(route('admin.notifications.index', ['company_id' => $company->id]))->assertRedirect('/login');
        $this->actingAs($none)->get(route('admin.notifications.index', ['company_id' => $company->id]))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.notifications.index', ['company_id' => $company->id]))->assertOk();
        $this->actingAs($foreignViewer)->get(route('admin.notifications.index', ['company_id' => $company->id]))->assertNotFound();
        self::assertNotSame($company->id, $foreign->id);
    }

    public function test_all_notification_admin_capabilities_have_route_permission_guards(): void
    {
        [$company, $user] = $this->context([]);
        $requests = [
            ['get', route('admin.notifications.index', ['company_id' => $company->id]), []],
            ['get', route('admin.notifications.templates.index', ['company_id' => $company->id]), []],
            ['get', route('admin.notifications.deliveries.index', ['company_id' => $company->id]), []],
            ['post', route('admin.notifications.deliveries.retry', ['delivery' => 999999]), ['company_id' => $company->id]],
            ['post', route('admin.notifications.preferences.update'), ['company_id' => $company->id]],
        ];
        foreach ($requests as [$method, $uri, $data]) {
            ($method === 'get' ? $this->actingAs($user)->get($uri) : $this->actingAs($user)->post($uri, $data))->assertForbidden();
        }
    }

    public function test_restricted_payload_is_rejected_before_message_creation(): void
    {
        [$company,$user] = $this->context([]);
        $data = new QueueNotificationData((int) $company->id, null, 'payroll', 'payroll_run', 1, 'finalized', 'Payroll updated', 'Open the authorized Payroll page.', null, ['salary_amount' => 1000], [(int) $user->id], ['database'], 'restricted-payload', null);
        $this->expectException(ValidationException::class);
        try {
            app(NotificationDeliveryService::class)->queue($data);
        } finally {
            self::assertSame(0, DB::table('notification_messages')->count());
        }
    }

    public function test_browser_fixture_is_guarded_and_cleanup_is_complete(): void
    {
        if (DB::connection()->getDatabaseName() !== 'schema_admin_testing') {
            $this->markTestSkipped('Fixture guard is verified only on schema_admin_testing.');
        }
        self::assertSame(0, Artisan::call('notification:browser-fixture'));
        self::assertSame(2, DB::table('notification_messages')->where('company_id', DB::table('companies')->where('code', 'NOTIFY-FIXTURE')->value('id'))->count());
        self::assertSame(0, Artisan::call('notification:browser-fixture', ['--cleanup' => true]));
        self::assertSame(0, DB::table('companies')->where('code', 'NOTIFY-FIXTURE')->count());
    }

    public function test_workflow_assignment_outbox_is_projected_into_notification_center(): void
    {
        [$company, $user] = $this->context([]);
        $definition = app(WorkflowDefinitionService::class)->create(new WorkflowDefinitionData((int) $company->id, (int) $user->id, 'NOTIFY-WORKFLOW', 'Notify Workflow', null, 'sequential', [['key' => 'approve', 'name' => 'Approve', 'mode' => 'sequential', 'resolver_type' => 'specific_user', 'resolver_value' => (string) $user->id]]));
        app(WorkflowDefinitionService::class)->publish((int) $company->id, $definition, (int) $user->id);
        $instance = app(WorkflowExecutionService::class)->start(new StartWorkflowData((int) $company->id, (int) $user->id, $definition, 'employee', 77, 'notify-workflow-start'));
        self::assertSame(1, DB::table('workflow_outbox')->where('workflow_instance_id', $instance)->where('event_type', 'workflow.task.assigned')->count());
        self::assertSame(0, Artisan::call('notification:consume-workflow-outbox'));
        self::assertSame(1, DB::table('notification_messages')->where('company_id', $company->id)->where('category', 'workflow')->count());
        self::assertNotNull(DB::table('workflow_outbox')->where('workflow_instance_id', $instance)->value('published_at'));
    }

    /** @param list<string> $permissions
     * @return array{Company,User}
     */
    private function context(array $permissions): array
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'NT-'.Str::upper(Str::random(8)), 'name' => 'Notification Test', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);

        return [$company, $this->user($company, $permissions)];
    }

    /** @param list<string> $permissions */
    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        } DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return $user;
    }

    private function data(int $companyId, int $userId, ?int $templateId, string $key): QueueNotificationData
    {
        return new QueueNotificationData($companyId, $templateId, 'workflow', 'workflow_instance', 77, 'assigned', 'Workflow assigned', 'A workflow item requires attention.', null, ['workflow_event' => 'assigned'], [$userId], ['database', 'broadcast'], $key, null);
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : self::fail('Expected numeric id.');
    }
}
