<?php

namespace Modules\Recruitment\Application\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class RecruitmentWorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $companyId,
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly string $action,
        public readonly int $version,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /** @return array{company_id:int,entity_type:string,entity_id:int,action:string,version:int,message:string,route_name:string} */
    public function toArray(object $notifiable): array
    {
        return [
            'company_id' => $this->companyId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'action' => $this->action,
            'version' => $this->version,
            'message' => $this->message(),
            'route_name' => $this->entityType === 'candidate_offer' ? 'admin.candidate-offers.show' : 'admin.interviews.show',
        ];
    }

    private function message(): string
    {
        return match ($this->entityType) {
            'candidate_offer' => "Candidate Offer đã thay đổi trạng thái: {$this->action}.",
            'interview_evaluation' => "Evaluation phỏng vấn đã thay đổi: {$this->action}.",
            default => "Lịch phỏng vấn đã thay đổi: {$this->action}.",
        };
    }
}
