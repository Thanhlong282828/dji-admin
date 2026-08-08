<?php

namespace Modules\Recruitment\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Recruitment\Domain\Enums\InterviewStatus;

final class ApplicationInterviewStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'scheduled' => ['confirmed', 'cancelled', 'no_show'],
        'confirmed' => ['completed', 'cancelled', 'no_show'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    public function assert(InterviewStatus $from, InterviewStatus $to): void
    {
        if (! in_array($to->value, self::TRANSITIONS[$from->value], true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển interview từ {$from->value} sang {$to->value}."]);
        }
    }
}
