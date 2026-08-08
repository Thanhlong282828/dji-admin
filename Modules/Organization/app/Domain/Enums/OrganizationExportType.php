<?php

namespace Modules\Organization\Domain\Enums;

enum OrganizationExportType: string
{
    case COST_CENTERS = 'cost-centers';
    case ORGANIZATION_UNITS = 'organization-units';
    case ORGANIZATION_TREE = 'organization-tree';
    case JOB_POSITIONS = 'job-positions';
    case HEADCOUNT = 'headcount';
}
