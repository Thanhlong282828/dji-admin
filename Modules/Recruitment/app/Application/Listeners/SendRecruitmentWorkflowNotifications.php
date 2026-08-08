<?php

namespace Modules\Recruitment\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Modules\Recruitment\Application\Notifications\RecruitmentNotificationRecipientResolver;
use Modules\Recruitment\Application\Notifications\RecruitmentWorkflowNotification;
use Modules\Recruitment\Domain\Events\RecruitmentWorkflowChanged;

final readonly class SendRecruitmentWorkflowNotifications implements ShouldQueue
{
    public function __construct(private RecruitmentNotificationRecipientResolver $recipients) {}

    public function handle(RecruitmentWorkflowChanged $event): void
    {
        foreach ($this->recipients->resolve($event->companyId, $event->entity, $event->entityId, $event->action, $event->actorId) as $recipient) {
            $idempotencyKey = hash('sha256', implode('|', [$event->companyId, $event->entity, $event->entityId, $event->action, $event->version, $recipient->id, 'internal']));
            $claimed = DB::table('recruitment_notification_deliveries')->insertOrIgnore([
                'company_id' => $event->companyId,
                'notification_type' => RecruitmentWorkflowNotification::class,
                'entity_type' => $event->entity,
                'entity_id' => $event->entityId,
                'entity_version' => $event->version,
                'recipient_user_id' => $recipient->id,
                'channel' => 'internal',
                'idempotency_key' => $idempotencyKey,
                'dispatched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($claimed === 1) {
                $recipient->notify(new RecruitmentWorkflowNotification($event->companyId, $event->entity, $event->entityId, $event->action, $event->version));
            }
        }
    }
}
