<?php

namespace Modules\Recruitment\Domain\Enums;

enum InterviewResult: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case NoShow = 'no_show';
}
