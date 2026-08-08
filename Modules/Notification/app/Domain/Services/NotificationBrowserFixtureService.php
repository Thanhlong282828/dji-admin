<?php

namespace Modules\Notification\Domain\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Notification\Application\DTOs\QueueNotificationData;

final readonly class NotificationBrowserFixtureService
{
    public function __construct(private NotificationTemplateService $templates, private NotificationDeliveryService $delivery, private NotificationReadStateService $reads) {}

    /** @return array{company_id:int,user_id:int,message_id:int} */
    public function create(): array
    {
        $this->cleanup();
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'NOTIFY-FIXTURE', 'name' => 'Schema Notification Browser Fixture', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::query()->create(['name' => 'Notification Browser Admin', 'email' => 'notification.browser@schema.test', 'password' => Hash::make('123456'), 'email_verified_at' => now()]);
        $user->forceFill(['email_verified_at' => now()])->save();
        foreach (['notification.view', 'notification.template.manage', 'notification.delivery.view', 'notification.retry', 'notification.preference.manage'] as $permission) {
            $user->givePermissionTo($permission);
        }
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);
        $template = $this->templates->create((int) $company->id, (int) $user->id, 'WORKFLOW-TASK', 'Workflow Task', 'workflow', 'Workflow task update', 'A workflow task requires attention.', true);
        $this->templates->publish((int) $company->id, $template, (int) $user->id);
        $first = $this->delivery->queue(new QueueNotificationData((int) $company->id, $template, 'workflow', 'workflow_instance', 9001, 'assigned', 'Approval task assigned', 'A new approval task is waiting for you.', '/admin/workflows/tasks', ['workflow_event' => 'assigned'], [(int) $user->id], ['database', 'broadcast'], 'fixture-unread', (int) $user->id));
        $second = $this->delivery->queue(new QueueNotificationData((int) $company->id, $template, 'workflow', 'workflow_instance', 9002, 'completed', 'Workflow completed', 'The workflow completed successfully.', null, ['workflow_event' => 'completed'], [(int) $user->id], ['database'], 'fixture-read', (int) $user->id));
        $this->reads->mark((int) $company->id, (int) $user->id, $second);

        return ['company_id' => (int) $company->id, 'user_id' => (int) $user->id, 'message_id' => $first];
    }

    public function cleanup(): void
    {
        $company = Company::query()->where('code', 'NOTIFY-FIXTURE')->first();
        if ($company === null) {
            return;
        }
        $id = (int) $company->id;
        $messageIds = DB::table('notification_messages')->where('company_id', $id)->pluck('id');
        $deliveryIds = DB::table('notification_deliveries')->where('company_id', $id)->pluck('id');
        DB::table('notification_delivery_attempts')->whereIn('notification_delivery_id', $deliveryIds)->delete();
        DB::table('notification_deliveries')->where('company_id', $id)->delete();
        DB::table('notification_read_states')->where('company_id', $id)->delete();
        DB::table('notification_recipients')->where('company_id', $id)->delete();
        DB::table('notification_messages')->where('company_id', $id)->delete();
        DB::table('notification_preferences')->where('company_id', $id)->delete();
        DB::table('notification_outbox')->where('company_id', $id)->delete();
        $templateIds = DB::table('notification_templates')->where('company_id', $id)->pluck('id');
        DB::table('notification_template_versions')->whereIn('notification_template_id', $templateIds)->delete();
        DB::table('notification_templates')->where('company_id', $id)->delete();
        $users = DB::table('company_user_access')->where('company_id', $id)->pluck('user_id');
        DB::table('notifications')->whereIn('notifiable_id', $users)->delete();
        DB::table('company_user_access')->where('company_id', $id)->delete();
        User::query()->whereIn('id', $users)->delete();
        $company->forceDelete();
    }
}
