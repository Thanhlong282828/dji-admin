<?php

namespace Modules\Onboarding\Domain\Enums;

enum OnboardingChecklistStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case NotApplicable = 'not_applicable';
}
