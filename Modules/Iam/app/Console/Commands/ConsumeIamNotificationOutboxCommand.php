<?php

namespace Modules\Iam\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Buses\CommandBusInterface;
use Modules\Iam\Domain\Services\IamValue;
use Modules\Notification\Application\Commands\QueueNotification\QueueNotificationCommand;
use Modules\Notification\Application\DTOs\QueueNotificationData;

final class ConsumeIamNotificationOutboxCommand extends Command
{
    protected $signature = 'iam:consume-notifications {--limit=100}';

    protected $description = 'Project IAM outbox events into Notification Center';

    public function handle(CommandBusInterface $commands): int
    {
        $ids = DB::table('iam_outbox')->whereNull('published_at')->where('available_at', '<=', now())->orderBy('id')->limit(max(1, (int) $this->option('limit')))->pluck('id');
        foreach ($ids as $rawId) {
            $id = IamValue::int($rawId);
            DB::transaction(function () use ($id, $commands): void {
                $row = DB::table('iam_outbox')->where('id', $id)->whereNull('published_at')->lockForUpdate()->first();
                if (! $row instanceof \stdClass) {
                    return;
                }
                $companyId = IamValue::int($row->company_id);
                $event = IamValue::string($row->event_type);
                $payload = json_decode(IamValue::string($row->payload), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new \UnexpectedValueException('Invalid IAM outbox payload.');
                }
                $recipients = $this->recipients($companyId, $event, $payload);
                $label = str_replace(['iam.', '.'], ['', ' '], $event);
                $commands->dispatch(new QueueNotificationCommand(new QueueNotificationData($companyId, null, 'iam', IamValue::string($row->aggregate_type), IamValue::int($row->aggregate_id), $event, 'IAM: '.ucwords($label), 'An identity and access governance action requires your attention.', '/admin/iam?company_id='.$companyId, ['event' => $event, 'aggregate_id' => IamValue::int($row->aggregate_id)], $recipients, ['database', 'broadcast'], IamValue::string($row->uuid), null)));
                DB::table('iam_outbox')->where('id', $id)->update(['published_at' => now(), 'attempts' => DB::raw('attempts+1')]);
            }, 3);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<mixed>  $payload
     * @return list<int>
     */
    private function recipients(int $companyId, string $event, array $payload): array
    {
        $direct = $payload['subject_user_id'] ?? $payload['user_id'] ?? null;
        if (is_int($direct) || (is_string($direct) && ctype_digit($direct))) {
            return [(int) $direct];
        }
        $permission = str_contains($event, 'review') ? 'iam.review.manage' : (str_contains($event, 'approved') ? 'iam.assignment.manage' : 'iam.request.review');

        $companyUsers = DB::table('company_user_access')->where('company_id', $companyId)->pluck('user_id')->map(fn (mixed $value): int => IamValue::int($value))->all();

        return array_values(User::permission($permission)->whereIn('id', $companyUsers)->orderBy('id')->pluck('id')->map(fn (mixed $value): int => IamValue::int($value))->all());
    }
}
