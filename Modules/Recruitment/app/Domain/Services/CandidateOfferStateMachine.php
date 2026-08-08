<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Recruitment\Domain\Enums\CandidateOfferStatus;

final class CandidateOfferStateMachine
{
    /** @var array<string, list<string>> */
    private const MAP = [
        'draft' => ['pending_approval'],
        'pending_approval' => ['approved', 'draft', 'withdrawn'],
        'approved' => ['sent', 'withdrawn'],
        'sent' => ['accepted', 'declined', 'expired', 'withdrawn'],
        'accepted' => [],
        'declined' => [],
        'expired' => [],
        'withdrawn' => [],
    ];

    public function assert(CandidateOfferStatus $from, CandidateOfferStatus $to): void
    {
        if (! in_array($to->value, self::MAP[$from->value], true)) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển offer từ {$from->value} sang {$to->value}.",
            ]);
        }
    }
}
