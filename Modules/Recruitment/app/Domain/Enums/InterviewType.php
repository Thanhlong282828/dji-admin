<?php

namespace Modules\Recruitment\Domain\Enums;

enum InterviewType: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Phone = 'phone';
}
