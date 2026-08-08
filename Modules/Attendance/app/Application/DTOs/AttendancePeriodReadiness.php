<?php

namespace Modules\Attendance\Application\DTOs;

final readonly class AttendancePeriodReadiness
{
    /** @param list<string> $blockingIssues */
    public function __construct(public int $totalRecords, public int $calculatedRecords, public int $pendingRecords, public int $approvedRecords, public int $rejectedRecords, public int $lockedRecords, public int $missingPunchCount, public int $criticalAnomalyCount, public int $pendingAdjustmentCount, public int $pendingApprovalCount, public int $failedCalculationCount, public int $openSessionCount, public array $blockingIssues, public bool $canLock) {}
}
