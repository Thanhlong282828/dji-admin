<?php

namespace Modules\Payroll\Domain\Services;

final readonly class PaymentFileIntegrityService
{
    public function checksum(string $bytes): string
    {
        return hash('sha256', $bytes);
    }

    public function valid(string $bytes, string $checksum): bool
    {
        return hash_equals($checksum, $this->checksum($bytes));
    }
}
