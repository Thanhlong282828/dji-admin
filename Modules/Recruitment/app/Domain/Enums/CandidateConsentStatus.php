<?php

namespace Modules\Recruitment\Domain\Enums;

enum CandidateConsentStatus: string
{
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';
}
