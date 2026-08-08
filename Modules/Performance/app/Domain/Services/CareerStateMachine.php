<?php

namespace Modules\Performance\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Performance\Domain\Enums\CareerPlanStatus;
use Modules\Performance\Domain\Enums\SuccessionStatus;

final class CareerStateMachine
{
    public function career(CareerPlanStatus $from, CareerPlanStatus $to): void
    {
        $ok = match ($from) {
            CareerPlanStatus::Draft => [CareerPlanStatus::Active, CareerPlanStatus::Cancelled],CareerPlanStatus::Active => [CareerPlanStatus::Completed, CareerPlanStatus::Cancelled],CareerPlanStatus::Completed,CareerPlanStatus::Cancelled => []
        };
        if (! in_array($to, $ok, true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển {$from->value} sang {$to->value}."]);
        }
    }

    public function succession(SuccessionStatus $from, SuccessionStatus $to): void
    {
        $ok = match ($from) {
            SuccessionStatus::Draft => [SuccessionStatus::Nominated],SuccessionStatus::Nominated => [SuccessionStatus::Assessed],SuccessionStatus::Assessed => [SuccessionStatus::Approved],SuccessionStatus::Approved => [SuccessionStatus::Active],SuccessionStatus::Active => [SuccessionStatus::Archived],SuccessionStatus::Archived => []
        };
        if (! in_array($to, $ok, true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển {$from->value} sang {$to->value}."]);
        }
    }
}
