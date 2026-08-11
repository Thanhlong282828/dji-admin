<?php

namespace Modules\Notification\Domain\Services;

use App\Models\User;
use App\Support\RuntimeConfiguration\FeatureFlag;
use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Notification\Application\DTOs\QueueNotificationData;
use Modules\Notification\Infrastructure\Channels\BroadcastNotificationSender;
use Modules\Notification\Infrastructure\Channels\DatabaseNotificationSender;
use Modules\Notification\Infrastructure\Channels\MailNotificationSender;
use Throwable;

final readonly class NotificationDeliveryService
{
    public function __construct(
        private NotificationPayloadGuard $guard,
        private DatabaseNotificationSender $database,
        private BroadcastNotificationSender $broadcast,
        private MailNotificationSender $mail,
        private RuntimeConfigurationService $configuration,
    ) {}

    public function queue(QueueNotificationData $data): int
    {
        $this->guard->assertSafe($data->safePayload);
        $messageId = DB::transaction(function () use ($data): int {
            $existing = DB::table('notification_messages')->where('company_id', $data->companyId)->where('idempotency_key', $data->idempotencyKey)->value('id');
            if (is_numeric($existing)) {
                return (int) $existing;
            }
            $versionId = $this->publishedVersion($data->companyId, $data->templateId);
            $safe = $data->safePayload + ['entity_type' => $data->entityType, 'entity_id' => $data->entityId, 'action' => $data->action];
            $checksum = hash('sha256', json_encode($safe, JSON_THROW_ON_ERROR));
            $messageId = (int) DB::table('notification_messages')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $data->companyId, 'notification_template_version_id' => $versionId, 'category' => $data->category, 'entity_type' => $data->entityType, 'entity_id' => $data->entityId, 'action' => $data->action, 'title' => $data->title, 'body' => $data->body, 'navigation_reference' => $data->navigationReference, 'safe_payload' => json_encode($safe, JSON_THROW_ON_ERROR), 'payload_checksum' => $checksum, 'idempotency_key' => $data->idempotencyKey, 'status' => 'queued', 'actor_id' => $data->actorId, 'created_at' => now(), 'updated_at' => now()]);
            $recipients = array_values(array_unique(array_filter($data->recipientIds, fn (int $id): bool => ! $data->excludeActor || $id !== $data->actorId)));
            foreach ($recipients as $userId) {
                if (! DB::table('company_user_access')->where('company_id', $data->companyId)->where('user_id', $userId)->exists()) {
                    continue;
                }
                DB::table('notification_recipients')->insertOrIgnore(['company_id' => $data->companyId, 'notification_message_id' => $messageId, 'user_id' => $userId, 'resolution_source' => 'resolved', 'created_at' => now(), 'updated_at' => now()]);
                foreach (array_values(array_unique($data->channels)) as $channel) {
                    if (! $this->preferenceEnabled($data->companyId, $userId, $data->category, $channel)) {
                        continue;
                    }
                    $key = "notification:{$messageId}:{$userId}:{$channel}";
                    DB::table('notification_deliveries')->insertOrIgnore(['company_id' => $data->companyId, 'notification_message_id' => $messageId, 'notification_type' => 'enterprise-center', 'entity_type' => $data->entityType, 'entity_id' => $data->entityId, 'entity_version' => 1, 'recipient_id' => $userId, 'channel' => $channel, 'action' => $data->action, 'idempotency_key' => Str::uuid(), 'artifact_uuid' => (string) Str::uuid(), 'status' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            return $messageId;
        }, 3);

        $this->deliverMessage($data->companyId, $messageId);

        return $messageId;
    }

    public function retry(int $companyId, int $deliveryId): void
    {
        $this->deliver($companyId, $deliveryId);
    }

    public function deliverMessage(int $companyId, int $messageId): void
    {
        $ids = DB::table('notification_deliveries')->where('company_id', $companyId)->where('notification_message_id', $messageId)->whereIn('status', ['pending', 'failed'])->pluck('id');
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $this->deliver($companyId, (int) $id);
            }
        }
        $failed = DB::table('notification_deliveries')->where('company_id', $companyId)->where('notification_message_id', $messageId)->where('status', 'failed')->exists();
        DB::table('notification_messages')->where('company_id', $companyId)->where('id', $messageId)->update(['status' => $failed ? 'partial' : 'delivered', 'updated_at' => now()]);
    }

    private function deliver(int $companyId, int $deliveryId): void
    {
        DB::transaction(function () use ($companyId, $deliveryId): void {
            $delivery = DB::table('notification_deliveries')->where('company_id', $companyId)->where('id', $deliveryId)->lockForUpdate()->first();
            abort_if($delivery === null, 404);
            $deliveryData = $this->scalarRow($delivery);
            if ($this->text($deliveryData, 'status') === 'sent') {
                return;
            }
            $channel = $this->text($deliveryData, 'channel');
            if (! $this->channelEnabled($channel)) {
                DB::table('notification_deliveries')->where('id', $deliveryId)->update(['status' => 'failed', 'failure_message' => 'controlled_disabled', 'updated_at' => now()]);

                return;
            }
            $attempts = $this->integer($deliveryData, 'attempts');
            abort_if($attempts >= 3, 409, 'Notification retry limit reached.');
            $message = DB::table('notification_messages')->where('company_id', $companyId)->where('id', $this->integer($deliveryData, 'notification_message_id'))->first();
            abort_if($message === null, 404);
            $messageData = $this->scalarRow($message);
            $user = User::query()->whereKey($this->integer($deliveryData, 'recipient_id'))->first();
            abort_if($user === null || ! DB::table('company_user_access')->where('company_id', $companyId)->where('user_id', $user->id)->exists(), 404);
            $navigation = $messageData['navigation_reference'] ?? null;
            $payload = ['uuid' => $this->text($messageData, 'uuid'), 'title' => $this->text($messageData, 'title'), 'body' => $this->text($messageData, 'body'), 'category' => $this->text($messageData, 'category'), 'entity_type' => $this->text($messageData, 'entity_type'), 'entity_id' => $this->integer($messageData, 'entity_id'), 'action' => $this->text($messageData, 'action'), 'navigation_reference' => is_string($navigation) ? $navigation : null, 'checksum' => $this->text($messageData, 'payload_checksum')];
            $attempt = $attempts + 1;
            try {
                $this->sender($channel)->send($this->text($deliveryData, 'artifact_uuid'), $user, $payload);
                $result = 'success';
                $code = null;
                $failure = null;
                $status = 'sent';
            } catch (Throwable $e) {
                $result = 'failed';
                $code = class_basename($e);
                $failure = Str::limit($e->getMessage(), 500);
                $status = 'failed';
            }
            $attemptPayload = ['delivery_id' => $deliveryId, 'attempt' => $attempt, 'result' => $result, 'code' => $code];
            DB::table('notification_delivery_attempts')->insert(['company_id' => $companyId, 'notification_delivery_id' => $deliveryId, 'attempt_number' => $attempt, 'result' => $result, 'failure_code' => $code, 'safe_failure_message' => $failure, 'checksum' => hash('sha256', json_encode($attemptPayload, JSON_THROW_ON_ERROR)), 'attempted_at' => now()]);
            DB::table('notification_deliveries')->where('id', $deliveryId)->update(['status' => $status, 'attempts' => $attempt, 'sent_at' => $status === 'sent' ? now() : null, 'failed_at' => $status === 'failed' ? now() : null, 'failure_message' => $failure, 'updated_at' => now()]);
        }, 3);
    }

    private function sender(string $channel): DatabaseNotificationSender|BroadcastNotificationSender|MailNotificationSender
    {
        return match ($channel) {
            'database' => $this->database, 'broadcast' => $this->broadcast, 'email' => $this->mail, default => abort(422, 'Unsupported notification channel.')
        };
    }

    private function publishedVersion(int $companyId, ?int $templateId): ?int
    {
        if ($templateId === null) {
            return null;
        }
        $id = DB::table('notification_template_versions as v')->join('notification_templates as t', 't.id', '=', 'v.notification_template_id')->where('t.id', $templateId)->where(fn (Builder $query): Builder => $query->where('t.company_id', $companyId)->orWhereNull('t.company_id'))->where('t.status', 'published')->whereNotNull('v.published_at')->orderByDesc('v.version')->value('v.id');
        abort_unless(is_numeric($id), 422, 'Published notification template is required.');

        return (int) $id;
    }

    private function preferenceEnabled(int $companyId, int $userId, string $category, string $channel): bool
    {
        $preference = DB::table('notification_preferences')->where('company_id', $companyId)->where('user_id', $userId)->whereIn('category', [$category, '*'])->orderByRaw('category = ? desc', [$category])->first();
        if ($preference === null) {
            return $channel !== 'email';
        }

        return (bool) ($preference->{$channel.'_enabled'} ?? false);
    }

    private function channelEnabled(string $channel): bool
    {
        return match ($channel) {
            'email' => $this->configuration->enabled(FeatureFlag::NotificationEmail),
            'broadcast' => $this->configuration->enabled(FeatureFlag::NotificationBroadcast),
            default => true,
        };
    }

    /** @return array<string, scalar|null> */
    private function scalarRow(object $row): array
    {
        $result = [];
        foreach (get_object_vars($row) as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @param array<string, scalar|null> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        abort_unless(is_int($value) || (is_string($value) && ctype_digit($value)), 500, "Invalid {$key}.");

        return (int) $value;
    }

    /** @param array<string, scalar|null> $row */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        abort_unless(is_string($value), 500, "Invalid {$key}.");

        return $value;
    }
}
