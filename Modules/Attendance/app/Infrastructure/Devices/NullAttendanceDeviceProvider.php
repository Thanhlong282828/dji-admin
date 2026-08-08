<?php

namespace Modules\Attendance\Infrastructure\Devices;

use Modules\Attendance\Domain\Services\AttendanceDeviceProviderInterface;

final class NullAttendanceDeviceProvider implements AttendanceDeviceProviderInterface
{
    public function pull(int $companyId): array
    {
        return [];
    }
}
