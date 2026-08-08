<?php

namespace Modules\Attendance\Domain\Services;

interface AttendanceDeviceProviderInterface
{
    /** @return list<array<string, scalar|null>> */
    public function pull(int $companyId): array;
}
