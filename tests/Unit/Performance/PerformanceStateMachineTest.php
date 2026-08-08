<?php

namespace Tests\Unit\Performance;

use Illuminate\Validation\ValidationException;
use Modules\Performance\Domain\Enums\PerformanceStatus;
use Modules\Performance\Domain\Services\PerformanceStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PerformanceStateMachineTest extends TestCase
{
    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions(PerformanceStatus $from, PerformanceStatus $to): void
    {
        $this->expectNotToPerformAssertions();
        (new PerformanceStateMachine)->validate($from, $to);
    }

    public function test_closed_and_approved_cannot_return_to_editable_states(): void
    {
        $this->expectException(ValidationException::class);
        (new PerformanceStateMachine)->validate(PerformanceStatus::Closed, PerformanceStatus::InProgress);
    }

    /** @return list<array{PerformanceStatus, PerformanceStatus}> */
    public static function allowedTransitions(): array
    {
        return [
            [PerformanceStatus::Draft, PerformanceStatus::Assigned],
            [PerformanceStatus::Assigned, PerformanceStatus::InProgress],
            [PerformanceStatus::InProgress, PerformanceStatus::SelfReview],
            [PerformanceStatus::SelfReview, PerformanceStatus::ManagerReview],
            [PerformanceStatus::ManagerReview, PerformanceStatus::Calibration],
            [PerformanceStatus::Calibration, PerformanceStatus::Approved],
            [PerformanceStatus::Approved, PerformanceStatus::Closed],
        ];
    }
}
