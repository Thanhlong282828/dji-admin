<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Performance\Domain\Enums\PerformanceStatus;

final class PerformanceStateMachine
{
    public function validate(PerformanceStatus $from, PerformanceStatus $to): void
    {
        $allowed = match ($from) {
            PerformanceStatus::Draft => [PerformanceStatus::Assigned],
            PerformanceStatus::Assigned => [PerformanceStatus::InProgress],
            PerformanceStatus::InProgress => [PerformanceStatus::SelfReview],
            PerformanceStatus::SelfReview => [PerformanceStatus::ManagerReview],
            PerformanceStatus::ManagerReview => [PerformanceStatus::Calibration, PerformanceStatus::Approved],
            PerformanceStatus::Calibration => [PerformanceStatus::Approved],
            PerformanceStatus::Approved => [PerformanceStatus::Closed],
            PerformanceStatus::Closed => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển {$from->value} sang {$to->value}."]);
        }
    }
}
