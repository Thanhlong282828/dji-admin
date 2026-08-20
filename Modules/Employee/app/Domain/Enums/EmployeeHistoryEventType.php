<?php

namespace Modules\Employee\Domain\Enums;

enum EmployeeHistoryEventType: string
{
    case Transfer = 'transfer';
    case Promotion = 'promotion';
    case Demotion = 'demotion';
    case SalaryChange = 'salary_change';
    case Discipline = 'discipline';
    case Award = 'award';
    case StatusChange = 'status_change';
}
