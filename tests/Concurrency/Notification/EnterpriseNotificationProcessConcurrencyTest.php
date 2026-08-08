<?php

namespace Tests\Concurrency\Notification;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Company\Domain\Models\Company;
use Modules\Notification\Application\DTOs\QueueNotificationData;
use Modules\Notification\Domain\Services\NotificationDeliveryService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class EnterpriseNotificationProcessConcurrencyTest extends TestCase
{
    /** @var list<int> */
    private array $companies = [];

    /** @var list<int> */
    private array $users = [];

    protected function tearDown(): void
    {
        foreach ($this->companies as $company) {
            $messageIds = DB::table('notification_messages')->where('company_id', $company)->pluck('id');
            $deliveryIds = DB::table('notification_deliveries')->where('company_id', $company)->pluck('id');
            DB::table('notification_delivery_attempts')->whereIn('notification_delivery_id', $deliveryIds)->delete();
            DB::table('notification_deliveries')->where('company_id', $company)->delete();
            DB::table('notification_read_states')->where('company_id', $company)->delete();
            DB::table('notification_recipients')->where('company_id', $company)->delete();
            DB::table('notification_messages')->where('company_id', $company)->delete();
            DB::table('notification_outbox')->where('company_id', $company)->delete();
            DB::table('company_user_access')->where('company_id', $company)->delete();
            Company::query()->whereKey($company)->forceDelete();
        }DB::table('notifications')->whereIn('notifiable_id', $this->users)->delete();
        User::query()->whereIn('id', $this->users)->delete();
        parent::tearDown();
    }

    public function test_queue_queue_creates_one_message_and_one_intent_per_channel(): void
    {
        [$company,$user] = $this->fixture();
        $payload = ['company_id' => $company, 'recipient_id' => $user, 'entity_id' => 88, 'idempotency_key' => 'queue-race'];
        $race = (new MysqlProcessRaceRunner)->run('notification_center_queue', $payload, $payload);
        self::assertContains($race['exit_codes'], [[0, 0], [0, 2]], implode("\n", $race['outputs']));
        self::assertSame(1, DB::table('notification_messages')->where('company_id', $company)->count());
        self::assertSame(2, DB::table('notification_deliveries')->where('company_id', $company)->count());
        self::assertSame(1, DB::table('notifications')->where('notifiable_id', $user)->count());
    }

    public function test_delivery_delivery_has_one_attempt_winner(): void
    {
        $this->raceDelivery('notification_center_delivery');
    }

    public function test_retry_retry_is_idempotent(): void
    {
        $this->raceDelivery('notification_center_retry');
    }

    public function test_mark_read_mark_read_creates_one_state(): void
    {
        [$company,$user] = $this->fixture();
        $message = $this->message($company, $user, 'read-race');
        $payload = ['company_id' => $company, 'recipient_id' => $user, 'message_id' => $message];
        $race = (new MysqlProcessRaceRunner)->run('notification_center_read', $payload, $payload);
        self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
        self::assertSame(1, DB::table('notification_read_states')->where('notification_message_id', $message)->count());
    }

    public function test_mark_all_read_race_keeps_exactly_one_state_per_message(): void
    {
        [$company,$user] = $this->fixture();
        $this->message($company, $user, 'all-1');
        $this->message($company, $user, 'all-2');
        $payload = ['company_id' => $company, 'recipient_id' => $user];
        $race = (new MysqlProcessRaceRunner)->run('notification_center_read_all', $payload, $payload);
        self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
        self::assertSame(2, DB::table('notification_read_states')->where('company_id', $company)->where('user_id', $user)->count());
    }

    private function raceDelivery(string $scenario): void
    {
        [$company,$user] = $this->fixture();
        $message = $this->message($company, $user, $scenario);
        $delivery = $this->integer(DB::table('notification_deliveries')->where('notification_message_id', $message)->where('channel', 'broadcast')->value('id'));
        DB::table('notification_delivery_attempts')->where('notification_delivery_id', $delivery)->delete();
        DB::table('notification_deliveries')->where('id', $delivery)->update(['status' => 'failed', 'attempts' => 0]);
        $payload = ['company_id' => $company, 'delivery_id' => $delivery];
        $race = (new MysqlProcessRaceRunner)->run($scenario, $payload, $payload);
        self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
        self::assertSame('sent', DB::table('notification_deliveries')->where('id', $delivery)->value('status'));
        self::assertSame(1, DB::table('notification_delivery_attempts')->where('notification_delivery_id', $delivery)->count());
    }

    /** @return array{int,int} */
    private function fixture(): array
    {
        $token = Str::upper(Str::random(8));
        $company = Company::query()->create(['uuid' => (string) Str::uuid(), 'code' => 'NC-'.$token, 'name' => 'Notification Race', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        $this->companies[] = (int) $company->id;
        $this->users[] = (int) $user->id;
        DB::table('company_user_access')->insert(['company_id' => $company->id, 'user_id' => $user->id]);

        return [(int) $company->id, (int) $user->id];
    }

    private function message(int $company, int $user, string $key): int
    {
        return app(NotificationDeliveryService::class)->queue(new QueueNotificationData($company, null, 'workflow', 'workflow_instance', 99, 'assigned', 'Workflow assigned', 'A workflow item requires attention.', null, ['workflow_event' => 'assigned'], [$user], ['database', 'broadcast'], $key, null));
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : self::fail('Expected numeric identifier.');
    }
}
