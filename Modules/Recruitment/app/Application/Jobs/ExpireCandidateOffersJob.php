<?php

namespace Modules\Recruitment\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;
use Modules\Recruitment\Domain\Models\CandidateOffer;
use Modules\Recruitment\Domain\Services\CandidateOfferService;

final class ExpireCandidateOffersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function handle(CandidateOfferService $service): void
    {
        CandidateOffer::query()
            ->where('status', CandidateOfferStatus::Sent->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->select(['id', 'company_id', 'version'])
            ->chunkById(100, function ($offers) use ($service): void {
                foreach ($offers as $offer) {
                    $service->transition(
                        $offer->company_id,
                        $offer->id,
                        CandidateOfferStatus::Expired,
                        0,
                        'Offer expired automatically',
                        $offer->version,
                    );
                }
            });
    }
}
