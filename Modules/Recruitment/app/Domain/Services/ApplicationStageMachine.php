<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Validation\ValidationException;

final class ApplicationStageMachine
{
    private const NEXT = ['received' => 'screening', 'screening' => 'test', 'test' => 'interview', 'interview' => 'reference', 'reference' => 'offer'];

    public function assert(string $from, string $to): void
    {
        if ($to === 'hired') {
            throw ValidationException::withMessages(['stage' => 'Stage hired chỉ được thiết lập bởi Hire Candidate workflow.']);
        }
        if ($to === 'rejected' && $from !== 'hired') {
            return;
        }
        if ((self::NEXT[$from] ?? null) !== $to) {
            throw ValidationException::withMessages(['stage' => "Không thể chuyển ứng viên từ {$from} sang {$to}."]);
        }
    }
}
