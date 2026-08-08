<?php

namespace Modules\Employee\Domain\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case Undisclosed = 'undisclosed';
}
