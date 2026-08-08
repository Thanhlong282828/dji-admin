<?php

namespace Modules\Onboarding\Domain\Services;

use Illuminate\Validation\ValidationException;
use Modules\Onboarding\Domain\Enums\OnboardingStatus;

final class OnboardingCaseStateMachine
{
    public function assertCanTransition(OnboardingStatus $from, OnboardingStatus $to): void
    {
        $allowed = match ($from) {
            OnboardingStatus::Draft => [OnboardingStatus::Ready, OnboardingStatus::Preboarding, OnboardingStatus::Cancelled],
            OnboardingStatus::Ready => [OnboardingStatus::Preboarding, OnboardingStatus::Cancelled],
            OnboardingStatus::Preboarding => [OnboardingStatus::InProgress, OnboardingStatus::Blocked, OnboardingStatus::Cancelled],
            OnboardingStatus::InProgress => [OnboardingStatus::Blocked, OnboardingStatus::ProbationReview, OnboardingStatus::Completed, OnboardingStatus::Cancelled],
            OnboardingStatus::Blocked => [OnboardingStatus::Preboarding, OnboardingStatus::InProgress, OnboardingStatus::Cancelled],
            OnboardingStatus::ProbationReview => [OnboardingStatus::Completed, OnboardingStatus::InProgress, OnboardingStatus::Cancelled],
            OnboardingStatus::Completed, OnboardingStatus::Cancelled => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển onboarding từ {$from->value} sang {$to->value}."]);
        }
    }
}
