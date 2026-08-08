<?php

namespace Modules\Recruitment\Domain\Enums;

enum InterviewRecommendation: string
{
    case StrongHire = 'strong_hire';
    case Hire = 'hire';
    case Neutral = 'neutral';
    case NoHire = 'no_hire';
    case StrongNoHire = 'strong_no_hire';
}
