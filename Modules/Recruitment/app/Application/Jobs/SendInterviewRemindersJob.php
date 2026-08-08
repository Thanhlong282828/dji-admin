<?php

namespace Modules\Recruitment\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Recruitment\Domain\Events\RecruitmentWorkflowChanged;
use Modules\Recruitment\Domain\Models\ApplicationInterview;

final class SendInterviewRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(): void
    {
        $now = now();
        foreach ([['action' => 'reminder_24h', 'from' => $now->copy()->addHours(23)->addMinutes(55), 'to' => $now->copy()->addHours(24)->addMinutes(5)], ['action' => 'reminder_1h', 'from' => $now->copy()->addMinutes(55), 'to' => $now->copy()->addMinutes(65)]] as $window) {
            ApplicationInterview::query()
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->whereBetween('starts_at', [$window['from'], $window['to']])
                ->lazyById(100)
                ->each(static function (ApplicationInterview $interview) use ($window): void {
                    event(new RecruitmentWorkflowChanged($interview->company_id, 'interview', $interview->id, $window['action'], $interview->version));
                });
        }
    }
}
