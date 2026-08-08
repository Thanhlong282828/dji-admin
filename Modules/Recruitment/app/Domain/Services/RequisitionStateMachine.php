<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Validation\ValidationException;

final class RequisitionStateMachine
{
    /** @var array<string, list<string>> */
    private const MAP = [
        'draft' => ['approval', 'cancelled'],
        'approval' => ['approved', 'rejected', 'draft', 'cancelled'],
        'approved' => ['open', 'cancelled'],
        'open' => ['paused', 'filled', 'cancelled'],
        'paused' => ['open', 'cancelled'],
        'rejected' => ['draft'],
        'filled' => [],
        'cancelled' => [],
    ];

    public function assert(string $from, string $to): void
    {
        if (! in_array($to, self::MAP[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển yêu cầu từ {$from} sang {$to}."]);
        }
    }
}
