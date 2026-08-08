<?php

namespace Modules\Attendance\Domain\Services;

final class OvertimeEligibilityCalculator
{
    public function payableMinutes(int $approvedMinutes, int $actualEligibleMinutes, int $minimumMinutes = 0, int $dailyLimitMinutes = 1440): int
    {
        if (min($approvedMinutes, $actualEligibleMinutes) < $minimumMinutes) {
            return 0;
        }

        return min($approvedMinutes, $actualEligibleMinutes, $dailyLimitMinutes);
    }
}
