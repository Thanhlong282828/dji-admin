<?php

namespace Modules\Payroll\Domain\Services;

final readonly class PayrollAdjustmentIntegrityService
{
    public function checksum(string $bytes): string
    {
        return hash('sha256', $bytes);
    }

    public function valid(string $bytes, string $checksum, int $size): bool
    {
        return strlen($bytes) === $size && hash_equals($checksum, $this->checksum($bytes));
    }
}
