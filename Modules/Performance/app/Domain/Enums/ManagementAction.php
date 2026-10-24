<?php

namespace Modules\Performance\Domain\Enums;

enum ManagementAction: string
{
    case OpenRequisition = 'OPEN_REQUISITION';
    case ReviewBudget = 'REVIEW_BUDGET';
    case ReviewCompensation = 'REVIEW_COMPENSATION';
    case ReviewHeadcount = 'REVIEW_HEADCOUNT';
    case NoAction = 'NO_ACTION';
}
