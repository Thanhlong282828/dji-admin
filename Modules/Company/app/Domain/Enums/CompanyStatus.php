<?php

namespace Modules\Company\Domain\Enums;

enum CompanyStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
