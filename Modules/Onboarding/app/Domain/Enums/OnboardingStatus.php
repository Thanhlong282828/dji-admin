<?php

namespace Modules\Onboarding\Domain\Enums;

enum OnboardingStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Preboarding = 'preboarding';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case ProbationReview = 'probation_review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
