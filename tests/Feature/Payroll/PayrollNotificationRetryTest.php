<?php

namespace Tests\Feature\Payroll;

use App\Contracts\WorkforceNotificationChannelSenderInterface;
use App\Models\User;
use App\Notifications\WorkforceNotification;
use App\Services\LaravelWorkforceNotificationChannelSender;
use App\Services\WorkforceNotificationDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Tests\TestCase;

final class PayrollNotificationRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_failed_broadcast_channel_is_retried(): void
    {
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'PN-'.Str::upper(Str::random(6)), 'name' => 'Schema Payroll Notification', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $recipient = User::factory()->create();
        $sender = new class(new LaravelWorkforceNotificationChannelSender) implements WorkforceNotificationChannelSenderInterface
        {
            /** @var array{database:int,broadcast:int} */
            public array $calls = ['database' => 0, 'broadcast' => 0];

            public bool $failBroadcast = true;

            public function __construct(private readonly LaravelWorkforceNotificationChannelSender $real) {}

            public function send(User $recipient, WorkforceNotification $notification, string $channel): void
            {
                if ($channel === 'database' || $channel === 'broadcast') {
                    $this->calls[$channel]++;
                }
                if ($channel === 'broadcast' && $this->failBroadcast) {
                    throw new \RuntimeException('Injected Payroll broadcast failure.');
                }
                $this->real->send($recipient, $notification, $channel);
            }
        };
        $this->app->instance(WorkforceNotificationChannelSenderInterface::class, $sender);
        $delivery = app(WorkforceNotificationDeliveryService::class);
        $payload = ['company_id' => $company->id, 'entity_type' => 'payroll_run', 'entity_id' => 901, 'employee_id' => null, 'action' => 'approved', 'safe_title' => 'Cập nhật kỳ tính lương', 'safe_message' => 'Trạng thái kỳ tính lương đã thay đổi.', 'version' => 4, 'timestamp' => now()->toIso8601String(), 'url' => null];

        $delivery->deliver($recipient, 'payroll_run', $payload);
        self::assertSame('sent', DB::table('notification_deliveries')->where('channel', 'database')->value('status'));
        self::assertSame('failed', DB::table('notification_deliveries')->where('channel', 'broadcast')->value('status'));

        $sender->failBroadcast = false;
        $delivery->deliver($recipient, 'payroll_run', $payload);

        self::assertSame(2, DB::table('notification_deliveries')->count());
        self::assertSame(1, DB::table('notifications')->where('notifiable_id', $recipient->id)->count());
        self::assertEquals(1, DB::table('notification_deliveries')->where('channel', 'database')->value('attempts'));
        self::assertEquals(2, DB::table('notification_deliveries')->where('channel', 'broadcast')->value('attempts'));
        self::assertSame('sent', DB::table('notification_deliveries')->where('channel', 'broadcast')->value('status'));
        self::assertSame(['database' => 1, 'broadcast' => 2], $sender->calls);
    }
}
